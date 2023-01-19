<?php
/**
 * Admin settings page.
 *
 *  @package         Editoria11y
 */

function ed11y_allowed_html() {
	$allowed_html = array(
		'em'     => array(),
		'strong' => array(),
		'code'   => array(),
		'br'     => array(),
		'p'      => array(),
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
	wp_enqueue_style( 'editoria11y-wp-css', trailingslashit( ED11Y_ASSETS ) . 'css/editoria11y-wp-admin.css', null, Ed11y::ED11Y_VERSION );
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
		'ed11y_basic',
		__( 'Basic configuration', 'ed11y-wp' ),
		'__return_false',
		'ed11y'
	);

	// Add dataviz content section.
	add_settings_section(
		'ed11y_test_settings',
		__( 'Customize tests', 'ed11y-wp' ),
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
		esc_html__( 'Check content in these containers', 'ed11y-wp' ),
		'ed11y_check_roots_field',
		'ed11y',
		'ed11y_basic',
		array( 'label_for' => 'ed11y_checkRoots' )
	);

	// Add container ignore field.
	add_settings_field(
		'ed11y_ignore_elements',
		esc_html__( 'Exclude these elements from checks', 'ed11y-wp' ),
		'ed11y_ignore_elements_field',
		'ed11y',
		'ed11y_basic',
		array( 'label_for' => 'ed11y_ignore_elements' )
	);

	// Add live check field.
	add_settings_field(
		'ed11y_livecheck',
		esc_html__( 'Highlight issues while editing content', 'ed11y-wp' ),
		'ed11y_livecheck_field',
		'ed11y',
		'ed11y_basic',
		array( 'label_for' => 'ed11y_livecheck' )
	);

	// Add themepicker.
	add_settings_field(
		'ed11y_theme',
		esc_html__( 'Theme for tooltips', 'ed11y-wp' ),
		'ed11y_theme_field',
		'ed11y',
		'ed11y_basic',
		array( 'label_for' => 'ed11y_theme' )
	);

	// datavizContent content
	add_settings_field(
		'ed11y_datavizContent',
		esc_html__( 'Embeds that need manual review', 'ed11y-wp' ),
		'ed11y_dataviz_content_field',
		'ed11y',
		'ed11y_test_settings',
		array( 'label_for' => 'ed11y_datavizContent' )
	);

	// Video content
	add_settings_field(
		'ed11y_videoContent',
		esc_html__( 'Video sources that need captions', 'ed11y-wp' ),
		'ed11y_video_content_field',
		'ed11y',
		'ed11y_test_settings',
		array( 'label_for' => 'ed11y_videoContent' )
	);

	// Audio content
	add_settings_field(
		'ed11y_audioContent',
		esc_html__( 'Audio sources that need transcripts', 'ed11y-wp' ),
		'ed11y_audio_content_field',
		'ed11y',
		'ed11y_test_settings',
		array( 'label_for' => 'ed11y_audioContent' )
	);

	// Documents
	add_settings_field(
		'ed11y_documentContent',
		esc_html__( 'Document types that need manual review', 'ed11y-wp' ),
		'ed11y_document_content_field',
		'ed11y',
		'ed11y_document_content_settings',
		array( 'label_for' => 'ed11y_documentContent' )
	);

	// Add link text ignore field.
	add_settings_field(
		'ed11y_link_ignore_strings',
		esc_html__( 'Ignore these strings in links', 'ed11y-wp' ),
		'ed11y_link_ignore_strings_field',
		'ed11y',
		'ed11y_advanced_settings',
		array( 'label_for' => 'ed11y_link_ignore_strings' )
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

}
add_action( 'admin_init', 'ed11y_setting_sections_fields' );

/**
 * Target field
 */
function ed11y_theme_field() {
	$settings = ed11y_get_plugin_settings( 'ed11y_theme', true );
	?>

	<select name="ed11y_plugin_settings[ed11y_theme]" id="ed11y-theme" name="ed11y_theme" class="form-select">
		<option <?php echo 'lightTheme' === $settings ? 'selected="true"' : ''; ?>value="lightTheme">Light</option>
		<option <?php echo 'darkTheme' === $settings ? 'selected="true"' : ''; ?>value="darkTheme">Dark</option>
	</select>
		
	<?php
}

/**
 * Target field
 */
function ed11y_livecheck_field() {
	$settings = ed11y_get_plugin_settings( 'ed11y_livecheck', false );
	?>

	<select name="ed11y_plugin_settings[ed11y_livecheck]" id="ed11y-livecheck" name="ed11y_livecheck" class="form-select" aria-describedby="livecheck_description">
		<option <?php echo 'all' === $settings ? 'selected="true"' : ''; ?>value="all">All issues</option>
		<option <?php echo 'errors' === $settings ? 'selected="true"' : ''; ?>value="errors">Only definite errors</option>
		<option <?php echo 'none' === $settings ? 'selected="true"' : ''; ?>value="none">None</option>
	</select>
	<p id="livecheck_description">
		In the Gutenburg editor, this outlines potential issues as authors type. Authors can dismiss alerts from the preview page.
	</p>
		
	<?php
}

/**
 * Target field
 */
function ed11y_check_roots_field() {
	$settings = ed11y_get_plugin_settings( 'ed11y_checkRoots' );
	$default = ed11y_get_default_options( 'ed11y_checkRoots' );
	?>
	<input autocomplete="off" 
		name="ed11y_plugin_settings[ed11y_checkRoots]" 
		type="text" 
		id="ed11y_checkRoots" 
		placeholder="<?php echo esc_attr( $default ); ?>"
		value="<?php echo esc_attr( $settings ); ?>" 
		aria-describedby="target_description" />
	<p id="target_description">
		<?php
			$string = 'Restrict checker to editable parts of the page, e.g. <br><code>#content, footer</code>. Defaults to <code>main</code> or <code>body</code>, depending on theme.';
			echo wp_kses( __( $string, 'ed11y-wp' ), ed11y_allowed_html() );
		?>
		<br>
		<?php
			$string = '';
			echo wp_kses( __( $string, 'ed11y-wp' ), ed11y_allowed_html() );
		?>
	</p>
	<?php
}


/**
 * Container ignore field
 */
function ed11y_ignore_elements_field() {
	$settings = ed11y_get_plugin_settings( 'ed11y_ignore_elements' );
	$default  = ed11y_get_default_options( 'ed11y_ignore_elements' );
	?>
	<textarea autocomplete="off" 
	class="regular-text" id="ed11y_ignore_elements" 
	aria-describedby="exclusions_description" 
	name="ed11y_plugin_settings[ed11y_ignore_elements]"
	rows="3" cols="45"
	placeholder="<?php echo esc_attr( $default ); ?>"><?php echo esc_attr( $settings ); ?></textarea>
	<p id="exclusions_description">
		<?php
			$string = 'Provide CSS selectors for specific elements, e.g. <code>.menu a</code>.';
			echo wp_kses( __( $string, 'ed11y-wp' ), ed11y_allowed_html() );
		?>
	</p>
	<?php
}

/**
 * Video field
 */
function ed11y_video_content_field() {
	$settings = ed11y_get_plugin_settings( 'ed11y_videoContent' );
	$default  = ed11y_get_default_options( 'ed11y_videoContent' );
	?>
	<textarea id="ed11y_videoContent" 
		name="ed11y_plugin_settings[ed11y_videoContent]" 
		cols="45" rows="3"
		placeholder="<?php echo esc_attr( $default ); ?>" ><?php echo esc_html( $settings ); ?></textarea> 
	<?php
}

/**
 * Audio field
 */
function ed11y_audio_content_field() {
	$settings = ed11y_get_plugin_settings( 'ed11y_audioContent' );
	$default  = ed11y_get_default_options( 'ed11y_audioContent' );
	?>
	<textarea id="ed11y_audioContent" name="ed11y_plugin_settings[ed11y_audioContent]" 
	cols="45" rows="3"
	placeholder="<?php echo esc_attr( $default ); ?>"
	><?php echo esc_html( $settings ); ?></textarea>  
	<?php
}

/**
 * Document field
 */
function ed11y_document_content_field() {
	$settings = ed11y_get_plugin_settings( 'ed11y_documentContent' );
	$default  = ed11y_get_default_options( 'ed11y_documentContent' );
	?>
	<textarea id="ed11y_documentContent" name="ed11y_plugin_settings[ed11y_documentContent]" 
	cols="45" rows="3"
	placeholder="<?php echo esc_attr( $default ); ?>"
	><?php echo esc_html( $settings ); ?></textarea>  
	<?php
}

/**
 * datavizContent
 */
function ed11y_dataviz_content_field() {
	// todo this does not map directly to my two options
	$settings = ed11y_get_plugin_settings( 'ed11y_datavizContent' );
	$default  = ed11y_get_default_options( 'ed11y_datavizContent' );
	?>
	<textarea id="ed11y_datavizContent" name="ed11y_plugin_settings[ed11y_datavizContent]" 
	cols="45" rows="3"
	placeholder="<?php echo esc_attr( $default ); ?>"
	><?php echo esc_html( $settings ); ?></textarea>  
	<?php
}

/**
 * Link span ignore field
 */
function ed11y_link_ignore_strings_field() {
	$settings = ed11y_get_plugin_settings( 'ed11y_link_ignore_strings' );
	$default  = ed11y_get_default_options( 'ed11y_link_ignore_strings' );
	?>
	   
	<input autocomplete="off" class="regular-text" 
	id="ed11y_link_ignore_strings" 
	aria-describedby="link_description" 
	type="text" 
	name="ed11y_plugin_settings[ed11y_link_ignore_strings]" 
	placeholder="<?php echo esc_attr( $default ); ?>" 
	value="<?php echo esc_attr( $settings ); ?>"/>
	<p id="link_span_description">
		<?php
			$string = 'Some themes inject hidden text for screen readers to explain external link icons. Provide a RegEx to exclude this theme-injected text from tests, e.g.:<br> <code>(Link opens in new window)|(External link)</code>';
			echo wp_kses( __( $string, 'ed11y-wp' ), ed11y_allowed_html() );
		?>
	</p>
	<?php
}

/**
 * Turn off Editoria11y if these elements are detected
 */
function ed11y_no_run_field() {
	$settings = ed11y_get_plugin_settings( 'ed11y_no_run' );
	$default  = ed11y_get_default_options( 'ed11y_no_run' );
	?>
	<input autocomplete="off" 
	class="regular-text" id="ed11y_no_run" 
	aria-describedby="ed11y_no_run_description" 
	type="text" name="ed11y_plugin_settings[ed11y_no_run]" 
	placeholder="<?php echo esc_attr( $default ); ?>" 
	value="<?php echo esc_attr( $settings ); ?>" pattern="[^<>\\\x27;|@&]+"/>
	<p id="ed11y_no_run_description">
		<?php
			$string = 'Used to disable checks on particular pager, or when content editing tools are active.';
			echo wp_kses( __( $string, 'ed11y-wp' ), ed11y_allowed_html() );
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
					<!-- stuff above the form -->
				</div>

				<form method="post" action="options.php" autocomplete="off" class="ed11y-form-admin">
					<?php settings_fields( 'ed11y_settings' ); ?>
					<?php do_settings_sections( 'ed11y' ); ?>
					<?php submit_button( esc_html__( 'Save Settings', 'ed11y-wp' ), 'primary large' ); ?>
				</form>
			</div><!-- .post-body-content -->

			<div id="postbox-container-1" class="postbox-container">
				<div class="postbox">
					<div class="inside">
					<h2 class="postbox-heading">
						Getting started
					</h2>
					<p>Editoria11y should work out of the box in most themes (view a 
						<a href="https://jjameson.mycpanel.princeton.edu/editoria11y/">demo of the authoring experience</a>). 
						<ol>
							<li>If authors do not see the checker toggle, check your <a href="https://developer.mozilla.org/en-US/docs/Tools/Browser_Console" class="ext" data-extlink="">browser console</a> for errors, and make sure the theme is not hiding <code>ed11y-element-panel</code>.</li>
							<li>If the checker toggle is <strong>present</strong> but not finding much: make sure your content areas are listed in "Check content in these containers". It is not uncommon for themes to insert editable content outside the <code>main</code> element.</li></ol>
					</p>
						

						<h2 class="postbox-heading">Getting help</h3>
						<ul>
							<li>
								<a href="https://github.com/itmaybejj/editoria11y">Editoria11y library documentation</a>
							</li>
							<li>
								<a href="https://github.com/itmaybejj/editoria11y-wp/issues">Issues &amp; feature requests</a><br><br>
								<span style="font-size: .9em;">Version: <?php echo ( esc_html( Ed11y::ED11Y_VERSION ) ); ?></span>
							</li>
						</ul>
						


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

	/* Don't run Editoria11y */
	$settings['ed11y_no_run'] = strtr(
		sanitize_text_field( $settings['ed11y_no_run'] ),
		$target_remove
	);

	/*
	 Regex match for deep cleaning */
	/* Allowed characters: , . : empty space */
	$special_chars = '/[^.,:a-zA-Z0-9 ]/';

	$settings['ed11y_livecheck'] = preg_replace(
		$special_chars,
		'',
		sanitize_text_field( $settings['ed11y_livecheck'] )
	);
	$settings['ed11y_theme'] = preg_replace(
		$special_chars,
		'',
		sanitize_text_field( $settings['ed11y_theme'] )
	);
	
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

	/* Document */
	$settings['ed11y_documentContent'] = preg_replace(
		$special_chars,
		'',
		sanitize_text_field( $settings['ed11y_documentContent'] )
	);

	/* Data Visualizations */
	$settings['ed11y_datavizContent'] = preg_replace(
		$special_chars,
		'',
		sanitize_text_field( $settings['ed11y_datavizContent'] )
	);


	return $settings;
}



/**
 * Render the plugin settings page.
 */
function editoria11y_dashboard() {

	wp_enqueue_script( 'ed11y-wp-js', trailingslashit( ED11Y_ASSETS ) . 'lib/editoria11y.min.js', array( 'wp-api' ), true, Ed11y::ED11Y_VERSION, false );
	wp_enqueue_script( 'ed11y-wp-js-dash', trailingslashit( ED11Y_ASSETS ) . 'js/editoria11y-dashboard.js', array( 'wp-api' ), true, Ed11y::ED11Y_VERSION, false );
	wp_enqueue_style( 'ed11y-wp-css', trailingslashit( ED11Y_ASSETS ) . 'css/editoria11y-dashboard.css', null, Ed11y::ED11Y_VERSION );
	echo '<div id="ed1">
			<h1>Editoria11y accessibility checker</h1>
			<div id="ed1-page-wrapper"></div>
			<div id="ed1-results-wrapper"></div>
			<div id="ed1-dismissals-wrapper"></div>
		</div>';

}

add_action( 'admin_menu', 'ed11y_dashboard_menu' );
function ed11y_dashboard_menu() {
	add_menu_page( esc_html__( 'Editoria11y', 'ed11y-wp' ), esc_html__( 'Editoria11y', 'ed11y-wp' ), 'manage_options', ED11Y_SRC . 'admin.php', 'editoria11y_dashboard', 'dashicons-chart-bar', 90 );
}
