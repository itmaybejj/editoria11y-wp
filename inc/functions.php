<?php
/**
 * Editoria11y functions settings loader.
 *
 * @package         Editoria11y
 */

add_filter( 'plugin_action_links_' . ED11Y_BASE, 'add_action_links' );

/**
 * Adds link to setting page on plugin admin screen.
 *
 * @param array $links WP action link array.
 */
function add_action_links( $links ) {
	$mylinks = array(
		'<a href="' . admin_url( 'options-general.php?page=ed11y' ) . '">Settings</a>',
	);
	return array_merge( $links, $mylinks );
}

/**
 * Return the default plugin settings.
 */
function ed11y_get_default_options() {

	$default_options = array(
		'ed11y_enable'              => absint( 1 ),
		'ed11y_lang'                => esc_html__( 'en' ),
		'ed11y_checkRoots'          => esc_html__( 'body' ),
		'ed11y_contrast'            => absint( 1 ),
		'ed11y_forms'               => absint( 1 ),
		'ed11y_links_advanced'      => absint( 1 ),

		'ed11y_ignore_elements'     => '',
		'ed11y_link_ignore_strings' => '',

		'ed11y_videoContent'        => 'youtube.com, vimeo.com, yuja.com, panopto.com',
		'ed11y_audioContent'        => 'soundcloud.com, simplecast.com, podbean.com, buzzsprout.com, blubrry.com, transistor.fm, fusebox.fm, libsyn.com',
		'ed11y_embeddedContent'     => 'datastudio.google.com, tableau',

		'ed11y_no_run'              => '',
		'ed11y_extra_props'         => '',
	);

	// Allow dev to filter the default settings.
	return apply_filters( 'ed11y_default_options', $default_options );
}

/**
 * Function for quickly grabbing settings for the plugin without having to call get_option()
 * every time we need a setting.
 *
 * @param mixed $option Option name.
 */
function ed11y_get_plugin_settings( $option = '' ) {
	$settings = get_option( 'ed11y_plugin_settings', ed11y_get_default_options() );
	return $settings[ $option ];
}


/**
 * Loads the scripts for the plugin.
 */
function ed11y_load_scripts() {

	// Get the enable option.
	$enable             = ed11y_get_plugin_settings( 'ed11y_enable' );
	$user               = wp_get_current_user();
	$allowed_roles      = array( 'editor', 'administrator', 'author', 'contributor' );
	$allowed_user_roles = array_intersect( $allowed_roles, $user->roles );

	// Check if scroll top enable.
	if ( 1 === $enable
		&& is_user_logged_in()
		&& ( $allowed_user_roles || current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ) )
	) {
		// added last two parameters 10/27/22 need to test.
		wp_enqueue_script( 'ed11y-wp-js', trailingslashit( ED11Y_ASSETS ) . 'src/editoria11y.min.js', null, true, Ed11y::ED11Y_VERSION, false );
		wp_enqueue_script( 'ed11y-wp-js-shim', trailingslashit( ED11Y_ASSETS ) . 'src/editoria11y-wp.js', null, true, Ed11y::ED11Y_VERSION, false );
	}
}
add_action( 'wp_enqueue_scripts', 'ed11y_load_scripts' );

/**
 * Loads the scripts for the plugin.
 */
function ed11y_load_admin_scripts() {

	// Get the enable option.
	$enable = ed11y_get_plugin_settings( 'ed11y_enable' );

	// Check if scroll top enable.
	if ( 1 === $enable ) {
		// Todo: only load on edit pages.
		wp_enqueue_script( 'ed11y-wp-js', trailingslashit( ED11Y_ASSETS ) . 'src/editoria11y.min.js', null, true, Ed11y::ED11Y_VERSION, false );
		wp_enqueue_script( 'ed11y-wp-editor', trailingslashit( ED11Y_ASSETS ) . 'src/editoria11y-editor.js', null, true, Ed11y::ED11Y_VERSION, false );
	}
}

/**
 * Initialize.
 */
function ed11y_init() {

	$enable = ed11y_get_plugin_settings( 'ed11y_enable' );

	// Allowed roles.
	$user               = wp_get_current_user();
	$allowed_roles      = array( 'editor', 'administrator', 'author', 'contributor' );
	$allowed_user_roles = array_intersect( $allowed_roles, $user->roles );

	// Instantiates Editoria11y on the page for allowed users.
	if ( 1 === $enable
		&& is_user_logged_in()
		&& ( $allowed_user_roles || current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ) )
	) {
		// Get the plugin settings value.
		$enable              = absint( ed11y_get_plugin_settings( 'ed11y_enable' ) );
		$check_roots         = esc_html( ed11y_get_plugin_settings( 'ed11y_checkRoots' ) );
		$ignore_elements     = esc_html( ed11y_get_plugin_settings( 'ed11y_ignore_elements' ) );
		$ignore_elements     = empty( $ignore_elements ) ? '.wp-block-post-comments *, #wpadminbar *' : '.wp-block-post-comments *, #wpadminbar *, ' . $ignore_elements;
		$link_ignore_strings = esc_html( ed11y_get_plugin_settings( 'ed11y_link_ignore_strings' ) );

		// Embedded content.
		$video_content    = wp_filter_nohtml_kses( ed11y_get_plugin_settings( 'ed11y_videoContent' ) );
		$audio_content    = wp_filter_nohtml_kses( ed11y_get_plugin_settings( 'ed11y_audioContent' ) );
		$embedded_content = wp_filter_nohtml_kses( ed11y_get_plugin_settings( 'ed11y_embeddedContent' ) );

		// Advanced settings.
		$ed11y_no_run = esc_html( ed11y_get_plugin_settings( 'ed11y_no_run' ) );
		$extra_props  = wp_filter_nohtml_kses( ed11y_get_plugin_settings( 'ed11y_extra_props' ) );

		// Target areaget_page_lang()
		if ( empty( $target ) ) {
			$target = false;
		}

		// Allowed characters before echoing.
		$r = array(
			'&gt;'   => '>',
			'&quot;' => '"',
			'&#039;' => '"',
		);

		// ed11yReady hat tip https://youmightnotneedjquery.com/#ready.
		echo '
		<script id="ed11y-wp-init">
            let ed11yOptions = {
                checkRoots:  \'' . strtr( $check_roots, $r ) . '\',
                ignoreElements: \'' . strtr( $ignore_elements, $r ) . '\',
                linkIgnoreStrings: \'' . strtr( $link_ignore_strings, $r ) . '\',
                embeddedContent: \'' . $embedded_content . '\',
                videoContent: \'' . $video_content . '\',
                audioContent: \'' . $audio_content . '\',
                doNotRun: \'' . $ed11y_no_run . '\',
				admin: false,
                ' . $extra_props . '
            }
		</script>';
	}
}
add_action( 'wp_footer', 'ed11y_init' );
// Load live checker when editor is present.

function editor_init() {
	// add_action( 'admin_enqueue_scripts', 'ed11y_load_admin_scripts' );
	add_action( 'enqueue_block_editor_assets', 'ed11y_load_admin_scripts' );
	add_action( 'admin_footer', 'ed11y_init' );
}
add_action( 'wp_enqueue_editor', 'editor_init' );
// This would enqueue on the site editor page, which lacks a preview target:
// add_action( 'enqueue_block_editor_assets', 'editor_init' );
