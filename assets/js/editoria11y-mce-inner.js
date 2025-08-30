window.setTimeout(() => {
  parent.startMCEEd11y(document.body);
},100);

/*
const ed11yFramed = {};
ed11yFramed.options = Object.assign(parent.ed11yInit.options);
ed11yFramed.options.checkRoots = 'body';
ed11yFramed.options.customTests = 1;
ed11yFramed.options['alertMode'] = 'customUI'
ed11yFramed.options.editableContent = 'body';
ed11yFramed.options.autoDetectShadowComponents = false;
ed11yFramed.options.watchForChanges = true;
ed11yFramed.options.editorHeadingLevel = [
  {
    selector: 'body',
    previousHeading: 1,
  },
  {
    selector: '*',
    previousHeading: 0,
  },
];
ed11yFramed.forceFullCheck = false;

console.log(ed11yFramed.options);
document.addEventListener('ed11yRunCustomTests', function() {
    ed11yFramed.forceFullCheck = true;
    let allDone = new CustomEvent('ed11yResume');
    document.dispatchEvent(allDone);
  }
);

const ed11ySync = {}
//console.log(parent.ed11yIframeCommunication);
window.setTimeout(function() {
  //console.log(parent.ed11yIframeCommunication);
},100)
//ed11ySync.worker = window.SharedWorker ? new SharedWorker(ed11yVars.worker) : false;
//ed11ySync.innerWorker.port.start();

const newResults = function() {
  console.log('results!');
  parent.ed11yIframeResults({
    results: Ed11y.results,
    forceFullCheck: ed11yFramed.forceFullCheck,
  }
  );
}

document.addEventListener('ed11yResults', newResults);
window.setTimeout(() => {
  const ed11y = new Ed11y(ed11yFramed.options);
},100);

*/
