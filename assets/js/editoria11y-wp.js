// Todo refactoring broke putting results

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

function ed11ySync() {
	let postData = async function (action, data) {
		fetch(wpApiSettings.root  + 'ed11y/v1/' + action,{
			method: "PUT",
			headers:{
				'Content-Type': 'application/json',
				'accept': 'application/json',
				'X-WP-Nonce': wpApiSettings.nonce,
			},
			body:JSON.stringify({
				data,
			})
		}).then(function(response){
			return response.json();
		}).then(function(post){
			//console.log(post);
		});
	}

	// Purge changed aliases & deleted pages.
	/* todo: Purge isn't ready yet.
	let urlParams = new URLSearchParams(window.location.search);
	if (urlParams.has('ed1ref') && urlParams.get('ed1ref') !== ed11yOptions.currentPage) {
		let data = {
			page_path: urlParams.get('ed1ref'),
		};
		window.setTimeout(function() {
			postData('purge/page', data);
		},0,data);
	}
	*/
	// TODO: SEND A MESSAGE?

	let extractResults = function () {
		results = {};
		dismissals = [];
		total = 0;
		Ed11y.results.forEach(result => {
			/* let test = Ed11y.results[i][1];
          		let dismissKey = Ed11y.results[i][4]; */
			let testName = result[1];
			let dismissStatus = result[5];
			let dismissKey = result[4];
			if (dismissStatus !== "ok") {
				// log all items not marked as OK
				if (results[testName]) {
					results[testName] = parseInt(results[testName]) + 1;
					total++;
				} else {
					results[testName] = 1;
					total++;
				}
			}
			if (dismissStatus !== "false") {
				let insert = {};
				insert = [testName, dismissKey];
				dismissals.push(
					insert
				)
			}
		})
		return [results, dismissals, total];
	}

	let sendResults = function () {
		window.setTimeout(function () {
			total = 0;
			let results = extractResults();
			let url = Ed11y.options.currentPage;
			url = url.length > 250 ? url.substring(0, 250) : url;
			let data = {
				page_title: ed11yOptions.title,
				page_count: results[2],
				entity_type: ed11yOptions.entity_type, // node or false
				results: results[0],
				dismissals: results[1],
				page_url: url,
				created: 0,
			};
			console.log(data);
			postData('result', data);
		  // Short timeout to let execution queue clear.
		}, 100)
	}

	let firstRun = true;
	
	document.addEventListener('ed11yResults', function () {
		if (firstRun) {
			sendResults();
			firstRun = false;
		}
	});

	let sendDismissal = function (detail) {
		if (!!detail) {
			let data = {
					page_url: Ed11y.options.currentPage,
					result_key: detail.dismissTest, // which test is sending a result
					element_id: detail.dismissKey, // some recognizable attribute of the item marked
					dismissal_status: detail.dismissAction, // ok, ignore or reset
				};
			postData('dismiss', data);
			if (detail.dismissAction !== 'hide') {
				window.setTimeout(function() {
					sendResults();
				},100);
			}
		}
	}
	document.addEventListener('ed11yDismissalUpdate', function (e) {
		sendDismissal(e.detail)}, false);
	
}


  // Call callback, init Editoria11y.
ed11yReady(
	function() {
		let ed11yOpts = document.getElementById("ed11y-wp-init");
		console.log(ed11yOpts);
		if (!!ed11yOpts && window.location.href.indexOf('elementor-preview') === -1) {
			ed11yOptions = JSON.parse(ed11yOpts.innerHTML);
			console.log(ed11yOptions);
			console.log(ed11yOptions['title']);
			if (ed11yOptions.title.length < 3) {
				ed11yOptions.title = document.title;
			}			
			// When triggered by the in-editor "issues" link, force assertive.
			if (window.location.href.indexOf("preview=true") > -1) {
				ed11yOptions['alertMode'] = 'assertive'; 
			}
			const ed11y = new Ed11y(ed11yOptions);
			ed11ySync();
		} 
	}
);

