jQuery( document ).on( 'tinymce-editor-setup', function( event, editor ) {
  //editor.settings.content_css += ',https://editoria11y-wp.ddev.site/wp-content/plugins/editoria11y-wp/assets/lib/editoria11y.min.css';
  /*editor.settings.external_plugins = {
    'noneditable': 'https://editoria11y-wp.ddev.site/wp-content/plugins/editoria11y-wp/assets/js/tinymce4.5noneditable.js'
  }*/
  //editor.settings.plugins += ',noneditable';
  //editor.settings.noneditable_noneditable_class = 'mceNonEditable'; // need to check for others?
  //editor.settings.noneditable_noneditable_class = 'ed11y-element'; // need to check for others?
  //console.log(editor.settings);
  /*editor.on('getContent', function(e) {
    console.log(e);
  });*/
  editor.on('getContent', function(e) {
    console.log(e);
  })
});

jQuery( document ).on( 'tinymce-editor-init', function( event, editor ) {
  const head = editor.dom.select('head')[0];
  /*editor.dom.add(
    head,
    'script',
    {
      src: "https://editoria11y-wp.ddev.site/wp-content/plugins/editoria11y-wp/assets/lib/editoria11y.min.js?ver=2.0.12",
      type: 'text/javascript'
    },
  );*/
  editor.dom.add(
    head,
    'script',
    {
      src: "https://editoria11y-wp.ddev.site/wp-content/plugins/editoria11y-wp/assets/js/editoria11y-mce-inner.js?ver=2.0.12",
      type: 'text/javascript'
    }
  );
});
