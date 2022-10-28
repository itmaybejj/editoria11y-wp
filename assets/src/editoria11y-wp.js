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
			if (window.location.href.indexOf("ed11y=show") > -1) {
				ed11yOptions['alertMode'] = 'assertive';
			}
			const ed11y = new Ed11y(ed11yOptions);
		} else {
			ed11yFindTarget();
		}
	}
);

let alertLink = document.createElement('a');
let ed11yUpdateCount = function(alertLink) {
	alertLink.textContent = Ed11y.totalCount + " issues";
	if (Ed11y.errorCount > 0) {
		alertLink.classList.remove('ed11y-warning', 'hidden');
		alertLink.classList.add('ed11y-errors');
	} else if (Ed11y.warningCount > 0) {
		alertLink.classList.remove('ed11y-error', 'hidden');
		alertLink.classList.add('ed11y-warning');
	} else {
		alertLink.classList.add('hidden');
	}
}

let ed11yRunning = false;
let ed11yAdminInit = function() {
	ed11yRunning = true;
	let insertionPoint = document.querySelector('.interface-pinned-items');
	let previewLink = document.querySelector('a[href*="?preview"]');
	if (insertionPoint && previewLink) {
		ed11yOptions['ignoreByKey'] = {img : ''};
		const ed11y = new Ed11y(ed11yOptions);
		alertLink.setAttribute('target', '_blank');
		alertLink.classList.add('components-button');
		alertLink.setAttribute('href', previewLink.getAttribute('href') + '&ed11y=show');
		alertLink.textContent = "checkmark";
		document.addEventListener('ed11yResults', function () {
			ed11yUpdateCount(alertLink);
		});
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

