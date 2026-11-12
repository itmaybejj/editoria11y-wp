<?php
/**
 * Boot-time check that the loaded `Freemius` class is one we can drive.
 *
 * Why this exists:
 *   The SDK's start.php (run by Composer's autoload-files entry when
 *   editoria11y.php requires vendor/autoload.php) returns early WITHOUT
 *   loading our bundled SDK whenever a class named `Freemius` already
 *   exists — it assumes that class is another plugin's copy of the real
 *   SDK, which normal version arbitration would have picked anyway
 *   (vendor/freemius/wordpress-sdk/start.php, the `class_exists( 'Freemius' )`
 *   early return right after the newest-SDK reorder block).
 *
 *   Nulled plugins and "disable Freemius" mu-plugins ship partial
 *   stand-ins under that same class name. One of them passed the check,
 *   implemented `add_filter()` but not `add_action()`, and the first
 *   `ed11ycsa()->add_action()` call fataled every admin request on the
 *   network — including network/plugins.php, the page an admin would use
 *   to deactivate the culprit.
 *
 *   editoria11y.php calls {@see check()} immediately before defining
 *   `ed11ycsa()`. On failure the plugin stops booting and an admin notice
 *   names the file that declared the class, instead of fataling.
 *
 * Keeping the lists current:
 *   {@see REQUIRED_METHODS} and {@see REQUIRED_FUNCTIONS} must cover every
 *   SDK method / function the plugin calls. Test_Editoria11y_Freemius_Sdk_Guard
 *   scans src/ and editoria11y.php for call sites and fails when a new one
 *   is missing here, and also fails when an entry here does not exist in
 *   the bundled SDK (typo / upstream rename).
 *
 * Build scope: free-build-safe. Runs in both builds, so it must NOT be
 * listed under `@fs_premium_only` in editoria11y.php. The `__premium_only`
 * names below are string literals, which the strip leaves untouched.
 *
 * @package Editoria11y
 */

namespace Editoria11y;

use ReflectionClass;
use ReflectionException;

defined( 'ABSPATH' ) || exit;

/**
 * Detects an incompatible `Freemius` class and reports it to admins.
 */
final class FreemiusSdkGuard {

	/** Class name the SDK declares and fs_dynamic_init() instantiates. */
	const SDK_CLASS = 'Freemius';

	/**
	 * Global SDK functions the plugin calls directly.
	 */
	const REQUIRED_FUNCTIONS = array(
		'fs_asset_url',
		'fs_dynamic_init',
		'fs_is_network_admin',
		'fs_text_inline',
		'fs_text_x_inline',
	);

	/**
	 * Instance methods the plugin calls on the object `ed11ycsa()` returns.
	 *
	 * Includes CSA-only callers (NetworkLicenseWorker, FreemiusAccessControl):
	 * the real SDK has all of these in both builds, so requiring them in the
	 * free build costs nothing and keeps one list for both.
	 */
	const REQUIRED_METHODS = array(
		'_get_admin_page_url',
		'add_action',
		'add_filter',
		'apply_filters',
		'can_use_premium_code__premium_only',
		'get_install_by_blog_id',
		'get_reconnect_url',
		'get_site_info',
		'get_slug',
		'get_trial_url',
		'get_upgrade_url',
		'has_addons',
		'has_paid_plan',
		'has_trial_plan',
		'is__premium_only',
		'is_anonymous',
		'is_installed_on_site',
		'is_network_active',
		'is_network_delegated_connection',
		'is_paid_trial',
		'is_paying',
		'is_pending_activation',
		'is_premium',
		'is_registered',
		'is_trial_utilized',
		'is_whitelabeled',
		'opt_in',
		'override_i18n',
		'switch_to_blog',
	);

	/** How many missing names the notice lists before truncating. */
	const NOTICE_MAX_LISTED = 5;

	/**
	 * Problems found by the last {@see check()} call.
	 *
	 * @var string[]
	 */
	private static $problems = array();

	/**
	 * Class name inspected by the last {@see check()} call.
	 *
	 * @var string
	 */
	private static $class_name = self::SDK_CLASS;

	/**
	 * Verify the loaded SDK; on failure, queue the admin notice.
	 *
	 * @param string $class_name Class to inspect. Only tests pass anything
	 *                           other than the default.
	 * @return bool True when the SDK is usable and the plugin may boot.
	 */
	public static function check( string $class_name = self::SDK_CLASS ): bool {
		self::$class_name = $class_name;
		self::$problems   = self::find_problems( $class_name );

		if ( array() === self::$problems ) {
			return true;
		}

		add_action( 'admin_notices', array( __CLASS__, 'render_notice' ) );
		add_action( 'network_admin_notices', array( __CLASS__, 'render_notice' ) );
		return false;
	}

	/**
	 * List what the loaded SDK is missing.
	 *
	 * `method_exists()` rather than `is_callable()`: a stand-in that answers
	 * everything through `__call()` returns null for every query, which is
	 * no more usable than a missing method.
	 *
	 * @param string $class_name Class to inspect.
	 * @return string[] Human-readable missing names (`Class`, `fn()`,
	 *                  `Class::method()`); empty when nothing is missing.
	 */
	public static function find_problems( string $class_name = self::SDK_CLASS ): array {
		// Autoload off: if no SDK class loaded, Composer must not be asked
		// to find one now, after start.php already made its decision.
		if ( ! class_exists( $class_name, false ) ) {
			return array( $class_name );
		}

		$problems = array();
		foreach ( self::REQUIRED_FUNCTIONS as $function_name ) {
			if ( ! function_exists( $function_name ) ) {
				$problems[] = $function_name . '()';
			}
		}
		foreach ( self::REQUIRED_METHODS as $method ) {
			if ( ! method_exists( $class_name, $method ) ) {
				$problems[] = $class_name . '::' . $method . '()';
			}
		}
		return $problems;
	}

	/**
	 * Admin notice explaining why the plugin is not running.
	 *
	 * Multisite: shown only to users who manage network plugins, on both
	 * site and network admin screens. The conflicting code is usually a
	 * network-active or must-use plugin, which a subsite admin cannot
	 * remove, so showing it to them would be noise.
	 */
	public static function render_notice(): void {
		if ( array() === self::$problems ) {
			return;
		}
		$capability = is_multisite() ? 'manage_network_plugins' : 'activate_plugins';
		if ( ! current_user_can( $capability ) ) {
			return;
		}

		if ( ! class_exists( self::$class_name, false ) ) {
			$message = esc_html__(
				'Editoria11y is not running because the Freemius SDK did not load. Reinstall the plugin from a complete release archive.',
				'editoria11y'
			);
		} else {
			$listed = array_slice( self::$problems, 0, self::NOTICE_MAX_LISTED );
			$list   = implode( ', ', array_map( 'esc_html', $listed ) );
			if ( count( self::$problems ) > self::NOTICE_MAX_LISTED ) {
				$list .= ', …';
			}
			$message = sprintf(
				/* translators: 1: path of the file that declared the conflicting Freemius class, 2: comma-separated list of missing PHP functions and methods */
				esc_html__(
					'Editoria11y is not running. Another plugin or theme loaded an incompatible copy of the Freemius SDK from %1$s, which is missing code Editoria11y needs: %2$s. Deactivate or update that plugin or theme, then reload this page.',
					'editoria11y'
				),
				'<code>' . esc_html( self::class_source( self::$class_name ) ) . '</code>',
				'<code>' . $list . '</code>'
			);
		}

		echo '<div class="notice notice-error"><p>' . wp_kses( $message, array( 'code' => array() ) ) . '</p></div>';
	}

	/**
	 * File that declared the class, relative to the WordPress root when
	 * it lives under it.
	 *
	 * @param string $class_name Loaded class name.
	 */
	private static function class_source( string $class_name ): string {
		$unknown = __( 'an unknown location', 'editoria11y' );
		try {
			$file = ( new ReflectionClass( $class_name ) )->getFileName();
		} catch ( ReflectionException $e ) {
			return $unknown;
		}
		if ( ! is_string( $file ) || '' === $file ) {
			return $unknown;
		}

		$file = wp_normalize_path( $file );
		$root = wp_normalize_path( ABSPATH );
		if ( 0 === strpos( $file, $root ) ) {
			return substr( $file, strlen( $root ) );
		}
		return $file;
	}
}
