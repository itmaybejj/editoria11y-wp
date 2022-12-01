<?php
/**
 * Editoria11y, the accessibility quality assurance assistant.
 *
 * Plugin Name:       Editoria11y
 * Plugin URI:        https://itmaybejj.github.io/editoria11y/
 * Version:           0.0.1
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            John Jameson, Princeton University
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ed11y
 * Domain Path:       /languages
 * Description:       Editoria11y is your accessibility quality assurance assistant. Geared towards content authors, Editoria11y straightforwardly identifies accessibility issues at the source.
 *
 * @package         Editoria11y
 * @link            https://itmaybejj.github.io/editoria11y/
 * @author          John Jameson, Princeton University
 * @copyright       2022 The Trustees of Princeton University
 * @license         GPL v2 or later
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Trigger class
// https://wordpress.stackexchange.com/questions/25910/uninstall-activate-deactivate-a-plugin-typical-features-how-to/25979#25979
register_activation_hook( __FILE__, array( 'Ed11y', 'activation' ) );

// TODO: remove tables on deactivation
//register_deactivation_hook( __FILE__, array( 'WCM_Setup_Demo_Class', 'on_deactivation' ) );
//register_uninstall_hook(    __FILE__, array( 'WCM_Setup_Demo_Class', 'on_uninstall' ) );

/**
 * Calls Editoria11y library with site config.
 */
class Ed11y {
	const ED11Y_VERSION = '2.0.012';
	const WP_VERSION    = '1.0.012';

	protected static $instance;

    public static function init() {
        is_null( self::$instance ) AND self::$instance = new self;
        return self::$instance;
    }
	
	/**
	 * Attachs functions to loop.
	 */
	public function __construct() {

		// Set the constants needed by the plugin.
		add_action( 'plugins_loaded', array( &$this, 'constants' ), 1 );

		// Internationalize the text strings used.
		add_action( 'plugins_loaded', array( &$this, 'i18n' ), 2 );

		// Load the functions files.
		add_action( 'plugins_loaded', array( &$this, 'includes' ), 3 );

		// Load the admin files.
		add_action( 'plugins_loaded', array( &$this, 'admin' ), 4 );

		//add_action( 'plugins_loaded', array( &$this, 'ed11y_install' ) );
		

		/* Todo: remove these old meta definitions kept for sample code
		add_action(
			'rest_api_init',
			function () {
				
				// see https://developer.wordpress.org/reference/functions/register_meta/
				
				$ed11y_types = ['post', 'page', 'category', 'media', 'tag', 'user'];
				$ed11y_rules = [
					'altMissing',
					'altNull',
					'altURL',
					'alURLLinked',
					'altImageOf',
					'altImageOfLinked',
					'altDeadspace',
					'altDeadspaceLinked',
					'altEmptyLinked',
					'altLong',
					'altLongLinked',
					'altPartOfLinkWithText',
					'linkNoText',
					'linkTextIsUrl',
					'linkTextIsGeneric',
					'linkDocument',
					'linkNewWindow',
					'tableNoHeaderCells',
					'tableContainsContentHeading',
					'tableEmptyHeaderCell',
					'textPossibleList',
					'textPossibleHeading',
					'textUppercase',
					'embedVideo',
					'embedAudio',
					'embedVisualization',
					'embedTwitter',
					'embedCustom',
					'total',
				];

				$ed11y_count_args = array(
					'type' => 'integer',
					'description' => 'Count for Editoria11y rule hits on page',
					'single' => true,
					'show_in_rest' => true, // todo permission control
					//'auth_callback' => false,
				);

				// https://make.wordpress.org/core/2019/10/03/wp-5-3-supports-object-and-array-meta-types-in-the-rest-api/
				$ed11y_dismissals_args = array(
					'type' => 'array',
					'description' => 'Dismissed alerts for a given rule',
					'single' => true,
					'show_in_rest' => array(
						'schema' => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'string',
							),
						),
					),
				);

				$ed11y_ok_args = array(
					'type' => 'object',
					'description' => 'Dismissed alerts for a given rule',
					'single' => true,
					'show_in_rest' => array(
						'schema' => array(
							'type'       => 'object',
							'additionalProperties' => array(
								'type' => 'array',
								'items'  => array(
									'type' => 'string',
								),
							),
						),
					),
				);
				
				foreach ($ed11y_types as &$type) {
					foreach ($ed11y_rules as &$rule) {
						register_meta( $type, 'ed11y_r_' . $rule, $ed11y_count_args );
						//register_meta( $type, 'ed11y_ok_' . $rule, $ed11y_dismissals_args );
					}
					register_meta( $type, 'ed11y_ok', $ed11y_ok_args );
				}


				/*
				// Field name to register.
				$field = 'ed11y_dismissals';
				register_rest_field(
					'post',
					$field,
					array(
						'get_callback'    => function ( $object ) use ( $field ) {
							// Get field as single value from post meta.
							return get_post_meta( $object['id'], $field, true );
						},
						'update_callback' => function ( $value, $object ) use ( $field ) {
							// Update the field/meta value.
							update_post_meta( $object->ID, $field, $value );
						},
						'schema'          => array(
							'type'        => 'string',
							'arg_options' => array(
								'sanitize_callback' => function ( $value ) {
									// Make the value safe for storage.
									return sanitize_text_field( $value );
								},
								'validate_callback' => function ( $value ) {
									// Valid if it contains exactly 10 English letters.
									return (bool) preg_match( '/\A[a-z]{10}\Z/', $value );
								},
							),
						),
					)
				);
			}
		);*/

	}

	/**
	 * Defines file locations.
	 */
	public function constants() {

		define( 'ED11Y_BASE', plugin_basename( __FILE__ ) );

		// Set constant path to the plugin directory.
		define( 'ED11Y_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );

		// Set the constant path to the plugin directory URI.
		define( 'ED11Y_URI', trailingslashit( plugin_dir_url( __FILE__ ) ) );

		// Set the constant path to the inc directory.
		define( 'ED11Y_INCLUDES', ED11Y_DIR . trailingslashit( 'inc' ) );

		// Set the constant path to the admin directory.
		define( 'ED11Y_ADMIN', ED11Y_DIR . trailingslashit( 'admin' ) );

		// Set the constant path to the assets directory.
		define( 'ED11Y_ASSETS', ED11Y_URI . trailingslashit( 'assets' ) );
	}

	/**
	 * Loads translation files.
	 */
	public function i18n() {
		load_plugin_textdomain( 'ed11y-wp', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Loads the initial files needed by the plugin.
	 */
	public function includes() {
		require_once ED11Y_INCLUDES . 'functions.php';
	}

	/**
	 * Loads admin-only functions.
	 */
	public function admin() {
		if ( is_admin() ) {
			require_once ED11Y_INCLUDES . 'functions.php';
			require_once ED11Y_ADMIN . 'admin.php';
		}
	}
	
	/**
	 * Provides DB table schema.
	 */
	public static function activation() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
        $plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
        check_admin_referer( "activate-plugin_{$plugin}" );

		global $wpdb;
		$message = '<strong>hi</strong> ';

		$charset_collate  = $wpdb->get_charset_collate();

		$table_results    = $wpdb->prefix . 'ed11y_results';
		$table_dismissals = $wpdb->prefix . 'ed11y_dismissals';

		$sql = "
		CREATE TABLE $table_results (
			id int(9) AUTO_INCREMENT NOT NULL,
			result_name varchar(255) NOT NULL,
			result_name_count smallint(4) NOT NULL,
			entity_type varchar(255) NOT NULL,
			page_url varchar(1024) NOT NULL,
			page_title varchar(1024) NOT NULL,
			page_result_count smallint(4) NOT NULL,
			created datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			updated datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			KEY page_url (page_url),
			KEY result_name (result_name),
			KEY entity_type (entity_type)
			) $charset_collate;
		
		CREATE TABLE $table_dismissals (
			id int(9) AUTO_INCREMENT NOT NULL,
			result_name varchar(255) NOT NULL,
			entity_type varchar(255) NOT NULL,
			page_url varchar(1024) NOT NULL,
			page_title varchar(1024) NOT NULL,
			created datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			updated datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			user smallint(6) NOT NULL,
			element_id varchar(2048)  NOT NULL,
			result_key varchar(255) NOT NULL,
			dismissal_status varchar(64) NOT NULL,
			stale tinyint(1) NOT NULL default '0',
			PRIMARY KEY  (id),
			KEY page_url (page_url),
			KEY dismissal_status (dismissal_status),
			KEY user (user)
			) $charset_collate;
		";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		add_option( 'ed11y_db_version', '0.2' );
	}

}

new Ed11y();
