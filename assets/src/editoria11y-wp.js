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

// Call callback, init Editoria11y.
ed11yReady(
	function() {
		if (ed11yOptions) {
			// When triggered by the in-editor "issues" link, force assertive.
			if (window.location.href.indexOf("ed11y=show") > -1) {
				ed11yOptions['alertMode'] = 'assertive';
				ed11yOptions['doNotRun'] = !!ed11yOptions['doNotRun'] ? ed11yOptions['doNotRun'] + ', .elementor-editor-active' : '.elementor-editor-active'; 
			}
			const ed11y = new Ed11y(ed11yOptions);
		} 
	}
);
