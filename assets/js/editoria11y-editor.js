let ed11yOptions = false;
let ed11yOpen = localStorage.getItem('ed11yOpen');
ed11yOpen = 'open' === ed11yOpen ? true : false;

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
	let count = ed11yOptions['liveCheck'] === 'errors' ? Ed11y.errorCount : Ed11y.totalCount;
	count = parseInt(count);
	buttonText = ed11yOpen ? 'Hide alerts' : `${count} issues`;
	Ed11y.wpIssueToggle.textContent = buttonText;
	if ((ed11yOptions['liveCheck'] === 'all') && Ed11y.totalCount === 0 || ed11yOptions === 'errors' && Ed11y.errorCount === 0) {
		Ed11y.wpIssueToggle.classList.add('hidden');
	} else if (ed11yOpen) {
		Ed11y.wpIssueToggle.classList.remove('ed11y-warning', 'hidden', 'ed11y-alert');	
	} else if (!ed11yOpen && Ed11y.errorCount > 0) {
		Ed11y.wpIssueToggle.classList.remove('ed11y-warning', 'hidden');
		Ed11y.wpIssueToggle.classList.add('ed11y-alert');
	} else if (!ed11yOpen && Ed11y.warningCount > 0 && ed11yOptions['liveCheck'] !== 'errors') {
		Ed11y.wpIssueToggle.classList.remove('ed11y-alert', 'hidden');
		Ed11y.wpIssueToggle.classList.add('ed11y-warning');
	} 

	let newStyles = document.querySelector('#ed11y-live-highlighter');
	if (!newStyles) {
		newStyles = document.createElement('div');
		newStyles.setAttribute('hidden', '');
		newStyles.setAttribute('id', 'ed11y-live-highlighter');
		document.querySelector('body').append(newStyles);
	}

	// todo: aria-live announcements.
	if (Ed11y.results.length > 0 && ed11yOptions['showResults'] === true) {
		let ed11yStyles = '';
		let ed11yKnownContainers = {};
		Ed11y.results.forEach(result => {
			// Skip dismissed items, and only show warnings if they have not been suppressed in plugin settings.
			if (ed11yOpen && result[5] === false && !(ed11yOptions['liveCheck'] === 'errors' && result[4])) {
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

		
		
		newStyles.innerHTML = `
		<style>${ed11yStyles}</style>
		`;
	}
	else {
		newStyles.innerHTML = '';
	}
}

let ed11yFindNewBlocks = function() {
	ed11yOptions['ignoreElements'] = ed11yOptions['originalIgnore'];
	let ed11yActiveBlock = document.querySelector('.wp-block.is-selected')?.getAttribute('id');
	// Ignoring a new block until it is edited.
	if (!!ed11yActiveBlock && ed11yActiveBlock !== 'undefined' && !Ed11y.WPBlocks.includes(ed11yActiveBlock)) {
		Ed11y.WPBlocks.push(ed11yActiveBlock);
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
	ed11yOptions['ignoreByKey']['h'] = '.wp-block-post-title';

	// Wordpress does not render empty post titles so we don't need to flag them.
	ed11yOptions['originalIgnore'] = ed11yOptions['ignoreElements'];

	ed11yOptions['showResults'] = true;
	ed11yOptions['alertMode'] = 'headless';
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
	Ed11y.wpIssueToggle.setAttribute('aria-describedby', 'ed11y-button-description');
	Ed11y.wpIssueToggle.addEventListener('click', function() {
		if (ed11yOpen) {
			localStorage.setItem('ed11yOpen', 'shut');
			ed11yOpen = false;
			let newStyles = document.querySelector('#ed11y-live-highlighter');
			if (newStyles) {
				newStyles.innerHTML = '';
			}
			ed11yOptions['showResults'] = false;
			ed11yUpdateCount();
		} else {
			localStorage.setItem('ed11yOpen', 'open');
			ed11yOpen = true;
			ed11yOptions['showResults'] = true;
			Ed11y.wpIssueToggle.textContent = 'Hide issues';
			ed11yUpdateCount();
		}
	});
	Ed11y.wpIssueToggle.textContent = "0";
	// Todo: add event listener to transfer click to preview link. It appears to have additional functions attached.
	ed11yPreviewLink.parentElement.insertAdjacentElement('afterend', Ed11y.wpIssueToggle);
	Ed11y.wpIssueToggle.insertAdjacentElement('afterend', ed11yButtonDescription);

	let ed11yStyle = document.createElement('div');
	ed11yStyle.setAttribute('hidden','');
	ed11yStyle.innerHTML = `
	<style>
		#ed11y-issue-link.ed11y-warning {
			background-color: #fad859;
			color: #000b;
			box-shadow: none;
		}
		
		#ed11y-issue-link.ed11y-alert {
			background-color: #b80519;
			color: #fff;
			box-shadow: none;
		}
		#ed11y-issue-link:hover {
			background: var(--wp-admin-theme-color-darker-10);
			color: white;
		}
		#ed11y-issue-link:focus-visible {
			box-shadow: 0 0 0 1px white, 0 0 0 2px var(--wp-admin-theme-color-darker-10);
		}
		ed11y-element-panel { display: none !important; }
	</style>`;
	Ed11y.wpIssueToggle.insertAdjacentElement('afterend', ed11yStyle);

	// Set up change observer.
	const ed11yTargetNode = document.querySelector('.editor-styles-wrapper');
	// Observe for class changes and typing.
	const ed11yObserverConfig = { attributeFilter: ['class'], characterData: true, subtree: true };
	// Immediately recheck on class change; wait for typing pauses for typing.
	const ed11yMutationCallback = (callback) => {
		if (callback[0].type === 'characterData') {
			ed11yMutationTimeoutWatch(750);
		} else {
			ed11yMutationTimeoutWatch(0);
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

/**
 * Debounced recheck:
 * Immediately, on block selection change.
 * After .75s pause, when typing.
 * No more frequently than every 1500s.
 */
let ed11yMutationTimeout;
let ed11yMutationRacer = 0;
function ed11yMutationTimeoutWatch(wait) {
	clearTimeout(ed11yMutationTimeout);

	let timeOut = ed11yMutationRacer - Date.now();
	timeOut = timeOut < 0 ? wait : timeOut;
	
	ed11yMutationTimeout = setTimeout(function () {
		ed11yMutationRacer = Date.now() + 1500;
		if (Ed11y && Ed11y.running === false) {
			ed11yFindNewBlocks();
			Ed11y.options.ignoreElements = ed11yOptions['ignoreElements'];
			Ed11y.checkAll(false,false);
		}
	}, timeOut);
}
