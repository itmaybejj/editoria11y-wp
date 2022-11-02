
// Create callback to see if document is ready.
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

// Call callback, scan page for compatible editors.
ed11yReady(
	function() {
		ed11yFindCompatibleEditor();
	}
);

// Get issue count from Ed11y object and apply to alert link.
let ed11yUpdateCount = function() {
	Ed11y.wpIssueLink.textContent = Ed11y.totalCount;
	// We update the href to make sure we have the right nonce.
	Ed11y.wpIssueLink.setAttribute('href', ed11yPreviewLink.getAttribute('href') + '&ed11y=show');
	if (Ed11y.errorCount > 0) {
		Ed11y.wpIssueLink.classList.remove('ed11y-warning', 'hidden');
		Ed11y.wpIssueLink.classList.add('ed11y-alert');
	} else if (Ed11y.warningCount > 0) {
		Ed11y.wpIssueLink.classList.remove('ed11y-alert', 'hidden');
		Ed11y.wpIssueLink.classList.add('ed11y-warning');
	} else {
		Ed11y.wpIssueLink.classList.add('hidden');
	}
	// todo: aria-live announcements.
	if (Ed11y.results.length > 0) {
		let ed11yStyles = '';
		Ed11y.results.forEach(result => {
			let ed11yContainerId = result[0].closest('.wp-block').getAttribute('id');
			let ed11yRingColor = !result[4] ? Ed11y.color.alert : Ed11y.color.warning;
			let ed11yFontColor = !result[4] ? '#fff' : '#111';
			ed11yStyles += `
				#${ed11yContainerId}:not(.is-selected)::before { 
					position: absolute;
					font-size: 13px;
					background: ${ed11yRingColor};
					color: ${ed11yFontColor};
					display: inline-block;
					padding: 2px 4px 0;
					content: '${Ed11y.M[result[1]]['title']}';
					z-index: 1;
					font-family: sans-serif;
					font-weight: 500;
					line-height: 15px;
				}
				#${ed11yContainerId}:not(.is-selected) { 
					box-shadow: 0 0 0 1px #fff, inset 0 0 0 2px ${ed11yRingColor}, 0 0 0 3px ${ed11yRingColor}, 0 0 1px 3px;
					outline: 2px solid ${ed11yRingColor};
					outline-offset: 1px; 
				}
			`;
		})
		let newStyles = document.querySelector('#ed11y-live-highlighter');
		if (!newStyles) {
			newStyles = document.createElement('div');
			newStyles.setAttribute('hidden', '');
			newStyles.setAttribute('id', 'ed11y-live-highlighter');
			document.querySelector('body').append(newStyles);
		}
		newStyles.innerHTML = `
		<style>${ed11yStyles}</style>
		`;
	}

}

let ed11yFindNewBlocks = function() {
	ed11yOptions['ignoreElements'] = ed11yOptions['originalIgnore'];
	let ed11yActiveBlock = document.querySelector('.wp-block.is-selected')?.getAttribute('id');
	if (ed11yActiveBlock !== 'undefined' && !Ed11y.WPBlocks.includes(ed11yActiveBlock)) {
		ed11yOptions['ignoreElements'] += `, #${ed11yActiveBlock}, #${ed11yActiveBlock} *`;
	}
}

// Initiate Editoria11y create alert link, initiate content change watcher.
let ed11yAdminInit = function(ed11yTarget) {
	ed11yRunning = true;
		
	// Initiate Ed11y with admin options.
	// Todo: pick checkRoot dynamically based on ed11yTarget.
	ed11yOptions['checkRoots'] = '.editor-styles-wrapper';
	ed11yOptions['ignoreByKey'] = {img : ''};
	ed11yOptions['originalIgnore'] = ed11yOptions['ignoreElements'];
	ed11yInitialBlocks = document.querySelectorAll('.wp-block');
	Ed11y.WPBlocks = [];
	if (ed11yInitialBlocks.length !== null) {
		ed11yInitialBlocks.forEach(block => {
			Ed11y.WPBlocks.push(block.getAttribute('id'));
		})
	}
	ed11yFindNewBlocks();
	const ed11y = new Ed11y(ed11yOptions);
	document.addEventListener('ed11yResults', function () {
		ed11yUpdateCount();
	});
	
	// Set up issue counter link.
	Ed11y.wpIssueLink = document.createElement('a');
	Ed11y.wpIssueLink.setAttribute('target', '_blank');
	Ed11y.wpIssueLink.classList.add('components-button');
	Ed11y.wpIssueLink.setAttribute('id', 'ed11y-issue-link');
	Ed11y.wpIssueLink.setAttribute('title', 'Open preview with issues highlighted');
	Ed11y.wpIssueLink.textContent = "0";
	// Todo: add event listener to transfer click to preview link. It appears to have additional functions attached.
	ed11yInsertAt.prepend(Ed11y.wpIssueLink);
	let ed11yStyle = document.createElement('div');
	ed11yStyle.setAttribute('hidden','');
	ed11yStyle.innerHTML = `
	<style>
		#ed11y-issue-link {
			margin: 0 .5em 0 0;
		}
		#ed11y-issue-link.ed11y-warning {
			background-color: #fad859;
			color: #000b;
		}
		#ed11y-issue-link.ed11y-alert {
			color: #fff;
			background: #d63638;
		}
		ed11y-element-panel { display: none !important; }
	</style>`;
	Ed11y.wpIssueLink.insertAdjacentElement('afterend', ed11yStyle);

	// Set up change observer.
	// Todo: set class dynamically based on target.
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
	
}

// Look to see if Gutenberg has loaded.
// Todo: add checks/markup for other common editors.
let ed11yReadyCount = 0;
let ed11yTarget = false;
let ed11yInsertAt = false;
let ed11yPreviewLink = false;
let ed11yFindCompatibleEditor = function() {
	ed11yTarget = ed11yTarget ? ed11yTarget : document.querySelector('.editor-styles-wrapper');
	ed11yInsertAt = ed11yInsertAt ? ed11yInsertAt : document.querySelector('.interface-pinned-items');
	ed11yPreviewLink = ed11yPreviewLink ? ed11yPreviewLink : document.querySelector('a[href*="?preview=true"], a[href*="&preview=true"]');
	if (!!ed11yTarget & !!ed11yInsertAt & !!ed11yPreviewLink) {
		ed11yAdminInit(ed11yTarget);
	} else if (ed11yReadyCount < 10) {
		window.setTimeout(function() {
			ed11yReadyCount++;
			ed11yFindCompatibleEditor();
		},500);
	} else {
		console.log('No editor found');
	}
}

// Debouncer: trigger re-check when typing pauses for > .5s.
let ed11yMutationTimeout;
function ed11yMutationTimeoutWatch() {
  clearTimeout(ed11yMutationTimeout);
  if (Ed11y.panel.classList.contains('active') === false) {
	ed11yMutationTimeout = setTimeout(function () {
		// Wishlist todo: check active block on enter and exit and increment count.
		// This would prevent premature alerts on just-added headings and tables.
		ed11yFindNewBlocks();
		Ed11y.options.ignoreElements = ed11yOptions['ignoreElements'];
		Ed11y.checkAll(false,false);
	  }, 500);
  }
}

