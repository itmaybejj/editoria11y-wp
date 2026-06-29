import { Ed11y, Lang, State, UI, findElements, elements, computeAccessibleName, createDismissalKey, getElements, refresh, sanitizeHTML, setFixedRoots, version } from 'editoria11y-js';
import { lang as ed11yUiLang } from 'editoria11y-lang';
import { lang as ed11yContentLang } from 'editoria11y-lang-content';
// `findElements`, `elements`, `createDismissalKey`, `sanitizeHTML` are
// used by `buttonBlockTest` below — kept imported even after the manual
// custom-rules loop was retired (the library now consumes camelCase
// `customRules` directly via prepareCustomRuleset()).

// Dual-language merge — see editoria11y-wp.js for the full rationale. The
// UI half (panel/tip strings, testNames) is the editor's locale; the
// content-detection ruleset (stopwords, "click here") is the locale of the
// post being edited, so reviewing a Spanish translation in an English admin
// still runs the Spanish link/alt checks. Inline copy rather than a shared
// module, matching the two shims' independent enqueue contexts.
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

Lang.addI18n(ed11yLang.strings);
Lang.testNames = { ...(Lang.testNames || {}), ...(ed11yUiLang.testNames || {}) };

// See editoria11y-wp.js for rationale. Identical helper inlined rather than
// shared because there's no third consumer yet — refactor to a module if a
// fourth init script needs the same fetch-and-merge pattern.
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

// See editoria11y-wp.js for the full rationale on each conversion.
// Inline copy rather than shared module because the two shims have
// distinct enqueue contexts (script-module vs. classic-script + iframe
// loader); a third consumer would justify extracting.
export function ed11yApplyOptionTranslations(options) {
  if (!options || typeof options !== 'object') return;

  options.linkStringsNewWindows = options.linkStringsNewWindows
    ? new RegExp(options.linkStringsNewWindows, 'g')
    : /window|\stab|download/g;

  if (typeof options.linkIgnoreStrings === 'string' && options.linkIgnoreStrings.length > 0) {
    options.linkIgnoreStrings = options.linkIgnoreStrings.split('|').map((s) => s.trim()).filter(Boolean);
  } else if (!Array.isArray(options.linkIgnoreStrings)) {
    options.linkIgnoreStrings = [];
  }

  if (typeof options.embeddedContentWarning === 'string' && options.embeddedContentWarning.trim().length > 0) {
    options.checks = options.checks || {};
    options.checks.EMBED_CUSTOM = { sources: options.embeddedContentWarning };
  }

  if (Array.isArray(options.ignoreTests)) {
    options.checks = options.checks || {};
    options.ignoreTests.forEach((key) => {
      if (typeof key === 'string' && key.length > 0) {
        options.checks[key] = false;
      }
    });
  }

  if (options.profile === 'dev' && options.devAssertiveness) {
    options.alertMode = options.devAssertiveness;
  }

  options.ignoreByTest = {
    HEADING_EMPTY: 'h1:has([data-rich-text-placeholder]), .is-selected',
    LABELS_MISSING_LABEL: 'block-editor-rich-text__editable',
  }
}

// Build the library's CSA dev/content split configuration. See
// editoria11y-wp.js for the full rationale; this is the in-editor copy.
//
// The ONLY behavioral difference from the front-end shim is `editors`:
// inside the block / Classic / TinyMCE editor it is true, so page-level
// checks (HEADING_MISSING_ONE / HEADING_FIRST / META_*) are suppressed —
// the editor scans one piece of content, not a whole rendered page. The
// devChecks map below must otherwise stay byte-aligned with the
// front-end copy and Drupal's editoria11yOptions.js; the parity guard in
// tests/js/unit/devchecks-parity.test.js enforces that.
//
// `export`ed for the Jest unit suite; the export lives inside the
// premium markers so the free build strips the whole function.



// Translate user-configured H2/H3/H4 selectors (live_h2/h3/h4 in PHP
// storage; liveH2/H3/H4 in the static config emit) into the library's
// `editorHeadingLevel` / `initialHeadingLevel` shape:
//
//   [{ selector: '...', previousHeading: <int> }, ...]
//
// `previousHeading` is the level the user is *under* (so a body field
// with an H2 floor has previousHeading=1, etc.). The bundled library
// uses this to detect skipped levels inside live editor content.
//
// Returns the array; caller decides whether to assign to
// `editorHeadingLevel` (block editor) or merge with hardcoded defaults.
function ed11yBuildEditorHeadingLevel(options) {
  const out = [];
  if (typeof options.liveH2 === 'string' && options.liveH2.trim().length > 0) {
    out.push({ selector: options.liveH2.trim(), previousHeading: 1 });
  }
  if (typeof options.liveH3 === 'string' && options.liveH3.trim().length > 0) {
    out.push({ selector: options.liveH3.trim(), previousHeading: 2 });
  }
  if (typeof options.liveH4 === 'string' && options.liveH4.trim().length > 0) {
    out.push({ selector: options.liveH4.trim(), previousHeading: 3 });
  }
  return out;
}

const ed11yInit = {};

ed11yInit.varDiv = document.getElementById('editoria11y-init');
if (ed11yInit.varDiv) {
  // Only run if initiation JSON is available.
  ed11yInit.options = JSON.parse(ed11yInit.varDiv.innerHTML);
  // Start the fetch immediately on script load. Both init paths (block /
  // classic) await this promise inside getOptions() before doing the
  // in-place option mutations (RegExp conversion etc.) — so by the time
  // firstCheck() constructs Ed11y, the merged options are ready.
  ed11yInit.staticConfigReady = ed11yLoadStaticConfig(ed11yInit.options.config_url);
  ed11yInit.ed11yReadyCount = 0;
  ed11yInit.editorType = false; // 'forbidden' | 'block' | 'mce'
  // Prevent multiple inits in modules that re-trigger the document context.
  ed11yInit.once = false;
  ed11yInit.noRun = '.editor-styles-wrapper > .is-root-container.wp-site-blocks, .edit-site-visual-editor__editor-canvas';
  ed11yInit.editRoot = '.editor-styles-wrapper > .is-root-container:not(.wp-site-blocks)';
  ed11yInit.activeIframe = null; // The block-editor iframe currently being scanned, or null when running on inline canvas.

  ed11yInit.syncDismissals = function () {
    let postData = async function (action, data) {
      fetch(wpApiSettings.root + 'ed11y/v1/' + action, {
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

    let sendDismissal = function (detail) {
      if (!ed11yInit.ed11yCanSync) {
        return;
      }
      if (ed11yInit.editorType === 'mce') {
        if (!ed11yInit.options.title) {
          const title = document.querySelector('#title');
          ed11yInit.title = 'New content';
          if (title && title?.value?.length > 0) {
            ed11yInit.title = title.value;
          }
        }
      }
      if (detail) {
        let data = {
          page_url: State.option.currentPage,
          entity_type: ed11yInit.entity_type,
          page_total: UI.totalCount,
          page_title: ed11yInit.title,
          result_key: detail.dismissTest,
          element_id: detail.dismissKey,
          dismissal_status: detail.dismissAction,
          post_id: ed11yInit.options.post_id ? ed11yInit.options.post_id : 0,
        };
        postData('dismiss', data);
      }
    };
    document.addEventListener('ed11yDismissalUpdate', function (e) {
      sendDismissal(e.detail);
    }, false);

  };


  // Async because getOptions has to await the static-config fetch before
  // running the in-place option mutations below (linkStringsNewWindows →
  // RegExp etc.). Both init paths (block / classic) await it.
  ed11yInit.getOptions = async function () {
    // Merge the static config blob into options FIRST so the downstream
    // RegExp / option-flag conversions transform the merged value instead of
    // being overwritten by it.
    ed11yApplyStaticConfig(
      await ed11yInit.staticConfigReady,
      ed11yInit.options
    );

    // Static config → library option translations (string→RegExp,
    // CSV→array, embeddedContentWarning→checks.EMBED_CUSTOM,
    // ignoreTests→checks[KEY]=false, dev-profile alertMode swap).
    ed11yApplyOptionTranslations(ed11yInit.options);

    // CSA: build splitConfiguration + enable dev/contrast/readability
    // plugins when the per-page blob set profile. No-op otherwise.
    

    // Pin the merged dual-language dictionary so the library uses it during
    // preProcessOptions instead of resetting Lang to its built-in English
    // pack (it calls Lang.addI18n(State.option.lang.strings) on construction
    // whenever options.lang is set). firstCheck() constructs Ed11y after this.
    ed11yInit.options.lang = ed11yLang;

    ed11yInit.options['inlineAlerts'] = false;
    // The editor canvas runs at full speed: shadow-DOM detection costs are
    // not worth it inside the live edit view, regardless of what the
    // frontend setting was. Override after the translations layer.
    ed11yInit.options.autoDetectShadowComponents = false;
    // Layer the editor's own no-run selectors on top of whatever the
    // admin configured for the frontend.
    const adminPreventChecking = ed11yInit.options['preventCheckingIfPresent'];
    ed11yInit.options['preventCheckingIfPresent'] = adminPreventChecking
      ? `${adminPreventChecking}, ${ed11yInit.noRun}, .block-editor-block-preview__content-iframe`
      : `${ed11yInit.noRun}, .block-editor-block-preview__content-iframe`;

    //ed11yInit.options['ignoreByKey'] = { img: '' }; Restore default ignores.
    ed11yInit.options['ignoreByKey'] = {
      table: '.is-selected.wp-block-table table, [role="presentation"]',
    };
    ed11yInit.options['headingsOnlyFromCheckRoots'] = true;
    ed11yInit.options['ignoreAriaOnElements'] = 'h1,h2,h3,h4,h5,h6,.wp-element-button,.block-editor-rich-text__editable,.wp-block-table';
    ed11yInit.options['altPlaceholder'] = 'This image has an empty alt attribute;';

    // WordPress does not render empty post titles, so we don't need to flag them.

    ed11yInit.options['showResults'] = true;
    ed11yInit.options['buttonZIndex'] = 99999;
    // alertMode in the editor is gated by the user-preference `liveCheck`
    // setting (per-user remembered state, not in the static config). The
    // dev-profile swap from `ed11yApplyOptionTranslations()` lands first;
    // this branch overrides it because the editor decides alertMode on
    // its own UX rules. (If a CSA dev wants their dev_assertiveness to
    // stick inside the editor too, that is a future enhancement.)
    if (ed11yInit.options['liveCheck'] && ed11yInit.options['liveCheck'] === 'errors') {
      ed11yInit.options['alertMode'] = 'userPreference';
    } else if (ed11yInit.options['liveCheck'] && ed11yInit.options['liveCheck'] === 'minimized') {
      ed11yInit.options['alertMode'] = 'minimized';
    } else {
      ed11yInit.options['alertMode'] = 'active';
    }
    if (!ed11yInit.ed11yCanSync) {
      ed11yInit.options['syncedDismissals'] = false;
      ed11yInit.options['allowOK'] = false;
    }
    // Ignore elementor-hidden content.
    ed11yInit.options['containerIgnore'] += ', body.elementor-editor-active .block-editor-block-list__layout *';
  };

  ed11yInit.shutMenusOnPop = function () {
    // Single-outer-copy: when a tip opens, just clear the block selection so
    // Gutenberg's floating toolbar doesn't sit on top of the tip. No worker bridge.
    ed11yInit.ed11yShutMenu = () => {
      if (UI.openTip.button) {
        // eslint-disable-next-line no-undef
        wp.data.dispatch('core/block-editor').clearSelectedBlock();
      }
    };
    document.addEventListener('ed11yPop', function () {
      window.setTimeout(() => {
        ed11yInit.ed11yShutMenu();
      }, 1000);
    });
    document.addEventListener('ed11yPop', (e) => {
      const alreadyDecorated = e.detail.tip.dataset.alreadyDecorated;
      if (e.detail.result.element.matches('img') && !alreadyDecorated) {
        const transferFocus = e.detail.tip.shadowRoot.querySelector('.ed11y-transfer-focus');
        transferFocus?.parentNode.style.setProperty('display', 'none');
      }
      e.detail.tip.dataset.alreadyDecorated = 'true';
    });
  };

  ed11yInit.firstCheck = function () {
    if (!ed11yInit.once) {
      window.setTimeout(() => {
        ed11yInit.once = true;
        new Ed11y(ed11yInit.options); // eslint-disable-line no-new
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
      }, 0); // Defer to ensure this runs after any pending UI updates from the editor.
    }
  };

  ed11yInit.buttonBlockTest = function () {

    ed11yInit.options['customTests'] = Number.parseInt(ed11yInit.options['customTests']) + 1;

    document.addEventListener('ed11yRunCustomTests', function () {

      findElements('wpButtonBlock', '.wp-element-button:not(.is-selected .wp-element-button)');
      elements.wpButtonBlock?.forEach((el) => {
        // Straight copy of link test checks as of library 2.2.13
        // Todo: not needed if the library exposes a parameter for link selector.
        // @todo verify: computeText is not exported in 3.x. Falling back to textContent for now.
        let linkText = (el.textContent || '').trim();
        let img = el.querySelectorAll('img');
        let hasImg = img.length > 0;
        let isDocument = el.matches(State.option.documentLinks);

        if (el?.getAttribute('target') === '_blank') {
          // Nothing was stripped AND we weren't warned.
          if (
            !(
              (State.option.linkIgnoreSelector &&
                el?.querySelector(State.option.linkIgnoreSelector))
              || linkText.toLowerCase().match(State.option.linkStringsNewWindows)
            )
          ) {
            let dismissKey = createDismissalKey(linkText);
            State.results.push({
              element: el,
              test: 'linkNewWindow',
              content: Lang.sprintf('LINK_NEW_WINDOW') || '<p>Link opens in a new window.</p>', // @todo verify message key.
              position: 'beforebegin',
              dismissalKey: dismissKey,
            });
          }
        }

        // Todo: add test for title === textContent. Don't use computedText().

        // Tests to see if this link is empty
        if (
          linkText.replace(/"|'|\?|\.|-|\s+/g, '').length === 0 &&
          !(State.option.linkIgnoreSelector &&
            el.querySelector(State.option.linkIgnoreSelector)
          )
        ) {
          // Link with no text at all.
          if (hasImg === false) {
            State.results.push({
              element: el,
              test: 'linkNoText',
              content: Lang.sprintf('LINK_NO_TEXT') || '<p>Link has no text.</p>', // @todo verify message key.
              position: 'beforebegin',
              dismissalKey: false,
            });
          } else {
            State.results.push({
              element: el,
              test: 'altEmptyLinked',
              content: Lang.sprintf('LINK_IMAGE_NO_ALT_TEXT') || '<p>Linked image is missing alt text.</p>', // @todo verify message key.
              position: 'beforebegin',
              dismissalKey: false,
            });
          }
        }
        else {
          let linkTextCheck = function (textContent) {
            // Checks if link text is not descriptive.
            let linkStrippedText = textContent.toLowerCase();
            // Create version of text without "open in new window" warnings.

            if (State.option.linkStringsNewWindows) {
              // don't strip on the default, which is loose.
              linkStrippedText = linkStrippedText.replace(State.option.linkIgnoreStrings, '');
            }
            if (State.option.linkIgnoreStrings) {
              linkStrippedText = linkStrippedText.replace(State.option.linkIgnoreStrings, '');
            }
            if (linkStrippedText.replace(/"|'|\?|\.|-|\s+/g, '').length === 0) {
              // No Text because of stripping out ignoreStrings.
              return 'generic';
            }

            // todo later: use regex to find any three-letter TLD followed by a slash.
            // todo later: parameterize TLD list
            // @todo verify: linksUrls / linksMeaningless were on Ed11y.M in 2.x.
            // In 3.x these are accessed via Lang internal strings; expose via State.option overrides if needed.
            let linksUrls = State.option.linksUrls || [];
            let linksMeaningless = State.option.linksMeaningless || /^\s*$/;
            let hit = 'none';

            if (linkStrippedText.replace(linksMeaningless, '').length === 0) {
              // If no partial words were found, then check for total words.
              hit = 'generic';
            }
            else {
              for (let i = 0; i < linksUrls.length; i++) {
                if (textContent.indexOf(linksUrls[i]) > -1) {
                  hit = 'url';
                  break;
                }
              }
            }
            return hit;
          };
          let textCheck = linkTextCheck(linkText);
          if (textCheck !== 'none') {
            let error = false;
            if (!hasImg && textCheck === 'url') {
              // Images test will pick this up.
            }
            if (textCheck === 'generic') {
              error = 'linkTextIsGeneric';
              if (linkText.length < 4) {
                // Reinsert ignored link strings.
                // @todo verify: was Ed11y.computeText(el, 0) — not exported in 3.x.
                linkText = (el.textContent || '').trim();
              }
            }
            if (error) {
              State.results.push({
                element: el,
                test: error,
                content: '<p>' + sanitizeHTML(linkText) + '</p>', // @todo verify message key for error.
                position: 'beforebegin',
                dismissalKey: createDismissalKey(linkText),
              });
            }
          }
        }
        // Warning: Find all PDFs.
        if (isDocument) {
          let dismissKey = createDismissalKey(el?.getAttribute('href'));
          State.results.push(
            {
              element: el,
              test: 'linkDocument',
              content: Lang.sprintf('LINK_DOCUMENT') || '<p>Linked document.</p>', // @todo verify message key.
              position: 'beforebegin',
              dismissalKey: dismissKey,
            });
        }
      });

      if (!ed11yInit.title) {
        findElements('pageTitle', 'h1');
        ed11yInit.title = 'New content';
        if (elements['pageTitle'][0] && elements['pageTitle'][0].textContent.length > 0) {
          ed11yInit.title = elements['pageTitle'][0].textContent;
        }
      }

      // Admin-defined custom rules from the CSA static config flow
      // through `options.customRules` directly (camelCase shape produced
      // server-side by ApiConfig::transform_custom_rules); the bundled
      // library's prepareCustomRuleset() / checkCustomRuleset() runs them.
      // No manual loop needed here.

      let allDone = new CustomEvent('ed11yResume');
      document.dispatchEvent(allDone);
    });
  };

  // Returns the visual block-editor iframe (if any), excluding block-preview iframes.
  ed11yInit.findBlockIframe = function () {
    return document.querySelector(
      '[class*="-visual-editor"] iframe:not(.block-editor-block-preview__content-iframe)'
    );
  };

  // Polls until the chosen canvas (iframed or inline) is loaded enough to scan.
  // Mirrors the iframesReady pattern used by the classic-editor MCE init.
  ed11yInit.canvasReady = function (iframe, callback, retries = 0) {
    let ready;
    if (iframe) {
      const doc = iframe.contentWindow?.document;
      ready = !!doc &&
        doc.readyState === 'complete' &&
        !!doc.body &&
        !!doc.querySelector(ed11yInit.editRoot);
    } else {
      // Inline canvas.
      ready = !!document.querySelector('#editor ' + ed11yInit.editRoot);
    }
    if (ready) {
      callback();
    } else if (retries < 60) {
      window.setTimeout(() => ed11yInit.canvasReady(iframe, callback, retries + 1), 1000);
    } else {
      console.log('Editoria11y: block editor canvas never reported ready.');
    }
  };

  ed11yInit.ed11yBlockOuterInit = function () {
    const iframe = ed11yInit.findBlockIframe();

    ed11yInit.canvasReady(iframe, async () => {
      await ed11yInit.getOptions();

      // Shared block-editor overrides. The two hardcoded entries cover the
      // post-title wrapper and the root-container floor; admin-configured
      // liveH2/H3/H4 selectors are appended after, so a custom-block plugin
      // that nests further down can pin the inner H-level without
      // disturbing the post-title detection above.
      ed11yInit.options.editorHeadingLevel = [
        { selector: '.editor-visual-editor__post-title-wrapper', previousHeading: 0 },
        { selector: '.editor-styles-wrapper > .is-root-container', previousHeading: 1 },
        ...ed11yBuildEditorHeadingLevel(ed11yInit.options),
      ];
      // The editor canvas always uses 'checkRoots' watch mode regardless
      // of the admin's frontend setting — the admin-configured value
      // already overrode this in `getOptions`, but the editor's lifecycle
      // (mount, swap, unmount) requires the contained tree-walk shape.
      ed11yInit.options.watchForChanges = 'checkRoots';

      if (iframe) {
        // Iframed canvas (default in WP 6.3+): library scans the iframe body via fixedRoots.
        const body = iframe.contentWindow.document.body;
        ed11yInit.options.fixedRoots = [body];
        ed11yInit.options.framePositioners = [iframe];
        ed11yInit.options.editableContent = [body];
        ed11yInit.options.ignoreAllIfAbsent = false;
      } else {
        // Inline canvas (older WP, sites that opt out of the iframed editor):
        // library scans the outer document directly. Library reads
        // `checkRoot` (singular); the plural form was a parity bug.
        ed11yInit.options.checkRoot =
          '.editor-visual-editor__post-title-wrapper:not(:has([data-rich-text-placeholder])), #editor .is-root-container:not(.wp-site-blocks)';
        ed11yInit.options.editableContent = '.interface-interface-skeleton__content';
        ed11yInit.options.ignoreAllIfAbsent = '#editor .editor-styles-wrapper';
      }

      // Hide tip elements while the user is in the Code Editor (textarea-only) view.
      const hideOnCode = document.createElement('style');
      hideOnCode.setAttribute('hidden', 'true');
      hideOnCode.textContent = 'body:has(.editor-text-editor) .ed11y-element { display: none; }';
      document.body.appendChild(hideOnCode);

      // Custom tests must register their listener before the first checkAll runs.
      ed11yInit.buttonBlockTest();

      ed11yInit.shutMenusOnPop();
      ed11yInit.firstCheck();
      ed11yInit.syncDismissals();
      ed11yInit.activeIframe = iframe || null;

      // Establish the baseline state upon load
      /*
      let currentSelectedId = wp?.data?.select('core/block-editor')?.getSelectedBlockClientId();

      if (currentSelectedId) {
        // Listen to every update emitted by the global state container
        const unsubscribe = wp.data.subscribe(() => {
          const nextSelectedId = wp?.data?.select('core/block-editor')?.getSelectedBlockClientId();

          // Check if the current ID differs from the previous tick
          if (currentSelectedId !== nextSelectedId) {
            console.log('Selection changed to block:', nextSelectedId);

            // Handle block deselect cases (returns null if no block is highlighted)
            if (nextSelectedId) {
              const blockDetails = wp.data.select('core/block-editor').getBlock(nextSelectedId);
              console.log('Selected block details:', blockDetails);
            }

            // Sync baseline state
            currentSelectedId = nextSelectedId;
          }
        });
      }*/

      // Watch for canvas swaps: Visual ↔ Code editor, post navigation that
      // remounts without a full reload, or the iframe being added/removed when
      // an editor setting changes. setFixedRoots() handles the in-place update.
      ed11yInit.canvasObserver = new MutationObserver(() => {
        const next = ed11yInit.findBlockIframe();
        if (next !== ed11yInit.activeIframe) {
          ed11yInit.handleCanvasSwap(next);
        }
      });
      ed11yInit.canvasObserver.observe(document.body, { childList: true, subtree: true });
    });
  };

  ed11yInit.handleCanvasSwap = function (newIframe) {
    if (newIframe) {
      ed11yInit.canvasReady(newIframe, () => {
        const body = newIframe.contentWindow.document.body;
        setFixedRoots([body], [newIframe], [body]);
        ed11yInit.activeIframe = newIframe;
      });
    } else {
      // iframe removed: caller is now using the inline canvas. Drop fixedRoots so
      // the library re-scans the outer document via checkRoots.
      setFixedRoots([], [], '.interface-interface-skeleton__content');
      ed11yInit.activeIframe = null;
    }
  };


  ed11yInit.ed11yOuterClassicInit = function () {

    // The library reads `containerIgnore` (not `ignoreElements`); use the
    // shim's library-shape value if present, fall back to a no-match
    // placeholder so the `:not(...)` selector is still valid CSS.
    const ignoreSel = ed11yInit.options['containerIgnore'] || ed11yInit.options['ignoreElements'] || ':not(*)';
    const iframes = document.querySelectorAll(`.mce-edit-area iframe:not(${ignoreSel})`);

    let readyCount = 0;
    const iframesReady = async function () {
      const ready = Array.from(iframes).every((frame) => typeof frame.contentWindow?.document === 'object');
      if (ready) {

        await ed11yInit.getOptions();
        ed11yInit.options['ignoreAllIfAbsent'] = false;
        ed11yInit.options['watchForChanges'] = false;
        ed11yInit.options['editorHeadingLevel'] = [];
        ed11yInit.options['headingsOnlyFromCheckRoots'] = true;
        ed11yInit.options['buttonZIndex'] = 998;
        ed11yInit.options['ignoreByKey']['a'] = '[aria-hidden][tabindex], .mce-item-anchor';

        // Todo: preventChecking would be better than ignore all, but fails to restore at the moment.
        // ed11yInit.options['preventCheckingIfPresent'] = '#content-html[aria-pressed="true"]';
        ed11yInit.options['ignoreAllIfPresent'] = '#content-html[aria-pressed="true"]';

        const hideOnCode = document.createElement('style');
        hideOnCode.setAttribute('hidden', 'true');
        hideOnCode.textContent = 'div.mce-toolbar-grp {z-index:999;} body:has(#content-html[aria-pressed="true"]) .ed11y-element {display: none;}';
        document.body.appendChild(hideOnCode);

        ed11yInit.options.autoDetectShadowComponents = false;
        ed11yInit.options.watchForChanges = 'checkRoots';
        // MCE-specific defaults; admin-configured liveH2/H3/H4 selectors
        // append after so the wildcard `selector: '*'` floor doesn't
        // override them (library walks the array in order).
        ed11yInit.options.editorHeadingLevel = [
          // need to set this up per frame
          {
            selector: '.mce-content-body',
            previousHeading: 1,
          },
          ...ed11yBuildEditorHeadingLevel(ed11yInit.options),
          {
            selector: '*',
            previousHeading: 0,
          },
        ];
        // Library reads `checkRoot` (singular); the plural form was a parity bug.
        ed11yInit.options['checkRoot'] = '#tinymce, #wp-content-editor-tools';
        ed11yInit.options.fixedRoots = [];
        ed11yInit.options.framePositioners = [];
        ed11yInit.options.editableContent = [];

        // Listen for event
        document.addEventListener('ed11yPop', e => {
          // Use event details to get the marked element
          const cantFocus = e.detail.tip.shadowRoot.querySelector('.ed11y-transfer-focus');
          if (cantFocus) {
            cantFocus.remove();
          }
        });

        iframes.forEach(iframe => {
          ed11yInit.options.fixedRoots.push(iframe.contentWindow.document.body);
          ed11yInit.options.framePositioners.push(iframe);
          ed11yInit.options.editableContent.push(iframe.contentWindow.document.body);
          const head = iframe.contentWindow.document.getElementsByTagName('head')[0];
          const script = iframe.contentWindow.document.createElement('script');
          script.src = ed11yInit.options.mceInnerJS;
          script.type = 'text/javascript';
          head.appendChild(script);
        });

        let once = false;
        // This is exported to global for use by the MCE iframe.
        window.startMCEEd11y = function () {
          if (once) {
            return;
          }
          once = true;
          //ed11yInit.options.fixedRoots = [root];
          ed11yInit.firstCheck();
          ed11yInit.syncDismissals();
          // Expose 3.x singletons + helpers for the MCE iframe (mce-inner.js runs as a regular script
          // inside the iframe and reaches into parent.Ed11y for state and refresh).
          // @todo retire alongside mce-inner.js once upstream cross-doc listener attachment is verified for MCE.
          window.Ed11y = Ed11y;
          window.Ed11y.UI = UI;
          window.Ed11y.State = State;
          window.Ed11y.refresh = refresh;
        };

      } else if (readyCount < 60) {
        readyCount++;
        window.setTimeout(iframesReady, 1000);
      }
    };
    window.setTimeout(() => {
      iframesReady();
    }, 100);

  };

  // Look to see if a supported editor has loaded.
  // Possible todo: add checks/markup for other common editors.
  ed11yInit.findCompatibleEditor = function () {
    if (ed11yInit.editorType) {
      // Do nothing.
    } else if (document.querySelector(ed11yInit.noRun)) {
      ed11yInit.editorType = 'forbidden';
    } else if (
      // Block editor: either iframed canvas (WP 6.3+ default) or inline canvas
      // (older WP, opt-outs via block_editor_settings_all, certain post types).
      ed11yInit.findBlockIframe() ||
      document.querySelector('#editor ' + ed11yInit.editRoot)
    ) {
      ed11yInit.editorType = 'block';
      ed11yInit.ed11yCanSync = !window.location.href.includes('-new.php');
      ed11yInit.ed11yBlockOuterInit();
    } else if (document.querySelector('.mce-edit-area iframe') && window.innerWidth > 600) {
      ed11yInit.editorType = 'mce';
      ed11yInit.ed11yCanSync = !window.location.href.includes('-new.php');
      ed11yInit.ed11yOuterClassicInit();
    } else if (ed11yInit.ed11yReadyCount < 60) {
      window.setTimeout(function () {
        ed11yInit.ed11yReadyCount++;
        ed11yInit.findCompatibleEditor();
      }, 1000);
    } else {
      console.log('Editoria11y called on page, but no compatible editor found');
    }
  };

  // Scan page for compatible editors once page has loaded.
  window.addEventListener('load', () => {
    window.setTimeout(() => {
      if (!ed11yInit.editorType) {
        ed11yInit.findCompatibleEditor();
      }
    }, 0);
  });
  // Belt & suspenders if load never fires.
  window.setTimeout(() => {
    if (!ed11yInit.editorType) {
      ed11yInit.findCompatibleEditor();
    }
  }, 2500);
}
