<?php

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
		// Prepare settings array.

		$ed1vals                      = array();
		$ed1vals['checkRoots']        = ed11y_get_plugin_settings( 'ed11y_checkRoots' );
		$ed1vals['ignoreElements']    = ed11y_get_plugin_settings( 'ed11y_ignore_elements' );
		$ed1vals['ignoreElements']    = empty( $ed1vals['ignoreElements'] ) ? '.wp-block-post-comments *, #wpadminbar *' : '.wp-block-post-comments *, #wpadminbar *, ' . $ed1vals['ignoreElements'];
		$ed1vals['linkIgnoreStrings'] = ed11y_get_plugin_settings( 'ed11y_link_ignore_strings' );
		$ed1vals['embeddedContent']   = ed11y_get_plugin_settings( 'ed11y_link_ignore_strings' );

		// Embedded content.
		$ed1vals['videoContent']    = ed11y_get_plugin_settings( 'ed11y_videoContent' );
		$ed1vals['audioContent']    = ed11y_get_plugin_settings( 'ed11y_audioContent' );
		$ed1vals['embeddedContent'] = ed11y_get_plugin_settings( 'ed11y_embeddedContent' );

		// Advanced settings.
		$ed1vals['preventCheckingIfPresent'] = ed11y_get_plugin_settings( 'ed11y_no_run' );

		// Use permalink as sync URL if available.
		$ed1vals['currentPage'] = get_permalink( get_the_ID() );
		// Otherwise use query path
		if ( empty( $ed1vals['currentPage'] ) || is_archive() || is_home() || is_front_page() ) {
			global $wp;
			$ed1vals['currentPage'] = home_url( $wp->request );
		}

		global $wpdb;
		$utable                      = $wpdb->prefix . 'ed11y_urls';
		$dtable                      = $wpdb->prefix . 'ed11y_dismissals';
		$dismissals_on_page          = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
				{$dtable}.result_key,
				{$dtable}.element_id,
				{$dtable}.dismissal_status
				FROM {$dtable}
				INNER JOIN {$utable} ON {$utable}.pid={$dtable}.pid
				WHERE {$utable}.page_url = %s
				AND (
					{$dtable}.dismissal_status = 'ok'
						OR
						(
							{$dtable}.dismissal_status = 'hide'
							AND
							{$dtable}.user = %d
						)
					)
				;",
				array(
					$ed1vals['currentPage'],
					$user->ID,
				)
			)
		);
		$ed1vals['syncedDismissals'] = array();
		foreach ( $dismissals_on_page as $key => $value ) {
			$ed1vals['syncedDismissals'][ $value->result_key ][ $value->element_id ] = $value->dismissal_status;
		}

		// Allowed characters before echoing.
		$r = array(
			'&gt;'   => '>',
			'&quot;' => '"',
			'&#039;' => '"',
		);

		$ed1vals['title'] = trim( wp_title( '', false, 'right' ) );

		$ed1vals['entity_type'] = 'other';
		// https://wordpress.stackexchange.com/questions/83887/return-current-page-type
		if ( is_page() ) {
			$ed1vals['entity_type'] = is_front_page() ? 'Front' : 'Page';
		} elseif ( is_home() ) {
			$ed1vals['entity_type'] = 'Home';
		} elseif ( is_single() ) {
			$ed1vals['entity_type'] = ( is_attachment() ) ? 'Attachment' : 'Post';
		} elseif ( is_category() ) {
			$ed1vals['entity_type'] = 'Category';
		} elseif ( is_tag() ) {
			$ed1vals['entity_type'] = 'Tag';
		} elseif ( is_tax() ) {
			$ed1vals['entity_type'] = 'Taxonomy';
		} elseif ( is_archive() ) {
			if ( is_author() ) {
				$ed1vals['entity_type'] = 'Author';
			} else {
				$ed1vals['entity_type'] = 'Archive';
			}
		} elseif ( is_search() ) {
			$ed1vals['entity_type'] = 'Search';
		} elseif ( is_404() ) {
			$ed1vals['entity_type'] = '404';
		}

		// At the moment, PHP escapes HTML breakouts. This would not be safe in other languages.
		echo '
		<script id="ed11y-wp-init" type="application/json">
			' . wp_json_encode( $ed1vals ) . '
		</script>
		';
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
