<?php
/**
 * Editoria11y, the accessibility quality assurance assistant.
 *
 * Plugin Name:       Editoria11y
 * Plugin URI:        https://itmaybejj.github.io/editoria11y/
 * Version:           0.0.2
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

// Set up tables on activation
// https://wordpress.stackexchange.com/questions/25910/uninstall-activate-deactivate-a-plugin-typical-features-how-to/25979#25979
register_activation_hook( __FILE__, array( 'Ed11y', 'activation' ) );

// TODO: remove tables on deactivation
register_deactivation_hook( __FILE__, array( 'Ed11y', 'uninstall' ) );
register_uninstall_hook( __FILE__, array( 'Ed11y', 'uninstall' ) );

/**
 * Calls Editoria11y library with site config.
 */
class Ed11y {
	const ED11Y_VERSION = '2.0.013';
	const WP_VERSION    = '1.0.013';

	protected static $instance;

	public static function init() {
		is_null( self::$instance ) and self::$instance = new self();
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

		// Load the API
		add_action( 'plugins_loaded', array( &$this, 'api' ), 5 );

		// add_action( 'plugins_loaded', array( &$this, 'ed11y_install' ) );

		/*
		 Todo: remove these old meta definitions kept for sample code
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

		define(
			'ED11Y_RULES',
			array(
				'headingLevelSkipped',
				'headingEmpty',
				'headingIsLong',
				'blockQuoteIsShort',
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
			)
		);

	}

	/**
	 * Loads translation files.
	 */
	public function i18n() {
		load_plugin_textdomain( 'ed11y-wp', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Loads page functions.
	 */
	public function includes() {
		require_once ED11Y_INCLUDES . 'functions.php';
	}

	/**
	 * Loads admin functions.
	 */
	public function admin() {
		if ( is_admin() ) {
			require_once ED11Y_INCLUDES . 'functions.php';
			require_once ED11Y_ADMIN . 'admin.php';
		}
	}

	/**
	 * Creates API routes.
	 */
	public function api() {
		// Load the API.
		require_once ED11Y_DIR . 'src/controller/class-ed11y-api-result.php';
		$ed11y_api_result = new Ed11y_Api_Result();
		$ed11y_api_result->init();
		require_once ED11Y_DIR . 'src/controller/class-ed11y-api-dismiss.php';
		$ed11y_api_dismiss = new Ed11y_Api_Dismiss();
		$ed11y_api_dismiss->init();
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

		$charset_collate = $wpdb->get_charset_collate();

		$table_urls       = $wpdb->prefix . 'ed11y_urls';
		$table_results    = $wpdb->prefix . 'ed11y_results';
		$table_dismissals = $wpdb->prefix . 'ed11y_dismissals';

		$sql = "
		CREATE TABLE $table_urls (
			pid int(9) unsigned AUTO_INCREMENT NOT NULL,
			page_url varchar(255) NOT NULL,
			entity_type varchar(255) NOT NULL,
			page_title varchar(1024) NOT NULL,
			page_total smallint(4) unsigned NOT NULL,
			PRIMARY KEY page_url (page_url),
			KEY pid (pid)
			) $charset_collate;

		CREATE TABLE $table_results (
			pid int(9) unsigned NOT NULL,
			result_key varchar(32) NOT NULL,
			result_count smallint(4) NOT NULL,
			created datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			updated datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			CONSTRAINT result PRIMARY KEY (pid, result_key),
			FOREIGN KEY (pid) REFERENCES $table_urls(pid) ON DELETE CASCADE
			) $charset_collate;
		
		CREATE TABLE $table_dismissals (
			id int(9) unsigned AUTO_INCREMENT NOT NULL,
			pid int(9) unsigned NOT NULL,
			result_key varchar(32) NOT NULL,
			user smallint(6) unsigned NOT NULL,
			element_id varchar(2048)  NOT NULL,
			dismissal_status varchar(64) NOT NULL,
			created datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			updated datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			stale tinyint(1) NOT NULL default '0',
			PRIMARY KEY (id),
			KEY page_url (pid),
			KEY user (user),
			KEY dismissal_status (dismissal_status),
			FOREIGN KEY (pid) REFERENCES $table_urls(pid) ON DELETE CASCADE
			) $charset_collate;
		";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		add_option( 'ed11y_db_version', '0.2' );
	}

	/**
	 * Provides DB table schema.
	 */
	public static function uninstall() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
		check_admin_referer( "activate-plugin_{$plugin}" );

		global $wpdb;

		$table_urls       = $wpdb->prefix . 'ed11y_urls';
		$table_results    = $wpdb->prefix . 'ed11y_results';
		$table_dismissals = $wpdb->prefix . 'ed11y_dismissals';

		$sql = "
		DROP TABLE $table_urls;

		DROP TABLE $table_results;
		
		DROP TABLE $table_dismissals;
		";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

}

new Ed11y();
