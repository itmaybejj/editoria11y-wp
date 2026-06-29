import { Ed11y, Lang, State, UI, findElements, elements, computeAccessibleName, createDismissalKey, getElements, refresh, sanitizeHTML, setFixedRoots, version } from 'editoria11y-js';
import { lang as ed11yUiLang } from 'editoria11y-lang';
import { lang as ed11yContentLang } from 'editoria11y-lang-content';

// Build the dual-language dictionary the bundled library consumes.
//
// The checker reads ONE flat string table (Lang.langStrings) for both the
// panel/tip UI *and* the content-detection ruleset (stopword lists, the
// "click here" set, suspicious-alt words). We want those two halves in
// different languages: UI in the editor's locale, ruleset in the locale of
// the content being scanned — so an English-speaking editor reviewing
// Spanish content still catches Spanish "haga clic aquí" links.
//
// PHP enqueues two packs (editoria11y-lang = UI locale, editoria11y-lang-content
// = content locale). Each pack exports `{ strings, testNames, ruleset }`,
// where `strings` already contains the ruleset keys; assigning the content
// pack's `ruleset` over the UI pack's `strings` swaps just the
// content-detection half. When both packs are the same locale the assign is
// a harmless no-op. Pure helper, exported for the unit suite.
export function ed11yBuildLang(uiLang, contentLang) {
  const ui = uiLang && typeof uiLang === 'object' ? uiLang : {};
  const content = contentLang && typeof contentLang === 'object' ? contentLang : {};
  return {
    strings: Object.assign({}, ui.strings, content.ruleset),
    testNames: ui.testNames,
    ruleset: content.ruleset || ui.ruleset || {},
  };
}

const ed11yLang = ed11yBuildLang(ed11yUiLang, ed11yContentLang);

// Seed the runtime table now so any early lookup resolves. The library
// re-applies this from `options.lang` during construction (see ed11yInit),
// which is what actually survives — without `options.lang` set, the library
// resets Lang back to its built-in English pack. testNames are panel labels,
// always the editor's (UI) locale.
Lang.addI18n(ed11yLang.strings);
Lang.testNames = { ...(Lang.testNames || {}), ...(ed11yUiLang.testNames || {}) };

// Fetch static config from /wp-json/ed11y/v1/config.
// Browser-cached for 30 days via Cache-Control: immutable; cachebust is the
// ?v=<n> in the URL. 
// X-WP-Nonce is needed because REST requests are processed as anonymous.
async function ed11yLoadStaticConfig(configUrl) {
  if (!configUrl) return null;
  const headers = {};
  if (typeof wpApiSettings !== 'undefined' && wpApiSettings && wpApiSettings.nonce) {
    headers['X-WP-Nonce'] = wpApiSettings.nonce;
  }
  try {
    const res = await fetch(configUrl, {
      credentials: 'same-origin',
      cache: 'force-cache',
      headers,
    });
    if (!res.ok) return null;
    return await res.json();
  } catch (e) {
    console.warn('Editoria11y: static config fetch failed', e);
    return null;
  }
}

// Apply the static config payload.
// Duplicate keys may be present during the 3.0.0 migration cron.
function ed11yApplyStaticConfig(data, options) {
  if (!data) return;
  if (data.testNames && typeof data.testNames === 'object') {
    Lang.testNames = { ...Lang.testNames, ...data.testNames };
  }
  if (!options) return;

  if (data.globalDismissals && typeof data.globalDismissals === 'object') {
    const synced = (options.syncedDismissals && typeof options.syncedDismissals === 'object')
      ? options.syncedDismissals
      : {};
    for (const [resultKey, elementMap] of Object.entries(data.globalDismissals)) {
      synced[resultKey] = { ...(synced[resultKey] || {}), ...elementMap };
    }
    options.syncedDismissals = synced;
  }
  for (const [key, value] of Object.entries(data)) {
    if (key === 'testNames' || key === 'globalDismissals') continue;
    if (value !== undefined && value !== null) {
      options[key] = value;
    }
  }
}

// Mirrors `editoria11yOptions.options()` in Drupal. Exported for the
// unit suite; runs in both the free and premium builds.
export function ed11yApplyOptionTranslations(options) {
  if (!options || typeof options !== 'object') return;

  if (!options.checkRoot) {
    options.checkRoot = document.querySelector('main') ? 'main' : 'body > *:not(#wpadminbar, script, style, .ed11y-element)';
  }

  options.linkIgnore = '[aria-hidden="true"][tabindex="-1"], [href*="/wp-admin/"]';

  // `linkStringsNewWindows` is a /pipe|delimited|phrases/g regex used to
  // detect "opens in a new tab" warnings inside link text.
  options.linkStringsNewWindows = options.linkStringsNewWindows
    ? new RegExp(options.linkStringsNewWindows, 'g')
    : /window|\stab|download/g;

  // `linkIgnoreStrings` strips from link text *before* running the 
  // empty/meaningless-text checks. Library wants an array.
  if (typeof options.linkIgnoreStrings === 'string' && options.linkIgnoreStrings.length > 0) {
    options.linkIgnoreStrings = options.linkIgnoreStrings.split('|').map((s) => s.trim()).filter(Boolean);
  } else if (!Array.isArray(options.linkIgnoreStrings)) {
    options.linkIgnoreStrings = [];
  }

  // The library's checkCustomRuleset() iterates `EMBED_CUSTOM.sources`
  // selectors and pushes EMBED_GENERAL warnings for each match.
  if (typeof options.embeddedContentWarning === 'string' && options.embeddedContentWarning.trim().length > 0) {
    options.checks = options.checks || {};
    options.checks.EMBED_CUSTOM = { sources: options.embeddedContentWarning };
  }

  // Tests are ignored by setting options.checks[KEY] = false.
  // CSA mode reprocesses this with more complexity.
  if (Array.isArray(options.ignoreTests)) {
    options.checks = options.checks || {};
    options.ignoreTests.forEach((key) => {
      if (typeof key === 'string' && key.length > 0) {
        options.checks[key] = false;
      }
    });
  }

  // CSA: if `profile === 'dev'`, pick the other alert mode.
  if (options.profile === 'dev' && options.devAssertiveness) {
    options.alertMode = options.devAssertiveness;
  }
}

// Build the library's CSA dev/content split configuration.
//
// Mirrors the `if (!!dS.profile)` branch of Drupal's editoria11yOptions.js
// (the source of truth — keep the devChecks map below in lockstep; the
// parity guard in tests/js/unit/devchecks-parity.test.js enforces it).
//
// Two distinct jobs:
//   1. `Object.assign(options.checks, devChecks)` turns ON the library
//      tests that ship disabled-by-default (contrast, forms, ARIA, page
//      meta, developer checks) and sets a few default-on tests, so the
//      library actually RUNS them. Without this merge they fall through to
//      their disabled defaults — the original "showing up false even when
//      enabled" bug.
//   2. `splitConfiguration.devChecks` is the separate *visibility* list:
//      which running tests are shown to developers only vs. everyone.
//
// `export`ed for the Jest unit suite; the export lives inside the
// premium markers so the free build strips the whole function. This is
// the rendered front end, so `editors` is always false (matches Drupal's
// `options.editors` on non-edit routes) — page-level checks like
// HEADING_MISSING_ONE / META_* surface here. The in-editor variant in
// editoria11y-editor.js sets it true and suppresses them.


let ed11yOptions = {};
let ed11yResetID = false;

// Create callback to see if document is ready.
function ed11yReady(fn) {
  if (document.readyState != 'loading') {
    fn();
  } else {
    document.addEventListener('DOMContentLoaded', fn);
  }
}

// Resolve a WordPress attachment ID from an <img> class attribute.
//
// Featured/template images get a `data-id` from the
// wp_get_attachment_image_attributes PHP filter, but body-content images
// (block/classic editor) are stored as static HTML.
function ed11yWpImageIdFromClass(classAttr) {
  if (typeof classAttr !== 'string') {
    return null;
  }
  for (const token of classAttr.split(/\s+/)) {
    const match = /^wp-image-(\d+)$/.exec(token);
    if (match) {
      const id = parseInt(match[1], 10);
      if (id > 0) {
        return id;
      }
    }
  }
  return null;
}

// Notify the parent crawler that this iframe's scan completed (or was
// skipped for a benign reason — password form, missing config, etc.).
//
// The parent (assets/js/editoria11y-crawler.js) sets `window.ed11ySynced`
// as a sentinel before opening any iframes, then listens for
// `{ done: <iframe-src> }` postMessage events to drain the iframe pool.
// Without this emitter, iframes that complete a scan fast enough never
// signal "done" and the parent's stuck-iframe decay loop has to time
// them out at ~20s each — which compounds badly across thousands of URLs.
//
// Same-origin only: cross-origin parents reject the postMessage and the
// try/catch eats the error silently. The sentinel check (`!== undefined`)
// also guards against the everyday case of the iframe being opened by
// something other than the crawler (a normal browser window).
function reportSyncDone() {
  try {
    if (
      window.parent &&
      window.parent !== window &&
      window.parent.ed11ySynced !== undefined
    ) {
      window.parent.postMessage(
        { done: String(window.location) },
        window.location.origin
      );
    }
  } catch (_e) {
    /* cross-origin parent — fail silently */
  }
}

function ed11ySync() {
  let postData = function (action, data) {
    // Returns the promise so callers can chain reportSyncDone after the
    // PUT settles. Previously fire-and-forget — the promise's resolution
    // had no observers, which is why crawler-driven iframes never told
    // the parent they were done.
    return fetch(wpApiSettings.root + 'ed11y/v1/' + action, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'accept': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce,
      },
      body: JSON.stringify({
        data,
      })
    }).then(function (response) {
      return response.json();
    });
  };

  let extractResults = function () {
    let results = {};
    let dismissals = [];
    let total = 0;
    State.results.forEach(result => {
      let testName = result.test;
      if (result.dismissalStatus !== 'ok') {
        // log all items not marked as OK
        if (results[testName]) {
          results[testName] = parseInt(results[testName]) + 1;
          total++;
        } else {
          results[testName] = 1;
          total++;
        }
      }
      if (result.dismissalStatus !== 'false') {
        let insert = [testName, result.dismissalKey];
        dismissals.push(
          insert
        );
      }
    });
    return [results, dismissals, total];
  };

  let url = State.option.currentPage;
  url = url.length > 190 ? url.substring(0, 189) : url;
  let queryString = window.location.search;
  let urlParams = new URLSearchParams(queryString);
  ed11yResetID = urlParams.get('ed1ref');

  let sendResults = function () {
    window.setTimeout(function () {
      if (ed11yOptions.post_id && document.getElementsByClassName('post-password-form').length > 0) {
        // Don't sync "0 results" if the post has been replaced by a login form.
        // Still notify the crawler parent so it doesn't wait the 20s decay.
        reportSyncDone();
        return;
      }
      let results = extractResults();
      let data = {
        page_title: ed11yOptions.title,
        post_id: ed11yOptions.post_id ? ed11yOptions.post_id : 0,
        page_count: results[2],
        entity_type: ed11yOptions.entity_type, // node or false
        results: results[0],
        dismissals: results[1],
        page_url: url,
        created: 0,
        pid: ed11yResetID ? parseInt(ed11yResetID) : -1,
      };
      // Fire reportSyncDone after the PUT settles, success or failure —
      // the crawler only cares that this iframe is no longer in flight.
      postData('result', data).then(reportSyncDone, reportSyncDone);
      ed11yResetID = false;
      // Short timeout to let execution queue clear.
    }, 100);
  };

  let resetResults = function () {
    window.setTimeout(function () {
      let results = {};
      let data = {
        page_title: ed11yOptions.title,
        page_count: results[2],
        entity_type: ed11yOptions.entity_type, // node or false
        results: results[0],
        dismissals: results[1],
        page_url: url,
        created: 0,
        pid: ed11yResetID ? parseInt(ed11yResetID) : -1,
        post_id: ed11yOptions.post_id ? ed11yOptions.post_id : 0,
      };
      postData('result', data).then(reportSyncDone, reportSyncDone);
      // Short timeout to let execution queue clear.
    }, 100);
  };
  if (ed11yResetID && ed11yOptions.preventCheckingIfPresent && !!document.querySelector(ed11yOptions.preventCheckingIfPresent)) {
    // We just got here from the dashboard and there should not be results at this route.
    resetResults();
  }

  document.addEventListener('ed11yResults', function () {
    sendResults();
  });

  let sendDismissal = function (detail) {
    if (detail) {
      let data = {
        page_url: State.option.currentPage,
        result_key: detail.dismissTest, // which test is sending a result
        element_id: detail.dismissKey, // some recognizable attribute of the item marked
        dismissal_status: detail.dismissAction, // ok, ignore or reset
        post_id: ed11yOptions.post_id ? ed11yOptions.post_id : 0,
      };
      postData('dismiss', data);
    }
  };
  document.addEventListener('ed11yDismissalUpdate', function (e) {
    sendDismissal(e.detail);
  }, false);

}

const ed11yCustomTests = function () {
  document.addEventListener('ed11yRunCustomTests', function () {

    // Find empty WP buttons.
    findElements('emptyWpButton', 'a.wp-element-button:not([href], [tabindex])');

    // Register a human-readable test name.
    Lang.testNames.emptyWpButton = 'Empty button-style link';
    const wrapper = document.createElement('div');
    const title = document.createElement('div');
    title.textContent = 'Empty link';
    title.classList.add('title');
    const p = document.createElement('p');
    p.textContent = 'The button style link is missing its URL.';
    wrapper.appendChild(title);
    wrapper.appendChild(p);

    elements.emptyWpButton?.forEach((el) => {
      State.results.push({
        element: el,
        test: 'emptyWpButton',
        content: wrapper,
        position: 'beforebegin',
        dismissalKey: false,
        type: 'warning',
      });
    });
    let allDone = new CustomEvent('ed11yResume');
    document.dispatchEvent(allDone);
  });
};

const ed11yInit = async function () {

  let ed11yOpts = document.getElementById('editoria11y-init');
  if (!!ed11yOpts && window.location.href.indexOf('elementor-preview') === -1) {
    ed11yOptions = JSON.parse(ed11yOpts.innerHTML);
    // Merge the immutable static config blob into dynamic options.
    ed11yApplyStaticConfig(
      await ed11yLoadStaticConfig(ed11yOptions.config_url),
      ed11yOptions
    );

    // +1 for the built-in `emptyWpButton` test.
    ed11yOptions.customTests = Number.parseInt(ed11yOptions.customTests) + 1;

    // Hard-coded known conflicts for panel placement.
    ed11yOptions.panelNoCover = ed11yOptions.panelNoCover
      ? `${ed11yOptions.panelNoCover}, #edac-highlight-panel`
      : '#edac-highlight-panel';

    ed11yOptions.reportsURL = !ed11yOptions.hideReportLink && ed11yOptions.adminUrl ? ed11yOptions.adminUrl + '?page=editoria11y' : false;

    if (ed11yOptions.title.length < 3) {
      ed11yOptions.title = document.title;
    }

    // All the static config → library option translations (RegExp build,
    // CSV → array, embeddedContentWarning → checks.EMBED_CUSTOM,
    // ignoreTests → checks[KEY]=false, dev-profile alertMode swap).
    ed11yApplyOptionTranslations(ed11yOptions);

    // CSA: build splitConfiguration + enable dev/contrast/readability
    // plugins when the per-page blob set profile. No-op otherwise.
    

    if (window.location.href.indexOf('preview=true') > -1) {
      ed11yOptions['alertMode'] = 'assertive';
    }
    if (window.location.href.indexOf('ed1ref') > -1) {
      ed11yOptions['alertMode'] = 'assertive';
      ed11yOptions['showDismissed'] = true;
    }
    ed11yOptions.cssUrls = [ed11yOptions.cssLocation];

    // Pin the merged dual-language dictionary so the library uses it during
    // preProcessOptions. The library calls Lang.addI18n(State.option.lang.strings)
    // on construction and, when options.lang is unset, falls back to its
    // built-in English pack — which is what previously clobbered the
    // enqueued translation back to English for non-English sites.
    ed11yOptions.lang = ed11yLang;

    let lateResultsReady;
    document.addEventListener('ed11yResults', function () {
      // Delay to make sure page has painted. Not needed until a tip is drawn.
      if (lateResultsReady) {
        return;
      }
      lateResultsReady = true;
      const editLink = document.querySelector('#wp-admin-bar-edit a');
      // todo: this is not always detected due to race condition.
      const elementorLink = document.querySelector('#wp-admin-bar-elementor_edit_page a');
      if (editLink || elementorLink) {
        const editIcon = document.createElement('span');
        editIcon.classList.add('ed11y-custom-edit-icon');
        editIcon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free 6.7.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path fill="currentColor" d="M441 58.9L453.1 71c9.4 9.4 9.4 24.6 0 33.9L424 134.1 377.9 88 407 58.9c9.4-9.4 24.6-9.4 33.9 0zM209.8 256.2L344 121.9 390.1 168 255.8 302.2c-2.9 2.9-6.5 5-10.4 6.1l-58.5 16.7 16.7-58.5c1.1-3.9 3.2-7.5 6.1-10.4zM373.1 25L175.8 222.2c-8.7 8.7-15 19.4-18.3 31.1l-28.6 100c-2.4 8.4-.1 17.4 6.1 23.6s15.2 8.5 23.6 6.1l100-28.6c11.8-3.4 22.5-9.7 31.1-18.3L487 138.9c28.1-28.1 28.1-73.7 0-101.8L474.9 25C446.8-3.1 401.2-3.1 373.1 25zM88 64C39.4 64 0 103.4 0 152L0 424c0 48.6 39.4 88 88 88l272 0c48.6 0 88-39.4 88-88l0-112c0-13.3-10.7-24-24-24s-24 10.7-24 24l0 112c0 22.1-17.9 40-40 40L88 464c-22.1 0-40-17.9-40-40l0-272c0-22.1 17.9-40 40-40l112 0c13.3 0 24-10.7 24-24s-10.7-24-24-24L88 64z"/></svg>';
        const reLink = function (link, type) {
          const linkButton = document.createElement('a');
          linkButton.href = link.href;
          linkButton.textContent = link.textContent;
          if (type === 'elementor') {
            const eIcon = editIcon.cloneNode(true);
            eIcon.style.fontSize = '1.125em';
            eIcon.style.lineHeight = '.9em';
            eIcon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" fill="none"><g clip-path="url(#clip0)"><path d="M200 -1.52588e-05C89.5321 -1.52588e-05 0 89.5321 0 200C0 310.431 89.5321 400 200 400C310.468 400 400 310.468 400 200C399.964 89.5321 310.431 -1.52588e-05 200 -1.52588e-05ZM150.009 283.306H116.694V116.658H150.009V283.306ZM283.306 283.306H183.324V249.991H283.306V283.306ZM283.306 216.639H183.324V183.324H283.306V216.639ZM283.306 149.973H183.324V116.658H283.306V149.973Z" fill="currentColor"></path></g><defs><clipPath id="clip0"><rect width="400" height="400" fill="white"></rect></clipPath></defs></svg>';
            linkButton.prepend(eIcon);
          } else {
            linkButton.prepend(editIcon.cloneNode(true));
          }
          return linkButton;
        };
        const editLinks = document.createElement('div');
        if (editLink) {
          editLinks.appendChild(reLink(editLink));
        }
        if (elementorLink) {
          editLinks.appendChild(reLink(elementorLink, 'elementor'));
        }
        State.option.editLinks = editLinks;

        // Honor the `hide_edit_links` admin setting (PHP emits it as
        // `hideEditLinks`): suppress the wp-admin-bar edit shortcut on
        // any tip whose element matches the configured selector. An
        // asterisk hides the links everywhere.
        if (typeof ed11yOptions.hideEditLinks === 'string' && ed11yOptions.hideEditLinks.trim().length > 0) {
          const hideSelector = ed11yOptions.hideEditLinks.trim();
          document.addEventListener('ed11yPop', (e) => {
            const matches = '*' === hideSelector
              ? true
              : e.detail.result.element?.closest(hideSelector);
            if (matches) {
              e.detail.tip.shadowRoot.querySelector('.ed11y-custom-edit-links')?.setAttribute('hidden', '');
            }
          });
        }
      }
    });

    document.addEventListener('ed11yPop', (e) => {
      if (!e.detail.result.element.matches('img')) {
        return;
      }
      // Get image ID from data attribute or class.
      let imageId = e.detail.result.element.dataset.id;
      if (!imageId) {
        imageId = ed11yWpImageIdFromClass(e.detail.result.element.getAttribute('class'));
      }

      const alreadyDecorated = e.detail.tip.dataset.alreadyDecorated;
      if (imageId && !alreadyDecorated) {
        const editIcon = document.createElement('span');
        editIcon.classList.add('ed11y-custom-edit-icon');
        editIcon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free 6.7.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path fill="currentColor" d="M448 80c8.8 0 16 7.2 16 16l0 319.8-5-6.5-136-176c-4.5-5.9-11.6-9.3-19-9.3s-14.4 3.4-19 9.3L202 340.7l-30.5-42.7C167 291.7 159.8 288 152 288s-15 3.7-19.5 10.1l-80 112L48 416.3l0-.3L48 96c0-8.8 7.2-16 16-16l384 0zM64 32C28.7 32 0 60.7 0 96L0 416c0 35.3 28.7 64 64 64l384 0c35.3 0 64-28.7 64-64l0-320c0-35.3-28.7-64-64-64L64 32zm80 192a48 48 0 1 0 0-96 48 48 0 1 0 0 96z"/></svg>';
        const linkButton = document.createElement('a');
        editIcon.style.fontSize = '1em';
        // Use the attachment edit screen (post.php) rather than
        // upload.php?item=, whose details modal only opens in the media
        // library's grid view and silently no-ops in list view.
        linkButton.href = `${ed11yOptions.adminUrl}post.php?post=${imageId}&action=edit`;
        linkButton.textContent = 'Edit Media';
        linkButton.prepend(editIcon);
        const buttonBar = e.detail.tip.shadowRoot.querySelector('.ed11y-custom-edit-links > div');
        buttonBar?.appendChild(linkButton);
      }
      e.detail.tip.dataset.alreadyDecorated = 'true';
    });

    window.ed11y = new Ed11y(ed11yOptions);
    // Expose the library's State / UI / Lang namespaces alongside the
    // instance so tooling (browser-console diagnostics, the Playwright
    // parity suite, third-party integrations) can read the live runtime
    // dictionaries without re-importing the bundle. Mirrors what the
    // editor shim does for the MCE iframe loader.
    window.Ed11y = Ed11y;
    window.Ed11y.State = State;
    window.Ed11y.UI = UI;
    window.Ed11y.Lang = Lang;
    window.Ed11y.refresh = refresh;
    window.Ed11y.createDismissalKey = createDismissalKey;
    window.Ed11y.computeAccessibleName = computeAccessibleName;
    window.Ed11y.getElements = getElements;
    window.Ed11y.sanitizeHTML = sanitizeHTML;
    window.Ed11y.setFixedRoots = setFixedRoots;
    window.Ed11y.version = version;
    ed11yCustomTests();
    ed11ySync();
  } else {
    // Either the page has no editoria11y-init blob (a route the plugin
    // doesn't try to scan) or we're inside an Elementor preview iframe.
    // No scan will run, so no postData/reportSyncDone chain will fire —
    // tell the crawler parent we're done so it doesn't wait the 20s decay.
    reportSyncDone();
  }
};

// Call callback, init Editoria11y.
ed11yReady(
  function () {
    window.setTimeout(() => {
      ed11yInit();
    }, 0);
  }
);
