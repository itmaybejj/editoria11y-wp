<?php
/**
 * Sets up custom filters for the plugin's output.
 */

add_filter( 'plugin_action_links_' . ED11Y_BASE, 'add_action_links' );
function add_action_links( $links ) {
	$mylinks = array(
		'<a href="' . admin_url( 'options-general.php?page=ed11y' ) . '">Advanced Settings</a>',
	);
	return array_merge( $links, $mylinks );
}

/**
 * Return the default plugin settings.
 */
function ed11y_get_defaultOptions() {

	$defaultOptions = array(
		'ed11y_enable'              => absint( 1 ),
		'ed11y_lang'                => esc_html__( 'en' ),
		'ed11y_checkRoots'          => esc_html__( 'body' ),
		'ed11y_contrast'            => absint( 1 ),
		'ed11y_forms'               => absint( 1 ),
		'ed11y_links_advanced'      => absint( 1 ),

		'ed11y_ignore_elements'     => esc_html__( '' ),
		'ed11y_link_ignore_strings' => esc_html__( '' ),

		'ed11y_videoContent'        => esc_html__( 'youtube.com, vimeo.com, yuja.com, panopto.com' ),
		'ed11y_audioContent'        => esc_html__( 'soundcloud.com, simplecast.com, podbean.com, buzzsprout.com, blubrry.com, transistor.fm, fusebox.fm, libsyn.com' ),
		'ed11y_embeddedContent'     => esc_html__( 'datastudio.google.com, tableau' ),

		'ed11y_no_run'              => esc_html__( '' ),
		'ed11y_extra_props'         => esc_html__( '' ),
	);

	// Allow dev to filter the default settings.
	return apply_filters( 'ed11y_defaultOptions', $defaultOptions );
}

/**
 * Function for quickly grabbing settings for the plugin without having to call get_option()
 * every time we need a setting.
 */
function ed11y_get_plugin_settings( $option = '' ) {
	$settings = get_option( 'ed11y_plugin_settings', ed11y_get_defaultOptions() );
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
	if ( $enable === 1
		&& is_user_logged_in()
		&& ( $allowed_user_roles || current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ) )
	) {
		// added last two parameters 10/27/22 need to test
		wp_enqueue_script( 'ed11y-wp-js', trailingslashit( ED11Y_ASSETS ) . 'src/editoria11y.min.js', null, true, ED11Y_VERSION, true );
	}
}
add_action( 'wp_enqueue_scripts', 'ed11y_load_scripts' );


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
	if ( $enable === 1
		&& is_user_logged_in()
		&& ( $allowed_user_roles || current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ) )
	) {
		// Get the plugin settings value
		$enable            = absint( ed11y_get_plugin_settings( 'ed11y_enable' ) );
		$checkRoots        = esc_html__( ed11y_get_plugin_settings( 'ed11y_checkRoots' ) );
		$ignoreElements    = esc_html__( ed11y_get_plugin_settings( 'ed11y_ignore_elements' ) );
		$ignoreElements    = empty( $ignoreElements ) ? '#comments *, #wpadminbar *' : '#comments *, #wpadminbar *, ' . $ignoreElements;
		$linkIgnoreStrings = esc_html__( ed11y_get_plugin_settings( 'ed11y_link_ignore_strings' ) );

		// Embedded content.
		$videoContent    = wp_filter_nohtml_kses( ed11y_get_plugin_settings( 'ed11y_videoContent' ) );
		$audioContent    = wp_filter_nohtml_kses( ed11y_get_plugin_settings( 'ed11y_audioContent' ) );
		$embeddedContent = wp_filter_nohtml_kses( ed11y_get_plugin_settings( 'ed11y_embeddedContent' ) );

		// Advanced settings.
		$ed11yNoRun = esc_html__( ed11y_get_plugin_settings( 'ed11y_no_run' ) );
		$extraProps = wp_filter_nohtml_kses( ed11y_get_plugin_settings( 'ed11y_extra_props' ) );

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

		echo '
		<script id="ed11y-wp-init">
            let ed11yOptions = {
                checkRoots:  \'' . strtr( $checkRoots, $r ) . '\',
                ignoreElements: \'' . strtr( $ignoreElements, $r ) . '\',
                linkIgnoreStrings: \'' . strtr( $linkIgnoreStrings, $r ) . '\',
                embeddedContent: \'' . $embeddedContent . '\',
                videoContent: \'' . $videoContent . '\',
                audioContent: \'' . $audioContent . '\',
                doNotRun: \'' . $ed11yNoRun . '\',
                ' . $extraProps . '
            }
            const ed11y = new Ed11y(ed11yOptions);
		</script>';
	}
}
add_action( 'wp_footer', 'ed11y_init' );
