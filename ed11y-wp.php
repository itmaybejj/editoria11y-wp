<?php

/**
 * Editoria11y, the accessibility quality assurance assistant.
 *
 * Plugin Name:       Editoria11y
 * Plugin URI:        https://itmaybejj.github.io/editoria11y/
 * Description:       Editoria11y is your accessibility quality assurance assistant. Geared towards content authors, Editoria11y straightforwardly identifies accessibility issues at the source.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            John Jameson, Princeton University
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ed11y
 * Domain Path:       /languages
 *
 * @package         Editoria11y
 * @link            https://itmaybejj.github.io/editoria11y/
 * @author          John Jameson, Princeton University
 * @copyright       2022 The Trustees of Princeton University
 * @license         GPL v2 or later
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

class Editoria11y_WP {

    const ED11Y_VERSION = '2.0.01';
    const WP_VERSION = '1.0.01';

    /**
     * PHP5 constructor method.
     */
    public function __construct() {

        // Set the constants needed by the plugin.
        add_action('plugins_loaded', array(&$this, 'constants'), 1);

        // Internationalize the text strings used.
        add_action('plugins_loaded', array(&$this, 'i18n'), 2);

        // Load the functions files.
        add_action('plugins_loaded', array(&$this, 'includes'), 3);

        // Load the admin files.
        add_action('plugins_loaded', array(&$this, 'admin'), 4);
    }

    /**
     * Defines constants used by the plugin.
     */
    public function constants() {

        define( 'ED11Y_BASE', plugin_basename( __FILE__ ));

        // Set constant path to the plugin directory.
        define('ED11Y_DIR', trailingslashit(plugin_dir_path(__FILE__)));

        // Set the constant path to the plugin directory URI.
        define('ED11Y_URI', trailingslashit(plugin_dir_url(__FILE__)));

        // Set the constant path to the inc directory.
        define('ED11Y_INCLUDES', ED11Y_DIR . trailingslashit('inc'));

        // Set the constant path to the admin directory.
        define('ED11Y_ADMIN', ED11Y_DIR . trailingslashit('admin'));

        // Set the constant path to the assets directory.
        define('ED11Y_ASSETS', ED11Y_URI . trailingslashit('assets'));
    }

    /**
     * Loads the translation files.
     */
    public function i18n() {
        load_plugin_textdomain('ed11y-wp', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    /**
     * Loads the initial files needed by the plugin.
     */
    public function includes() {
        require_once(ED11Y_INCLUDES . 'functions.php');
    }

    /**
     * Loads the admin functions and files.
     */
    public function admin() {
        if (is_admin()) {
            require_once(ED11Y_ADMIN . 'admin.php');
        }
    }

}

new Editoria11y_WP;
