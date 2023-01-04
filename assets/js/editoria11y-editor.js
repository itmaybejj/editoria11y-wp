let ed11yOptions = false;

// TODO: localStorage or WP use preference for open/shut notifications.
// TODO: share dismissal API with preview page.
// TODO: create aria-live region. Populate it with the issues for the is-active block. It will only change when the block changes.
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
	Ed11y.wpIssueToggle.textContent = Ed11y.totalCount;
	Ed11y.wpIssueToggle.setAttribute('aria-label', Ed11y.totalCount + ' accessibility issues')
	if (Ed11y.errorCount > 0) {
		Ed11y.wpIssueToggle.classList.remove('ed11y-warning', 'hidden');
		Ed11y.wpIssueToggle.classList.add('ed11y-alert');
	} else if (Ed11y.warningCount > 0) {
		Ed11y.wpIssueToggle.classList.remove('ed11y-alert', 'hidden');
		Ed11y.wpIssueToggle.classList.add('ed11y-warning');
	} else {
		Ed11y.wpIssueToggle.classList.add('hidden');
	}
	// todo: aria-live announcements.
	if (Ed11y.results.length > 0 && ed11yOptions['showResults'] === true) {
		let ed11yStyles = '';
		let ed11yKnownContainers = {};
		Ed11y.results.forEach(result => {
			if (result[5] === false) {
				let ed11yContainerId = result[0].closest('.wp-block').getAttribute('id');
				let ed11yRingColor = !result[4] ? Ed11y.color.alert : Ed11y.color.warning;
				let ed11yFontColor = !result[4] ? '#fff' : '#111';
				// Concatenate results when multiple hits in same black.
				if (!ed11yKnownContainers[ed11yContainerId]) {
					// First alert in block.
					ed11yKnownContainers[ed11yContainerId] = {
						title : Ed11y.M[result[1]]['title'],
						ring : ed11yRingColor,
						font : ed11yFontColor,
					};
				} else {
					if (ed11yKnownContainers[ed11yContainerId]['title'].indexOf(Ed11y.M[result[1]]['title']) === -1) {
						// First alert of this type in block.
						if (ed11yKnownContainers[ed11yContainerId]['ring'] !== ed11yRingColor) {
							// If one is red, red wins.
							ed11yRingColor = Ed11y.color.alert;
						}
						// Put question marks at end.
						let ed11yNewTitle = '';
						if (Ed11y.M[result[1]]['title'].indexOf('?') === -1) {
							ed11yNewTitle =  Ed11y.M[result[1]]['title'] + ', ' + ed11yKnownContainers[ed11yContainerId]['title']
						} else {
							ed11yNewTitle = ed11yKnownContainers[ed11yContainerId]['title'] + ', ' + Ed11y.M[result[1]]['title']
						}
						ed11yKnownContainers[ed11yContainerId] = {
							title : ed11yNewTitle,
							ring : ed11yRingColor,
							font : ed11yFontColor,
						};
					} 
				}
			}
			
		})

		for (const [key, value] of Object.entries(ed11yKnownContainers)) {
			ed11yStyles += `
				#${key}::after { 
					position: absolute;
					font-size: 13px;
					background: ${value.ring};
					color: ${value.font};
					display: inline-block;
					padding: 4px 4px 2px 6px;
					content: '${value.title.replace('?,', ',')}';
					z-index: -1;
					opacity: 0;
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
					font-weight: 500;
					line-height: 15px;
					bottom: 0;
					right: 0;
					border-radius: 2px 0 0 0;
				}
				#${key}:not(.is-selected)::after { 
					opacity: 1;
					z-index: 1;
				}
				#${key}:not(.is-selected) { 
					box-shadow: 0 0 0 2px ${value.ring};
					outline: 1px solid ${value.ring};
					border-radius: 2px;
				}
			`;
		  }

		
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

const ed11yLiveToggle = `
<div class="components-menu-group"><div role="group"><button type="button" role="menuitem" class="components-button components-menu-item__button ed11y-live-edit"><span class="components-menu-item__item">Show issues</span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" class="components-menu-items__item-icon has-icon-right" aria-hidden="true" focusable="false"><path d="M16.7 7.1l-6.3 8.5-3.3-2.5-.9 1.2 4.5 3.4L17.9 8z"></path></svg></button></div></div>
`;

// Initiate Editoria11y create alert link, initiate content change watcher.
let ed11yAdminInit = function(ed11yTarget) {
	ed11yRunning = true;
	
	ed11yOptions = JSON.parse(ed11yOptions.innerHTML);
	ed11yOptions.linkIgnoreStrings = ed11yOptions.linkIgnoreStrings ? new RegExp(ed11yOptions.linkIgnoreStrings, 'g') : false;
		
	// Initiate Ed11y with admin options.
	// Todo: pick checkRoot dynamically based on ed11yTarget.
	ed11yOptions['checkRoots'] = '.editor-styles-wrapper';
	ed11yOptions['ignoreByKey'] = {img : ''};
	ed11yOptions['originalIgnore'] = ed11yOptions['ignoreElements'];
	ed11yOptions['showResults'] = true;
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
	ed11yButtonDescription = document.createElement('span');
	ed11yButtonDescription.setAttribute('hidden','');
	ed11yButtonDescription.setAttribute('id', 'ed11y-button-description');
	ed11yButtonDescription.textContent = 'Screen reader accessible issue descriptions have been added to the preview page.';
	Ed11y.wpIssueToggle = document.createElement('button');
	Ed11y.wpIssueToggle.classList.add('components-button', 'is-secondary', 'hidden');
	Ed11y.wpIssueToggle.setAttribute('id', 'ed11y-issue-link');
	Ed11y.wpIssueToggle.setAttribute('title', 'Hide accessibility issues.');
	Ed11y.wpIssueToggle.setAttribute('aria-pressed', 'true');
	Ed11y.wpIssueToggle.setAttribute('aria-describedby', 'ed11y-button-description');
	Ed11y.wpIssueToggle.addEventListener('click', function() {
		if (Ed11y.wpIssueToggle.getAttribute('aria-pressed') === 'true') {
			Ed11y.wpIssueToggle.setAttribute('aria-pressed','false');
			Ed11y.wpIssueToggle.setAttribute('title', 'Show accessibility issues');
			let newStyles = document.querySelector('#ed11y-live-highlighter');
			if (newStyles) {
				newStyles.innerHTML = '';
			}
			ed11yOptions['showResults'] = false;
		} else {
			Ed11y.wpIssueToggle.setAttribute('aria-pressed','true');
			Ed11y.wpIssueToggle.setAttribute('title', 'Hide accessibility issues');
			ed11yOptions['showResults'] = true;
			ed11yUpdateCount();
		}
	});
	Ed11y.wpIssueToggle.textContent = "0";
	Ed11y.wpIssueToggle.setAttribute('aria-live', 'polite');
	// Todo: add event listener to transfer click to preview link. It appears to have additional functions attached.
	ed11yPreviewLink.parentElement.insertAdjacentElement('afterend', Ed11y.wpIssueToggle);
	Ed11y.wpIssueToggle.insertAdjacentElement('afterend', ed11yButtonDescription);

	let ed11yStyle = document.createElement('div');
	ed11yStyle.setAttribute('hidden','');
	ed11yStyle.innerHTML = `
	<style>
		#ed11y-issue-link.ed11y-warning[aria-pressed="true"] {
			background-color: #fad859;
			color: #000b;
		}
		
		#ed11y-issue-link.ed11y-alert[aria-pressed="true"] {
			background: #b80519;
			color: #fff;
		}
		#ed11y-issue-link[aria-pressed="true"]:not(:focus-visible) {
			box-shadow: none;
		}
		ed11y-element-panel { display: none !important; }
	</style>`;
	Ed11y.wpIssueToggle.insertAdjacentElement('afterend', ed11yStyle);

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
let ed11yPreviewLink = false;
let ed11yFindCompatibleEditor = function() {
	ed11yTarget = ed11yTarget ? ed11yTarget : document.querySelector('.editor-styles-wrapper');
	ed11yPreviewLink = ed11yPreviewLink ? ed11yPreviewLink : document.querySelector('button[class*="preview"]');
	ed11yOptions = ed11yOptions ? ed11yOptions : document.getElementById("ed11y-wp-init");
	if (!!ed11yTarget & !!ed11yPreviewLink && !!ed11yOptions) {
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
  if (Ed11y.panel && Ed11y.panel.classList.contains('active') === false) {
	ed11yMutationTimeout = setTimeout(function () {
		ed11yFindNewBlocks();
		Ed11y.options.ignoreElements = ed11yOptions['ignoreElements'];
		Ed11y.checkAll(false,false);
	  }, 500);
  }
}
