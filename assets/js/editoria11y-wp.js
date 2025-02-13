let ed11yOptions = {};
let ed11yResetID = false;

// Create callback to see if document is ready.
function ed11yReady(fn) {
  if (document.readyState != 'loading') {
    fn();
  } else if (document.addEventListener) {
    document.addEventListener('DOMContentLoaded', fn);
  } else {
    document.attachEvent('onreadystatechange', function () {
      if (document.readyState != 'loading')
        fn();
    });
  }
}

function ed11ySync() {
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

  let extractResults = function () {
    let results = {};
    let dismissals = [];
    let total = 0;
    Ed11y.results.forEach(result => {
      /* let test = Ed11y.results[i][1];
						  let dismissKey = Ed11y.results[i][4]; */
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

  let url = Ed11y.options.currentPage;
  url = url.length > 190 ? url.substring(0, 189) : url;
  let queryString = window.location.search;
  let urlParams = new URLSearchParams(queryString);
  ed11yResetID = urlParams.get('ed1ref');

  let sendResults = function () {
    window.setTimeout(function () {
        if ( ed11yOptions.post_id && document.getElementsByClassName('post-password-form').length > 0 ) {
            // Don't sync "0 results" if the post has been replaced by a login form.
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
		postData('result', data);
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
			pid: ed11yResetID ? parseInt( ed11yResetID ) : -1,
            post_id: ed11yOptions.post_id ? ed11yOptions.post_id : 0,
		};
		postData('result', data);
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
        page_url: Ed11y.options.currentPage,
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

const ed11yCustomTests = function() {
    document.addEventListener('ed11yRunCustomTests', function() {

        // 1. Write your custom test.
        // This can be anything you want.
        //
        // This example uses Ed11y.findElements(),
        // which respects checkRoots and ignoreElements,
        // to find links with a particular string in their URL.
        Ed11y.findElements('emptyWpButton','a.wp-element-button:not([href], [tabindex])');

        // 2. Create a message for your tooltip.
        // You'll need a title and some contents,
        // as a value for the key you create for your test.
        //
        // Be sure to use the same key as the test name below.

        Ed11y.M.emptyWpButton = {
            title: 'Empty Wordpress Button',
            tip: () =>
                '<p>The button style visually indicates a link, but this button is not linked.</p>',
        };

        // 3. Push each item you want flagged to Ed11y.results.
        //
        // You must provide:
        //   el: The element
        //   test: A key for your test
        //   content: The tip you created above.
        //   position: "beforebegin" for images and links,
        //             "afterbegin" for paragraphs.
        //   dismissalKey: false for errors,
        //             a unique string for manual checks.
        Ed11y.elements.emptyWpButton?.forEach((el) => {
            Ed11y.results.push({
                element: el,
                test: 'emptyWpButton',
                content: Ed11y.M.emptyWpButton.tip(),
                position: 'beforebegin',
                dismissalKey: false,
            });
        });

        // 4. When you are done with all your custom tests,
        // dispatch an "ed11yResume" event:
        let allDone = new CustomEvent('ed11yResume');
        document.dispatchEvent(allDone);
    });
};

const ed11yInit = function() {
  let ed11yOpts = document.getElementById('editoria11y-init');
  if (!!ed11yOpts && window.location.href.indexOf('elementor-preview') === -1) {
    ed11yOptions = JSON.parse(ed11yOpts.innerHTML);
    ed11yOptions.customTests = 1;
    ed11yOptions.panelNoCover = '#edac-highlight-panel'; // Accessibility Checker module

    ed11yOptions.linkStringsNewWindows = ed11yOptions.linkStringsNewWindows ? new RegExp(ed11yOptions.linkStringsNewWindows, 'g') : /window|\stab|download/g;
    if (ed11yOptions.title.length < 3) {
      ed11yOptions.title = document.title;
    }
    ed11yOptions['checkVisible'] = ed11yOptions['checkVisible'] === 'true';
    // When triggered by the in-editor "issues" link, force assertive.
    if (window.location.href.indexOf('preview=true') > -1) {
      ed11yOptions['alertMode'] = 'assertive';
    }
    if (window.location.href.indexOf('ed1ref') > -1) {
      ed11yOptions['alertMode'] = 'assertive';
      ed11yOptions['showDismissed'] = true;
    }
    ed11yOptions.cssUrls = [ed11yOptions.cssLocation];


    const editLink = document.querySelector('#wp-admin-bar-edit a');
    // todo: this is not detected due to race condition.
    const elementorLink = document.querySelector('#wp-admin-bar-elementor_edit_page a');
    if (editLink || elementorLink) {
      const editIcon = document.createElement('span');
      editIcon.classList.add('ed11y-custom-edit-icon');
      editIcon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free 6.7.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path d="M441 58.9L453.1 71c9.4 9.4 9.4 24.6 0 33.9L424 134.1 377.9 88 407 58.9c9.4-9.4 24.6-9.4 33.9 0zM209.8 256.2L344 121.9 390.1 168 255.8 302.2c-2.9 2.9-6.5 5-10.4 6.1l-58.5 16.7 16.7-58.5c1.1-3.9 3.2-7.5 6.1-10.4zM373.1 25L175.8 222.2c-8.7 8.7-15 19.4-18.3 31.1l-28.6 100c-2.4 8.4-.1 17.4 6.1 23.6s15.2 8.5 23.6 6.1l100-28.6c11.8-3.4 22.5-9.7 31.1-18.3L487 138.9c28.1-28.1 28.1-73.7 0-101.8L474.9 25C446.8-3.1 401.2-3.1 373.1 25zM88 64C39.4 64 0 103.4 0 152L0 424c0 48.6 39.4 88 88 88l272 0c48.6 0 88-39.4 88-88l0-112c0-13.3-10.7-24-24-24s-24 10.7-24 24l0 112c0 22.1-17.9 40-40 40L88 464c-22.1 0-40-17.9-40-40l0-272c0-22.1 17.9-40 40-40l112 0c13.3 0 24-10.7 24-24s-10.7-24-24-24L88 64z"/></svg>';
      const reLink = function(link) {
        const linkButton = document.createElement('a');
        linkButton.href = link.href;
        linkButton.textContent = link.textContent;
        linkButton.prepend(editIcon.cloneNode(true));
        return linkButton;
      }
      const editLinks = document.createElement('div');
      if (editLink) {
        editLinks.appendChild(reLink(editLink));
      }
      if (elementorLink) {
        editLinks.appendChild(reLink(elementorLink));
      }
      ed11yOptions.editLinks = editLinks;

      // todo: Add param for listener to hide edit links on certain widgets.
      /*if (!!drupalSettings.editoria11y.hide_edit_links) {
        document.addEventListener('ed11yPop', e => {
          if (e.detail.result.element.closest(drupalSettings.editoria11y.hide_edit_links)) {
            e.detail.tip.shadowRoot.querySelector('.ed11y-custom-edit-links')?.setAttribute('hidden', '');
          }
        });
      }*/
    }

    const ed11y = new Ed11y(ed11yOptions); // eslint-disable-line
    ed11yCustomTests();
    ed11ySync();
  }
}

// Call callback, init Editoria11y.
ed11yReady(
  function () {
      window.setTimeout(()=>{
        ed11yInit();
      },0);
    }
);
