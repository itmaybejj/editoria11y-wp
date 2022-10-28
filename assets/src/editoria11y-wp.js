function ed11yReady(fn) {
	if (document.readyState != 'loading'){
	  fn();
	} else if (document.addEventListener) {
	  document.addEventListener('DOMContentLoaded', fn);
	} else {
	  document.attachEvent('onreadystatechange', function() {
		if (document.readyState != 'loading')
		  fn();
	  });
	}
  }
ed11yReady(
	function() {
		if (ed11yOptions && ed11yOptions.admin === false) {
			const ed11y = new Ed11y(ed11yOptions);
		} else {
			ed11yFindTarget();
		}
	}
);

let ed11yRunning = false;
let ed11yAdminInit = function() {
	ed11yRunning = true;
	let insertionPoint = document.querySelector('.interface-pinned-items');
	let previewLink = document.querySelector('a[href*="?preview"]');
	if (insertionPoint && previewLink) {
		const ed11y = new Ed11y(ed11yOptions);
		let alertLink = document.createElement('a');
		alertLink.setAttribute('target', '_blank');
		alertLink.setAttribute('href', previewLink.getAttribute('href'));
		alertLink.textContent = "checkmark";
		insertionPoint.prepend(alertLink);
		const ed11yTargetNode = document.querySelector('.editor-styles-wrapper');
		// Options for the observer (which mutations to observe)
		const ed11yObserverConfig = { attributes: true, childList: true, subtree: true };
		// Callback function to execute when mutations are observed
		const ed11yMutationCallback = (mutationList, observer) => {
		for (const mutation of mutationList) {
			if (mutation.type === 'childList') {
				ed11yMutationTimeoutWatch();
			} 
		}
		};
		// Create an observer instance linked to the callback function
		const ed11yObserver = new MutationObserver(ed11yMutationCallback);
		// Start observing the target node for configured mutations
		ed11yObserver.observe(ed11yTargetNode, ed11yObserverConfig);
		// Later, you can stop observing
		//ed11yObserver.disconnect();
	}
	// todo don't start mutation observer if this fails
}

let targetReady = null;
let count = 0;
let ed11yFindTarget = function() {
	count++;
	console.log(count);
	targetReady = document.querySelector('.editor-styles-wrapper');
	if (!targetReady && count < 1000) {
		window.setTimeout(function() {
			ed11yFindTarget();
		},100);
	} else {
		if (ed11yRunning === false) {
			ed11yAdminInit();
		}
	}
}


let ed11yMutationTimeout;
function ed11yMutationTimeoutWatch() {
  clearTimeout(ed11yMutationTimeout);
  if (Ed11y.panel.classList.contains('active') === false) {
	ed11yMutationTimeout = setTimeout(function () {
		Ed11y.checkAll(false,false);
	  }, 1000);
  }
}

