<?php
@ini_set( 'upload_max_size' , '120M' );
@ini_set( 'post_max_size', '120M');
@ini_set( 'max_execution_time', '300' );
/**
 * Editoria11y functions settings loader.
 *
 * @package Editoria11y
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
	$user               = wp_get_current_user();
	$allowed_roles      = array( 'editor', 'administrator', 'author', 'contributor' );
	$allowed_user_roles = array_intersect( $allowed_roles, $user->roles );

	if ( is_user_logged_in()
		&& ( $allowed_user_roles || current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ) )
	) {
		// added last two parameters 10/27/22 need to test.
		wp_enqueue_script( 'ed11y-wp-js', trailingslashit( ED11Y_ASSETS ) . 'lib/editoria11y.min.js', null, true, Ed11y::ED11Y_VERSION, false );
		wp_enqueue_script( 'ed11y-wp-js-shim', trailingslashit( ED11Y_ASSETS ) . 'js/editoria11y-wp.js', array( 'wp-api' ), true, Ed11y::ED11Y_VERSION, false );
	}
}
add_action( 'wp_enqueue_scripts', 'ed11y_load_scripts' );

/**
 * Loads the scripts for the plugin.
 */
function ed11y_load_block_editor_scripts() {
	// Get the enable option.
	// Check if scroll top enable.
	// Todo: only load on edit pages.
	wp_enqueue_script( 'ed11y-wp-js', trailingslashit( ED11Y_ASSETS ) . 'lib/editoria11y.min.js', null, true, Ed11y::ED11Y_VERSION, false );
	wp_enqueue_script( 'ed11y-wp-editor', trailingslashit( ED11Y_ASSETS ) . 'js/editoria11y-editor.js', null, true, Ed11y::ED11Y_VERSION, false );
	
}

/**
 * Initialize.
 */
function ed11y_init() {

	// Allowed roles.
	$user               = wp_get_current_user();
	$allowed_roles      = array( 'editor', 'administrator', 'author', 'contributor' );
	$allowed_user_roles = array_intersect( $allowed_roles, $user->roles );

	// Instantiates Editoria11y on the page for allowed users.
	if ( is_user_logged_in()
		&& ( $allowed_user_roles || current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ) )
	) {
		// Get the plugin settings value.
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

		// Use permalink as sync URL if available
		$ed11y_page = get_permalink( get_the_ID() );
		// Otherwise use query path
		if ( empty( $ed11y_page ) || is_archive() || is_home() || is_front_page() ) {
			global $wp;
			$ed11y_page = home_url( $wp->request );
		}

        global $wpdb;
        $dismissals_on_page = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
				{$wpdb->prefix}ed11y_dismissals.result_key,
				{$wpdb->prefix}ed11y_dismissals.element_id,
				{$wpdb->prefix}ed11y_dismissals.dismissal_status
				FROM {$wpdb->prefix}ed11y_dismissals
				INNER JOIN {$wpdb->prefix}ed11y_urls ON {$wpdb->prefix}ed11y_urls.pid={$wpdb->prefix}ed11y_dismissals.pid
				WHERE {$wpdb->prefix}ed11y_urls.page_url = %s
				;",
				array( $ed11y_page )
			)
			
		);
		//error_log(print_r($dismissals_on_page));
        $synced_dismissals = array();
        foreach ( $dismissals_on_page as $key => $value ) {
            $synced_dismissals[ $value->result_key ][ $value->element_id ] = $value->dismissal_status;
        }

		// Allowed characters before echoing.
		$r = array(
			'&gt;'   => '>',
			'&quot;' => '"',
			'&#039;' => '"',
		);

		$debugtitle = wp_filter_nohtml_kses( trim( wp_title( '', false, 'right' ) ) );

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
                preventCheckingIfPresent: \'' . $ed11y_no_run . '\',
				currentPage : \'' . $ed11y_page . '\',
				admin: false,
                syncedDismissals: ' . wp_json_encode( $synced_dismissals ) . ',
                title : \'' . $debugtitle . '\',
                ' . $extra_props . '
            };
			console.log(\'' . $ed11y_page . '\');
			console.log(\'Title: ' . $debugtitle . '\');
		</script>';
	}
}
add_action( 'wp_footer', 'ed11y_init' );
// Load live checker when editor is present.

function editor_init() {
	// add_action( 'admin_enqueue_scripts', 'ed11y_load_block_editor_scripts' );
	add_action( 'enqueue_block_editor_assets', 'ed11y_load_block_editor_scripts' );
	add_action( 'admin_footer', 'ed11y_init' );
}
add_action( 'wp_enqueue_editor', 'editor_init' );
// This would enqueue on the site editor page, which lacks a preview target:
// add_action( 'enqueue_block_editor_assets', 'editor_init' );
