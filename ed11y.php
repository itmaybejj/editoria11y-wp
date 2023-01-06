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
	const ED11Y_VERSION = '1.0.0-alpha';

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
		// Todo.
		add_action( 'plugins_loaded', array( &$this, 'i18n' ), 2 );

		// Load the functions files.
		add_action( 'plugins_loaded', array( &$this, 'includes' ), 3 );

		// Load the admin files.
		add_action( 'plugins_loaded', array( &$this, 'admin' ), 4 );

		// Load the API
		add_action( 'plugins_loaded', array( &$this, 'api' ), 5 );

	}

	/**
	 * Defines file locations.
	 */
	public function constants() {
		global $wpdb;

		define( 'ED11Y_BASE', plugin_basename( __FILE__ ) );

		// Set constant path to the plugin directory.
		define( 'ED11Y_SRC', trailingslashit( plugin_dir_path( __FILE__ ) . 'src/' ) );

		// Set the constant path to the assets directory.
		define( 'ED11Y_ASSETS', trailingslashit( plugin_dir_url( __FILE__ ) . 'assets/' ) );

	}

	/**
	 * Loads translation files.
	 */
	public function i18n() {
		// Todo.
		load_plugin_textdomain( 'ed11y-wp', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Loads page functions.
	 */
	public function includes() {
		require_once ED11Y_SRC . 'functions.php';
	}

	/**
	 * Loads admin functions.
	 */
	public function admin() {
		if ( is_admin() ) {
			require_once ED11Y_SRC . 'functions.php';
			require_once ED11Y_SRC . 'admin.php';
		}
	}

	/**
	 * Creates API routes.
	 */
	public function api() {
		// Load the API.
		require_once ED11Y_SRC . 'controller/class-ed11y-api-results.php';
		$ed11y_api_results = new Ed11y_Api_Results();
		$ed11y_api_results->init();
		require_once ED11Y_SRC . 'controller/class-ed11y-api-dismissals.php';
		$ed11y_api_dismissals = new Ed11y_Api_Dismissals();
		$ed11y_api_dismissals->init();
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
