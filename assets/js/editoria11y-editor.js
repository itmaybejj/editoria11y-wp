

/*let ed11yInit.options = ed11yInit ? ed11yInit.options : false;
let workerURL = ed11yInit ? ed11yInit.innerWorker : false;
if (!ed11yInit.options) {
  const ed11yVarPile = document.getElementById("ed11yVarPile");
  const parsed  = JSON.parse(ed11yVarPile.textContent);
  ed11yInit.options = parsed.options;
  workerURL = parsed.worker;
}*/
const ed11yInit = {};
// eslint-disable-next-line no-undef
ed11yInit.options = ed11yVars.options;
ed11yInit.ed11yReadyCount = 0;
ed11yInit.editorType = false; // onPage, inIframe, outsideIframe
// Prevent multiple inits in modules that re-trigger the document context.
ed11yInit.once = false;
ed11yInit.noRun = '.editor-styles-wrapper > .is-root-container.wp-site-blocks, .edit-site-visual-editor__editor-canvas';
ed11yInit.editRoot = '.editor-styles-wrapper > .is-root-container:not(.wp-site-blocks)';
ed11yInit.scrollRoot = false;


ed11yInit.getOptions = function() {
  // Initiate Ed11y with admin options.

  ed11yInit.options.linkStringsNewWindows = ed11yInit.options.linkStringsNewWindows ?
    new RegExp(ed11yInit.options.linkStringsNewWindows, 'g') :
    /window|\stab|download/g;
  ed11yInit.options['inlineAlerts'] = false;
  ed11yInit.options.checkRoots = ed11yInit.editRoot;
  ed11yInit.options['preventCheckingIfPresent'] = ed11yInit.noRun;
  ed11yInit.options['ignoreAllIfAbsent'] = ed11yInit.editRoot;
  if (ed11yInit.scrollRoot) {
    ed11yInit.options['editableContent'] = ed11yInit.scrollRoot;
  }
  // todo heading level
  ed11yInit.options['ignoreByKey'] = { img: '' };
  //ed11yInit.options['ignoreByKey']['h'] = '.wp-block-post-title';
  ed11yInit.options['altPlaceholder'] = 'This image has an empty alt attribute;';

  // WordPress does not render empty post titles, so we don't need to flag them.
  ed11yInit.options['originalIgnore'] = ed11yInit.options['ignoreElements'];

  ed11yInit.options['showResults'] = true;
  ed11yInit.options['alertMode'] = 'active';
  ed11yInit.options['editorHeadingLevel'] = [{
    selector: '.editor-styles-wrapper> .is-root-container',
    previousHeading: 1,
  }];
};

// Create callback to see if document is ready.
ed11yInit.ed11yReady = (fn) => {
  if (document.readyState !== 'loading') {
    fn();
  } else if (document.addEventListener) {
    document.addEventListener('DOMContentLoaded', fn);
  } else {
    document.attachEvent('onreadystatechange', function () {
      if (document.readyState !== 'loading')
        fn();
    });
  }
}

ed11yInit.firstCheck = function() {
  if (!ed11yInit.once) {
    ed11yInit.once = true;
    const ed11y = new Ed11y(ed11yInit.options); // eslint-disable-line
  }
};

ed11yInit.nextCheck = Date.now();
ed11yInit.waiting = false;
ed11yInit.lastText = '';
ed11yInit.recheck = () => {
  // Debouncing to 1x per second.
  let nextRun = ed11yInit.nextCheck + Ed11y.browserLag - Date.now();
  if (nextRun > 0) {
    // Not time to go yet.
    if (!ed11yInit.waiting) {
      // Wait and start debouncing.
      ed11yInit.waiting = true;
      window.setTimeout(ed11yInit.recheck, nextRun);
    }
  } else {
    // Check now.
    ed11yInit.nextCheck = Date.now() + 1000 + Ed11y.browserLag;
    ed11yInit.waiting = false;
    if (ed11yInit.once && Ed11y.panel && Ed11y.roots) {
      window.setTimeout(() => {
        Ed11y.incrementalAlign();
        Ed11y.alignPending = false;
      }, 0 + Ed11y.browserLag);
      window.setTimeout(() => {
        Ed11y.forceFullCheck = true;
        Ed11y.incrementalCheck()
      }, 250 + Ed11y.browserLag);
      window.setTimeout(() => {
        Ed11y.forceFullCheck = true;
        Ed11y.incrementalCheck()
      }, 1250 + Ed11y.browserLag);
    } else {
      console.log('Editoria11y debug: fallback full check');
      Ed11y.checkAll();
    }
  }
}

ed11yInit.ed11yShutMenu = () => {
  if (Ed11y.openTip.button) {
    if (ed11yInit.editorType === 'inIframe') {
      ed11yInit.innerWorker.port.postMessage([true, false]);
    } else {
      wp.data.dispatch('core/block-editor').clearSelectedBlock()
    }
  }
}
document.addEventListener('ed11yPop', function() {
  window.setTimeout(() => {
    ed11yInit.ed11yShutMenu();
  }, 1000);
});

ed11yInit.interaction = false;

ed11yInit.createObserver = function () {
  // Ed11y misses many Gutenberg changes without help.

  // Recheck inner when something was clicked outside the iframe.
  ed11yInit.innerWorker.port.onmessage = (message) => {
    // Something was clicked outside the iframe.
    if (message.data[1]) {
      ed11yInit.recheck();
      ed11yInit.interaction = false;
    }
  }
  ed11yInit.innerWorker.port.start();
  if (ed11yInit.editorType === 'inIframe') {
    return;
  }

  // Listen for events that may modify content without triggering a mutation.
  window.addEventListener('keyup', (e) => {
    if (!e.target.closest('.ed11y-wrapper, [contenteditable="true"]')) {
      // Arrow changes of radio and select controls.
      ed11yInit.interaction = true;
    }
  });
  window.addEventListener('click', (e) => {
    // Click covers mouse, keyboard and touch.
    if (!e.target.closest('.ed11y-wrapper')) {
      ed11yInit.interaction = true;
    }
  });

  // Observe for DOM mutations.

  const ed11yTargetNode = document.querySelector(ed11yInit.scrollRoot);
  const ed11yObserverConfig = { attributeFilter: ['class'], characterData: true, subtree: true };
  const ed11yMutationCallback = (callback) => {
    // Ignore mutations that do not result from user interactions.
    if (callback[0].type !== 'characterData' && ed11yInit.interaction) {
      ed11yInit.recheck();
      ed11yInit.interaction = false;
      // Could get blockID via Web worker to check less often.
      // let newBlockId = wp.data.select( 'core/block-editor' ).getSelectedBlockClientId();
    }
  };
  const ed11yObserver = new MutationObserver(ed11yMutationCallback);
  ed11yObserver.observe(ed11yTargetNode, ed11yObserverConfig)
};

ed11yInit.ed11yOuterInit = function() {

  // Tell iframe if block editor might be up to something.
  ed11yInit.outerWorker = window.SharedWorker ? new SharedWorker(ed11yVars.worker) : false;
  window.addEventListener('keyup', (e) => {
    // Arrow changes of radio and select controls.
    ed11yInit.outerWorker.port.postMessage([false, true])
  });
  window.addEventListener('click', (e) => {
    ed11yInit.outerWorker.port.postMessage([false, true])
  });

  // Clear active block selection when a tip opens to hide floating menup.
  ed11yInit.outerWorker.port.onmessage = (message) => {
    if (message.data[0]) {
      wp.data.dispatch('core/block-editor').clearSelectedBlock();
    }
  }

  ed11yInit.outerWorker.port.onmessageerror = (data) => {
    console.warn(data);
  }
  ed11yInit.outerWorker.port.onerror = (data) => {
    console.warn(data);
  }
  ed11yInit.outerWorker.port.start();
};

/*
// Classic editor watching failed.
// The Tiny MCE iframe has editable elements touching <body>.
// In theory I could bring back the outlines.
// Leaving code in case I get any bright ideas down the road.
const ed11yClassicInsertScripts = function() {
  //"https://editoria11y-wp.ddev.site/wp-content/plugins/editoria11y-wp/assets/lib/editoria11y.min.css"
  const library = document.createElement('script');
  library.src = ed11yInit.options.cssLocation.replace('editoria11y.min.css', 'editoria11y.min.js');
  const editorInit = document.createElement('script');
  const varPile = document.createElement('script');
  varPile.setAttribute('id', 'ed11yVarPile');
  varPile.innerHTML = 'var ed11yInit = ' + JSON.stringify({
    options: ed11yInit.options,
    worker: workerURL,
  });
  editorInit.src = ed11yInit.options.cssLocation.replace('lib/editoria11y.min.css', 'js/editoria11y-editor.js');
  const workerScript = document.createElement('script');
  workerScript.src = ed11yInit.options.cssLocation.replace('lib/editoria11y.min.css', 'js/editoria11y-editor-worker.js');
  const style = document.createElement('link');
  style.rel = 'stylesheet';
  style.href = ed11yInit.options.cssLocation;
  const iframe = document.getElementById('content_ifr');
   // Check if the iframe has loaded
  if (iframe.contentDocument) {
      // Access the iframe's document object
    const iframeDoc = iframe.contentDocument;
    // Insert content into the iframe
    iframeDoc.head.append(library);
    iframeDoc.head.append(varPile);
    iframeDoc.head.append(style);
    iframeDoc.head.append(workerScript);
    iframeDoc.head.append(editorInit);
  } else {
    // Wait for the iframe to load
    iframe.addEventListener('load', () => {
      console.log('later');
      const iframeDoc = iframe.contentDocument;
      iframeDoc.head.append(library);
      iframeDoc.head.append(varPile);
      iframeDoc.head.append(style);
      iframeDoc.head.append(workerScript);
      iframeDoc.head.append(editorInit);
    });
  }
}*/

// Initiate Editoria11y create alert link, initiate content change watcher.
ed11yInit.ed11yPageInit = function () {
  ed11yInit.innerWorker = window.SharedWorker ? new SharedWorker(ed11yVars.worker) : false;
  window.setTimeout(() => {
    ed11yInit.getOptions();
    ed11yInit.firstCheck();
  },1000)
  window.setTimeout(() => {
    ed11yInit.createObserver();
    ed11yInit.recheck();
  }, 2500);
};

// Look to see if Gutenberg has loaded.
// Possible todo: add checks/markup for other common editors.
ed11yInit.findCompatibleEditor = function () {
  if (ed11yInit.editorType) {
    // Do nothing.
  } else if (document.querySelector(ed11yInit.noRun)) {
    ed11yInit.editorType = 'forbidden';
  } else if (document.querySelector('body' + ed11yInit.editRoot)) {
    // inside iFrame
    ed11yInit.editorType = 'inIframe';
    ed11yInit.scrollRoot = 'body';
    ed11yInit.ed11yPageInit();
  } else if (document.querySelector('[class*="-visual-editor"] iframe')) {
    ed11yInit.editorType = 'outsideIframe';
    ed11yInit.ed11yOuterInit();
  } else if ( document.querySelector('#editor .editor-styles-wrapper')) {
    ed11yInit.editorType = 'onPage';
    ed11yInit.editRoot = '#editor .is-root-container, #editor .editor-post-title'; // todo: title in headings panel is always "add title?"
    ed11yInit.scrollRoot = '.interface-interface-skeleton__content';
    ed11yInit.ed11yPageInit();
  } else if (document.getElementById('content_ifr')) {
    ed11yInit.editorType = 'mce';
  } else if (ed11yInit.ed11yReadyCount < 60) {
    window.setTimeout(function () {
      ed11yInit.ed11yReadyCount++;
      ed11yInit.findCompatibleEditor();
    }, 1000);
  } else {
    console.log('Editoria11y: no block editor found');
  }
};

// Call callback, scan page for compatible editors.
ed11yInit.ed11yReady(
  function () {
    ed11yInit.findCompatibleEditor();
  }
);
