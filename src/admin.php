<?php
/**
 * Admin settings page.
 *
 *  @package         Editoria11y
 */

/* Allowed HTML for all translatable text strings */
$allowed_html = array(
	'em'     => array(),
	'strong' => array(),
	'code'   => array(),
);

function ed11y_get_allowed_html() {
	$allowed_html = array(
		'em'     => array(),
		'strong' => array(),
		'code'   => array(),
	);
	return $allowed_html;
}

/**
 * Sets up the plugin settings page and registers the plugin settings.
 *
 * @link   http://codex.wordpress.org/Function_Reference/add_options_page
 */
function ed11y_admin_menu() {
	$settings = add_options_page(
		esc_html__( 'Editoria11y Settings', 'ed11y-wp' ),
		esc_html__( 'Editoria11y', 'ed11y-wp' ),
		'manage_options',
		'ed11y',
		'ed11y_plugin_settings_render_page'
	);
	if ( ! $settings ) {
		return;
	}
	// Provided hook_suffix that's returned to add scripts only on settings page.
	add_action( 'load-' . $settings, 'ed11y_styles_scripts' );
}
add_action( 'admin_menu', 'ed11y_admin_menu' );

/**
 * Enqueue custom styles & scripts for plugin usage.
 */
function ed11y_styles_scripts() {
	// Load plugin admin style.
	wp_enqueue_style( 'ed11y-wp-css', trailingslashit( ED11Y_ASSETS ) . 'css/ed11y-wp-admin.css', null );
}

/**
 * Register settings.
 *
 * @link   http://codex.wordpress.org/Function_Reference/register_setting
 */
function ed11y_register_settings() {

	register_setting(
		'ed11y_settings',
		'ed11y_plugin_settings',
		'ed11y_plugin_settings_validate'
	);
}
add_action( 'admin_init', 'ed11y_register_settings' );

/**
 * Register the setting sections and fields.
 *
 * @link   http://codex.wordpress.org/Function_Reference/add_settings_section
 * @link   http://codex.wordpress.org/Function_Reference/add_settings_field
 */
function ed11y_setting_sections_fields() {

	/* Sections */

	// Add General section.
	add_settings_section(
		'ed11y_general_settings',
		'',
		'__return_false',
		'ed11y'
	);

	// Add General section.
	add_settings_section(
		'ed11y_exclusions_settings',
		__( 'Exclusions', 'ed11y-wp' ),
		'__return_false',
		'ed11y'
	);

	// Add Embedded content section.
	add_settings_section(
		'ed11y_embedded_content_settings',
		__( 'Embedded content', 'ed11y-wp' ),
		'__return_false',
		'ed11y'
	);

	// Add Advanced section.
	add_settings_section(
		'ed11y_advanced_settings',
		__( 'Advanced', 'ed11y-wp' ),
		'__return_false',
		'ed11y'
	);

	/* Fields */


	// Add 'Check Roots' input setting field.
	add_settings_field(
		'ed11y_checkRoots',
		esc_html__( 'Page regions to check', 'ed11y-wp' ),
		'ed11y_check_roots_field',
		'ed11y',
		'ed11y_general_settings',
		array( 'label_for' => 'ed11y_checkRoots' )
	);

	// Add container ignore field.
	add_settings_field(
		'ed11y_ignore_elements',
		esc_html__( 'Elements to ignore', 'ed11y-wp' ),
		'ed11y_ignore_elements_field',
		'ed11y',
		'ed11y_exclusions_settings',
		array( 'label_for' => 'ed11y_ignore_elements' )
	);

	// Add link text ignore field.
	add_settings_field(
		'ed11y_link_ignore_strings',
		esc_html__( 'Ignore these strings in links', 'ed11y-wp' ),
		'ed11y_link_ignore_strings_field',
		'ed11y',
		'ed11y_exclusions_settings',
		array( 'label_for' => 'ed11y_link_ignore_strings' )
	);

	// Video content
	add_settings_field(
		'ed11y_videoContent',
		esc_html__( 'Video sources', 'ed11y-wp' ),
		'ed11y_video_content_field',
		'ed11y',
		'ed11y_embedded_content_settings',
		array( 'label_for' => 'ed11y_videoContent' )
	);
	// Audio content
	add_settings_field(
		'ed11y_audioContent',
		esc_html__( 'Audio sources', 'ed11y-wp' ),
		'ed11y_audio_content_field',
		'ed11y',
		'ed11y_embedded_content_settings',
		array( 'label_for' => 'ed11y_audioContent' )
	);
	// embeddedContent content
	add_settings_field(
		'ed11y_embeddedContent',
		esc_html__( 'Embeds to flag for manual checking', 'ed11y-wp' ),
		'ed11y_embedded_content_field',
		'ed11y',
		'ed11y_embedded_content_settings',
		array( 'label_for' => 'ed11y_embeddedContent' )
	);

	// Don't run ed11y if these elements exist
	add_settings_field(
		'ed11y_no_run',
		esc_html__( 'Turn off Editoria11y if these elements exist', 'ed11y-wp' ),
		'ed11y_no_run_field',
		'ed11y',
		'ed11y_advanced_settings',
		array( 'label_for' => 'ed11y_no_run' )
	);

	// Add 'Extra Props' textarea setting field.
	add_settings_field(
		'ed11y_extra_props',
		esc_html__( 'Add extra props', 'ed11y-wp' ),
		'ed11y_extra_props_field',
		'ed11y',
		'ed11y_advanced_settings',
		array( 'label_for' => 'ed11y_extra_props' )
	);
}
add_action( 'admin_init', 'ed11y_setting_sections_fields' );

/**
 * Target field
 */
function ed11y_check_roots_field() {
	$settings = ed11y_get_plugin_settings( 'ed11y_checkRoots' );
	?>
	<input autocomplete="off" name="ed11y_plugin_settings[ed11y_checkRoots]" type="text" id="ed11y_checkRoots" value="<?php echo esc_attr( $settings ); ?>" aria-describedby="target_description" pattern="[^<>\\\x27;|@&\s]+"/>
	<p id="target_description">
		<?php
			$string = 'Input a <strong>single selector</strong> to target a specific region of your website. For example, use <code>main</code> to scan the main content region only.';
			echo wp_kses( __( $string, 'ed11y-wp' ), ed11y_get_allowed_html() );
		?>
	</p>
	<?php
}


/**
 * Container ignore field
 */
function ed11y_ignore_elements_field() {
	$settings = ed11y_get_plugin_settings( 'ed11y_ignore_elements' );
	?>
	<input autocomplete="off" class="regular-text" id="ed11y_ignore_elements" aria-describedby="exclusions_description" type="text" name="ed11y_plugin_settings[ed11y_ignore_elements]" value="<?php echo esc_attr( $settings ); ?>" pattern="[^<\\\x27;|@&]+"/>
	<p id="exclusions_description">
		<?php
			$string = 'Ignore entire regions of a page. For example, <code>#comments</code> to ignore the Comments section on all pages.';
			echo wp_kses( __( $string, 'ed11y-wp' ), ed11y_get_allowed_html() );
		?>
	</p>
	<?php
}


/**
 * Link span ignore field
 */
function ed11y_link_ignore_strings_field() {
	$settings = ed11y_get_plugin_settings( 'ed11y_link_ignore_strings' );
	?>
	   
	<input autocomplete="off" class="regular-text" id="ed11y_link_ignore_strings" aria-describedby="link_description" type="text" name="ed11y_plugin_settings[ed11y_link_ignore_strings]" value="<?php echo esc_attr( $settings ); ?>" pattern="[^<\\\x27;|@&]+"/>
	<p id="link_span_description">
		<?php
			$string = 'Ignore elements within a link to improve accuracy of link checks. For example: <code>&lt;a href=&#34;#&#34;&gt;learn more <strong>&lt;span class=&#34;sr-only&#34;&gt;external link&lt;/span&gt;</strong>&lt;/a&gt;</code>';
			echo wp_kses( __( $string, 'ed11y-wp' ), ed11y_get_allowed_html() );
		?>
	</p>
	<?php
}


/**
 * Video field
 */
function ed11y_video_content_field() {
	$settings = ed11y_get_plugin_settings( 'ed11y_videoContent' );
	?>
	<textarea required id="ed11y_videoContent" name="ed11y_plugin_settings[ed11y_videoContent]" cols="45" rows="3"><?php echo esc_html( $settings ); ?></textarea> 
	<?php
}

/**
 * Audio field
 */
function ed11y_audio_content_field() {
	$settings = ed11y_get_plugin_settings( 'ed11y_audioContent' );
	?>
	<textarea required id="ed11y_audioContent" name="ed11y_plugin_settings[ed11y_audioContent]" cols="45" rows="3"><?php echo esc_html( $settings ); ?></textarea>  
	<?php
}

/**
 * embeddedContent
 */
function ed11y_embedded_content_field() {
	$settings = ed11y_get_plugin_settings( 'ed11y_embeddedContent' );
	?>
	<textarea required id="ed11y_embeddedContent" name="ed11y_plugin_settings[ed11y_embeddedContent]" cols="45" rows="3"><?php echo esc_html( $settings ); ?></textarea>  
	<?php
}

/**
 * Turn off Editoria11y if these elements are detected
 */
function ed11y_no_run_field() {
	$settings = ed11y_get_plugin_settings( 'ed11y_no_run' );
	?>
	<input autocomplete="off" class="regular-text" id="ed11y_no_run" aria-describedby="ed11y_no_run_description" type="text" name="ed11y_plugin_settings[ed11y_no_run]" value="<?php echo esc_attr( $settings ); ?>" pattern="[^<>\\\x27;|@&]+"/>
	<p id="ed11y_no_run_description">
		<?php
			$string = 'Provide a list of selectors that are <strong>unique to pages</strong>. If any of the elements exist on the page, Editoria11y will not scan or appear.';
			echo wp_kses( __( $string, 'ed11y-wp' ), ed11y_get_allowed_html() );
		?>
	</p>

	<?php
}

/**
 * Extra props
 */
function ed11y_extra_props_field() {
	$settings = ed11y_get_plugin_settings( 'ed11y_extra_props' );
	?>
	<textarea name="ed11y_plugin_settings[ed11y_extra_props]" aria-describedby="extra_props_description" id="ed11y_extra_props" cols="45" rows="3"><?php echo esc_html( $settings ); ?></textarea>
	<p id="extra_props_description">
		<?php
			$domain = esc_url( __( 'https://ed11y.netlify.app/developers/props/', 'ed11y-wp' ) );
			$string = 'Pass additional (boolean) properties to customize. Refer to ';
			$anchor = esc_html__( 'documentation.', 'ed11y-wp' );
			echo wp_kses( __( $string, 'ed11y-wp' ), ed11y_get_allowed_html() );

			$link = sprintf( '<a href="%s">%s</a>', $domain, $anchor );
			echo sprintf( esc_html( '%1$s', 'ed11y-wp' ), $link );
		?>
	</p> 
	<?php
}

/**
 * Render the plugin settings page.
 */
function ed11y_plugin_settings_render_page() {
	?>

	<div class="wrap">
		<h1><?php esc_html_e( 'Editoria11y Settings', 'ed11y-wp' ); ?></h1>

		<div id="poststuff">
			<div id="post-body" class="ed11y-wp-settings metabox-holder columns-2">
				<div id="post-body-content">

				<div class="announcement-component">
					<h2 class="announcement-heading">
					<?php
						$current_user = wp_get_current_user();
						esc_html_e( 'Howdy ' . $current_user->nickname . ',', 'ed11y-wp' );
					?>
					</h2>
					<p>
					<?php
							esc_html_e( 'Editoria11y is your accessibility quality assurance assistant. Geared towards content authors, Editoria11y straightforwardly identifies content related issues at the source. Editoria11y works out-of-the-box, although you can use this settings page to customize the experience for website authors. Please note, Editoria11y is not a comprehensive code analysis tool. You should make sure you are using an accessible theme.', 'ed11y-wp' );
					?>
					</p>
					<p style="padding-top:8px">
					<?php
						esc_html_e( 'To learn more about Editoria11y, please visit the ', 'ed11y-wp' );
						$domain = esc_url( __( 'https://ed11y.netlify.app/', 'ed11y-wp' ) );
						$anchor = esc_html__( 'project website.', 'ed11y-wp' );
						$link   = sprintf( '<a href="%s">%s</a>', $domain, $anchor );
						echo sprintf( esc_html( '%1$s', 'ed11y-wp' ), $link );
					?>
					</p>
				</div>

				<form method="post" action="options.php" autocomplete="off" class="ed11y-form-admin">
					<?php settings_fields( 'ed11y_settings' ); ?>
					<?php do_settings_sections( 'ed11y' ); ?>
					<?php submit_button( esc_html__( 'Save Settings', 'ed11y-wp' ), 'primary large' ); ?>
				</form>
			</div><!-- .post-body-content -->

			<div id="postbox-container-1" class="postbox-container">
				<div class="postbox">
					<h2 class="screen-reader-text"><?php esc_html_e( 'More', 'ed11y-wp' ); ?></h2>
					<div class="inside">
						<h3 class="postbox-heading"><?php esc_html_e( 'Administrator guide', 'ed11y-wp' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'Specify the target area to check. Only check content your authors can edit.', 'ed11y-wp' ); ?></li>
							<li><?php esc_html_e( 'Turn off checks or features that are not relevant, including issues that cannot be fixed by content authors.', 'ed11y-wp' ); ?></li>
							<li>
							<?php
								$anchor = esc_html__( 'CSS selectors reference.', 'ed11y-wp' );
								$domain = esc_url( __( 'https://www.w3schools.com/cssref/css_selectors.asp', 'ed11y-wp' ) );
								$link   = sprintf( '<a href="%s">%s</a>', $domain, $anchor );
								echo sprintf( esc_html__( 'Create exclusions or ignore repetitive elements using CSS selectors. Use a comma to seperate multiple selectors. View %1$s', 'ed11y-wp' ), $link );
							?>
							</li>
						</ul>

						<h3 class="postbox-heading"><?php esc_html_e( 'Contribute', 'ed11y-wp' ); ?></h3>
						<ul>
							<li>
								<?php
								$anchor = esc_html__( 'Report a bug or leave feedback', 'ed11y-wp' );
								$domain = esc_url( __( 'https://forms.gle/sjzK9XykETaoqZv99', 'ed11y-wp' ) );
								$link   = sprintf( '<a href="%s">%s</a>', $domain, $anchor );
								echo sprintf( esc_html_x( '%1$s', 'ed11y-wp' ), $link );
								?>
							</li>
							<li>
								<?php
									$anchor = esc_html__( 'Help translate or improve', 'ed11y-wp' );
									$domain = esc_url( __( 'https://github.com/ryersondmp/ed11y/blob/master/CONTRIBUTING.md', 'ed11y-wp' ) );
									$link   = sprintf( '<a href="%s">%s</a>', $domain, $anchor );
									echo sprintf( esc_html_x( '%1$s', 'ed11y-wp' ), $link );
								?>
							</li>
						</ul>

						<h3 class="postbox-heading"><?php esc_html_e( 'Version', 'ed11y-wp' ); ?></h3>
						<ul>
							<li>
								<strong><?php esc_html_e( 'Editoria11y', 'ed11y-wp' ); ?>:</strong> 
								<?php echo Ed11y::ED11Y_VERSION; ?>
							</li>
							<li>
								<strong><?php esc_html_e( 'Plugin', 'ed11y-wp' ); ?>:</strong> 
								<?php echo Ed11y::WP_VERSION; ?> 
								<strong class="ed11y-admin-badge">Beta</strong>
							</li>
						</ul>

						<h3 class="postbox-heading"><?php esc_html_e( 'Acknowledgements', 'ed11y-wp' ); ?></h3>
						<p>
						<?php
							$anchor = esc_html__( 'all acknowledgements.', 'ed11y-wp' );
							$domain = esc_url( __( 'https://ed11y.netlify.app/acknowledgements/', 'ed11y-wp' ) );
							$link   = sprintf( '<a href="%s">%s</a>', $domain, $anchor );
							echo sprintf( esc_html__( 'Development led by Adam Chaboryk at Toronto Metropolitan University. View %1$s', 'ed11y-wp' ), $link );
						?>
							</p>
						<br>
						<p><?php esc_html_e( '© 2022 Toronto Metropolitan University.', 'ed11y-wp' ); ?></p>
					</div>
				</div>
			</div><!-- .postbox-container -->

			</div><!-- .ed11y-wp-settings -->
			<br class="clear">
		</div>
	</div>
	<?php
}

/**
 * Validates/sanitizes the plugins settings after they've been submitted.
 */
function ed11y_plugin_settings_validate( $settings ) {

	/* Deep cleaning to help with error handling and security */
	$remove        = array(
		'&lt;'     => '',
		'&apos;'   => '',
		'&amp;'    => '',
		'&percnt;' => '',
		'&#96;'    => '',
		'`'        => '',
	);
	$remove_extra  = array(
		'&gt;' => '',
		'>'    => '',
	);
	$target_remove = array_merge( $remove, $remove_extra );

	$settings['ed11y_checkRoots'] = strtr(
		sanitize_text_field( $settings['ed11y_checkRoots'] ),
		$target_remove
	);

	/* Exclusions */
	$settings['ed11y_ignore_elements'] = strtr(
		sanitize_text_field( $settings['ed11y_ignore_elements'] ),
		$remove
	);

	$settings['ed11y_link_ignore_strings'] = strtr(
		sanitize_text_field( $settings['ed11y_link_ignore_strings'] ),
		$remove
	);

	$settings['ed11y_links_to_flag'] = strtr(
		sanitize_text_field( $settings['ed11y_links_to_flag'] ),
		$remove
	);

	/*
	 Regex match for deep cleaning */
	/* Allowed characters: , . : empty space */
	$special_chars = '/[^.,:a-zA-Z0-9 ]/';

	/* Video */
	$settings['ed11y_videoContent'] = preg_replace(
		$special_chars,
		'',
		sanitize_text_field( $settings['ed11y_videoContent'] )
	);

	/* Audio */
	$settings['ed11y_audioContent'] = preg_replace(
		$special_chars,
		'',
		sanitize_text_field( $settings['ed11y_audioContent'] )
	);

	/* Data Visualizations */
	$settings['ed11y_embeddedContent'] = preg_replace(
		$special_chars,
		'',
		sanitize_text_field( $settings['ed11y_embeddedContent'] )
	);

	/* Don't run Editoria11y */
	$settings['ed11y_no_run'] = strtr(
		sanitize_text_field( $settings['ed11y_no_run'] ),
		$target_remove
	);

	/* Advanced props */
	$settings['ed11y_extra_props'] = preg_replace(
		$special_chars,
		'',
		sanitize_text_field( $settings['ed11y_extra_props'] )
	);

	return $settings;
}



/**
 * Render the plugin settings page.
 */
function editoria11y_dashboard() {

	wp_enqueue_script( 'ed11y-wp-js', trailingslashit( ED11Y_ASSETS ) . 'lib/editoria11y.min.js', array( 'wp-api' ), true, Ed11y::ED11Y_VERSION, false );
	wp_enqueue_script( 'ed11y-wp-js-dash', trailingslashit( ED11Y_ASSETS ) . 'js/ed11y-dashboard.js', array( 'wp-api' ), true, Ed11y::ED11Y_VERSION, false );
	wp_enqueue_style( 'ed11y-wp-css', trailingslashit( ED11Y_ASSETS ) . 'css/ed11y-dashboard.css', null );
	echo '<div id="ed1">
			<h1>Editoria11y accessibility checker</h1>
			<div id="ed1-results-wrapper"></div>
			<div id="ed1-page-wrapper"></div>
			<div id="ed1-dismissals-wrapper"></div>
		</div>';

}

add_action( 'admin_menu', 'ed11y_dashboard_menu' );
function ed11y_dashboard_menu() {
	add_menu_page( esc_html__( 'Editoria11y', 'ed11y-wp' ), esc_html__( 'Editoria11y', 'ed11y-wp' ), 'manage_options', ED11Y_SRC . 'admin.php', 'editoria11y_dashboard', 'dashicons-chart-bar', 90 );
};


