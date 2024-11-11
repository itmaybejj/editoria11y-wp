

/*let ed11yInit.options = ed11yInit ? ed11yInit.options : false;
let ed11yWorkerURL = ed11yInit ? ed11yInit.worker : false;
if (!ed11yInit.options) {
  const ed11yVarPile = document.getElementById("ed11yVarPile");
  const parsed  = JSON.parse(ed11yVarPile.textContent);
  ed11yInit.options = parsed.options;
  ed11yWorkerURL = parsed.worker;
}*/
const ed11yInit = {};
// eslint-disable-next-line no-undef
ed11yInit.options = ed11yVars.options;
ed11yInit.ed11yReadyCount = 0;
ed11yInit.editorType = false; // onPage, inIframe, outsideIframe
// Prevent multiple inits in modules that re-trigger the document context.
ed11yInit.ed11yOnce = false;
ed11yInit.ed11yWorker = window.SharedWorker ? new SharedWorker(ed11yVars.worker) : false;
ed11yInit.ed11yNoRun = '.editor-styles-wrapper > .is-root-container.wp-site-blocks, .edit-site-visual-editor__editor-canvas';
ed11yInit.editRoot = '.editor-styles-wrapper > .is-root-container:not(.wp-site-blocks)';
ed11yInit.scrollRoot = false;


ed11yInit.getOptions = function() {
  // Initiate Ed11y with admin options.

  ed11yInit.options.linkStringsNewWindows = ed11yInit.options.linkStringsNewWindows ?
    new RegExp(ed11yInit.options.linkStringsNewWindows, 'g') :
    /window|\stab|download/g;
  ed11yInit.options['inlineAlerts'] = false;
  ed11yInit.options.checkRoots = ed11yInit.editRoot;
  ed11yInit.options['preventCheckingIfPresent'] = ed11yInit.ed11yNoRun;
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
  if (!ed11yInit.ed11yOnce) {
    ed11yInit.ed11yOnce = true;
    const ed11y = new Ed11y(ed11yInit.options); // eslint-disable-line
  }
};

ed11yInit.nextCheck = 0;
ed11yInit.waiting = false;
ed11yInit.recheck = () => {
  // Debouncing to 1x per second.
  if (Date.now() < ed11yInit.nextCheck + 1000 + Ed11y.browserLag) {
    if (!ed11yInit.waiting) {
      ed11yInit.waiting = true;
      window.setTimeout(ed11yInit.recheck, 1000 + Ed11y.browserLag);
    }
  } else {
    ed11yInit.nextCheck = Date.now() + 1000 + Ed11y.browserLag;
    if (Ed11y.openTip.button) {
      // Don't force recheck while a tip is open.
      return;
    }
    if (ed11yInit.ed11yOnce && Ed11y.panel && Ed11y.roots) {
      Ed11y.forceFullCheck = true;
      Ed11y.incrementalAlign();
      Ed11y.alignPending = false;
      window.setTimeout(() => {Ed11y.incrementalCheck() }, 1001 + Ed11y.browserLag);
    } else {
      Ed11y.checkAll();
    }
  }
}

ed11yInit.ed11yShutMenu = () => {
  if (Ed11y.openTip.button) {
    if (ed11yInit.editorType === 'inIframe') {
      ed11yInit.ed11yWorker.port.postMessage([ed11yInit.editorType]);
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

ed11yInit.createObserver = function () {
  // Ed11y misses many Gutenberg changes without help.
  const ed11yTargetNode = document.querySelector(ed11yInit.editRoot);
  // Observe for class changes and typing.
  const ed11yObserverConfig = { attributeFilter: ['class'], characterData: true, subtree: true };
  const ed11yMutationCallback = (callback) => {
    if (callback[0].type !== 'characterData') {
      // Could get blockID via Web worker to check less often.
      // let newBlockId = wp.data.select( 'core/block-editor' ).getSelectedBlockClientId();
      ed11yInit.recheck();
    }
  };
  // Create an observer instance linked to the callback function
  const ed11yObserver = new MutationObserver(ed11yMutationCallback);
  // Start observing the target node for configured mutations
  ed11yObserver.observe(ed11yTargetNode, ed11yObserverConfig)
};

ed11yInit.ed11yOuterInit = function() {
  ed11yInit.ed11yWorker.port.onmessage = () => {
    wp.data.dispatch('core/block-editor').clearSelectedBlock();
  }
};

/*
// This failed. The Tiny MCE iframe has editable elements touching <body>.
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
    worker: ed11yWorkerURL,
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
  window.setTimeout(() => {
    ed11yInit.getOptions();
    ed11yInit.firstCheck();
  },1000)
  window.setTimeout(() => {
    ed11yInit.createObserver();
    if (Ed11y.openTip && Ed11y.openTip.button) {
      // Don't force recheck while a tip is open.
      return;
    }
    ed11yInit.recheck();
  }, 2500);
};

// Look to see if Gutenberg has loaded.
// Possible todo: add checks/markup for other common editors.
ed11yInit.findCompatibleEditor = function () {
  if (ed11yInit.editorType) {
    return;
  } else if (document.querySelector(ed11yInit.ed11yNoRun)) {
    ed11yInit.editorType = 'forbidden';
  } else if (document.querySelector('body' + ed11yInit.editRoot)) {
    // inside iFrame
    ed11yInit.editorType = 'inIframe';
    ed11yInit.ed11yPageInit();
  } else if (document.querySelector('[class*="-visual-editor"] iframe')) {
    ed11yInit.editorType = 'outsideIframe';
    ed11yInit.scrollRoot = 'body';
    ed11yInit.ed11yOuterInit();
  } else if ( document.querySelector('#editor .editor-styles-wrapper')) {
    ed11yInit.editorType = 'onPage';
    ed11yInit.editRoot = '#editor .is-root-container';
    ed11yInit.scrollRoot = '.interface-interface-skeleton__content';
    ed11yInit.ed11yPageInit();
  } else if (document.getElementById('content_ifr')) {
    ed11yInit.editorType = 'mce';
  } else if (ed11yInit.ed11yReadyCount < 600 && ed11yInit.editorType !== 'mce') {
    window.setTimeout(function () {
      ed11yInit.ed11yReadyCount++;
      ed11yInit.findCompatibleEditor();
    }, 1000);
  } else {
    console.log('Editoria11y: no block editor found');
  }
  console.log(ed11yInit.editorType);

};

// Call callback, scan page for compatible editors.
ed11yInit.ed11yReady(
  function () {
    ed11yInit.findCompatibleEditor();
  }
);
