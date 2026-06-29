<?php
/**
 * Editoria11y Accessibility Checker
 *
 * Plugin Name:       Editoria11y Accessibility Checker
 * Plugin URI:        https://wordpress.org/plugins/editoria11y-accessibility-checker/
 * Version:           3.0.0-alpha2
 * Requires PHP:      7.4
 * Requires at least: 6.0
 * Tested up to:      7.0
 * Author:            Princeton University, WDS
 * Author URI:        https://editoria11y.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       editoria11y
 * Domain Path:       /languages
 * Description:       User friendly content quality assurance. Checks automatically, highlights issues inline, and provides straightforward, easy-to-understand tips.
 *
 * @package         Editoria11y
 * @link            https://wordpress.org/plugins/editoria11y-accessibility-checker/
 * @author          John Jameson, Princeton University
 * @copyright       2025 The Trustees of Princeton University
 * @license         GPL v2 or later
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Build-precedence yield + single-instance guard (free vs. premium collision).
 *
 * The free build (slug editoria11y-accessibility-checker) and the premium
 * build (slug editoria11y-wp-csa) are the same Freemius product shipped in
 * two separate folders. They share the Editoria11y\ namespace, the ED11Y_*
 * constants, and the ed11ycsa() accessor, and each ships its own Composer
 * autoloader registered with prepend = true — so when both are included in
 * one request the second-loaded build's autoloader jumps to the FRONT of the
 * SPL stack and resolves any not-yet-loaded class from its own src/, serving
 * a mix of stripped (free) and unstripped (premium) copies of the same class.
 * The symptom that surfaced this: the premium-only Settings nav tabs vanished
 * because SettingsPage autoloaded from the free (stripped) copy while Plugin
 * had already loaded from the premium copy.
 *
 * Both builds can legitimately be active in one request on multisite, in
 * BOTH precedence directions:
 *   - premium network-active + free per-site active on a subsite (Freemius's
 *     activate-time handoff misses per-site subsite activations a site admin
 *     made independently); and
 *   - free network-active everywhere + premium per-site active on the
 *     licensed subsites (the per-active-site billing model — free is the
 *     baseline, premium is added only on sites that paid for it).
 * WordPress loads network-active plugins before per-site ones, so load order
 * alone does NOT reliably hand the request to the premium build: in the
 * second case the free build loads first and would otherwise win.
 *
 * Two cooperating guards make the premium build win whenever it is co-active,
 * in either direction:
 *
 *   1. Precedence yield. The free build bails the instant it detects a
 *      co-active premium build (per-site on this blog, or network-active) —
 *      BEFORE defining the sentinel below, so the premium build still finds
 *      the sentinel undefined and boots normally even when it loads second.
 *      Build identity is the folder name (the Freemius slug): stable, and
 *      resolvable before the autoloader without WordPress's plugin registry.
 *
 *   2. Single-instance sentinel. The first build past the yield bails any
 *      sibling that still reaches this point — before requiring our vendor
 *      tree (so no second autoloader registers) and before fs_dynamic_init()
 *      (so the SDK isn't double-initialized for one product id). Exactly one
 *      build then owns the request with an internally consistent class set.
 *
 * The redundant still-"active" free entry on premium-everywhere installs is
 * cleaned up lazily by Editoria11y\CoActivationGuard; the per-active-site
 * baseline (network-active free) is intentional and is left untouched.
 *
 * Plain (un-gated) code so it survives into the free build too — the free
 * build is the one that does the yielding, and both builds must run the same
 * sentinel check against the same constant.
 */
$ed11y_premium_basename = 'editoria11y-wp-csa/editoria11y.php';
if (
	'editoria11y-accessibility-checker' === basename( __DIR__ )
	&& (
		in_array( $ed11y_premium_basename, (array) get_option( 'active_plugins', array() ), true )
		|| (
			is_multisite()
			&& array_key_exists(
				$ed11y_premium_basename,
				(array) get_site_option( 'active_sitewide_plugins', array() )
			)
		)
	)
) {
	return;
}

if ( defined( 'ED11Y_PLUGIN_FILE' ) ) {
	return;
}
define( 'ED11Y_PLUGIN_FILE', __FILE__ );

/*
 * Composer autoload.
 *
 * Required for both the PSR-4 mapping (Editoria11y\* → src/) and the
 * Freemius SDK's autoload-files entry, which is what defines
 * fs_dynamic_init(). WordPress does not auto-load plugin vendor trees on
 * its own — without this require the production site crashes at
 * `fs_dynamic_init()` below. PHPUnit happens to load vendor/autoload.php
 * via its own launcher, which is why the test suite was green while the
 * production load path crashed.
 *
 * file_exists() guard: a misconfigured CI build that ships without vendor/
 * (or a `composer install --no-dev` that somehow excluded freemius) should
 * fail loudly with an admin notice rather than fataling the site.
 */
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	add_action(
		'admin_notices',
		function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p>'
				. esc_html__(
					'Editoria11y is missing its vendored dependencies. Run "composer install" inside the plugin directory or reinstall the plugin from a complete release archive.',
					'editoria11y'
				)
				. '</p></div>';
		}
	);
	return;
}

/*
 * Plugin path constants.
 *
 * Defined here at file-load (rather than inside a class method on
 * plugins_loaded:1) so PSR-4 autoload-resolved classes can use ED11Y_SRC /
 * ED11Y_BASE directly and __FILE__ refers to the actual plugin entry.
 */
if ( ! defined( 'ED11Y_BASE' ) ) {
	define( 'ED11Y_BASE', plugin_basename( __FILE__ ) );
	define( 'ED11Y_SRC', trailingslashit( plugin_dir_path( __FILE__ ) . 'src/' ) );
	define( 'ED11Y_ASSETS', trailingslashit( plugin_dir_url( __FILE__ ) . 'assets/' ) );
}

if ( ! function_exists( 'ed11ycsa' ) ) {
	/**
	 * Freemius wrapper init.
	 *
	 * Kept as a global function (rather than a method on Editoria11y) because
	 * Freemius's idiom is a bare-named singleton accessor and downstream
	 * src/ files call it as `ed11ycsa()->can_use_premium_code__premium_only()`.
	 * Lives in this file (separate from the class declarations in src/) so
	 * the file does not mix function and OO declarations
	 * (Universal.Files.SeparateFunctionsFromOO).
	 */
	function ed11ycsa() {
		global $ed11ycsa;

		if ( ! isset( $ed11ycsa ) ) {
			// Activate multisite network integration. The constant name is
			// dictated by the Freemius SDK (`WP_FS__PRODUCT_<id>_MULTISITE`)
			// — not a plugin-prefixed name we get to choose.
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
			if ( ! defined( 'WP_FS__PRODUCT_26217_MULTISITE' ) ) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
				define( 'WP_FS__PRODUCT_26217_MULTISITE', true );
			}

			// fs_dynamic_init() is defined by the Freemius SDK's
			// autoload-files entry, which runs when vendor/autoload.php
			// is required (see the file-level require_once above).

			$ed11ycsa = fs_dynamic_init(
				array(
					'id'                  => '26217',
					'slug'                => 'editoria11y-accessibility-checker',
					'premium_slug'        => 'editoria11y-wp-csa',
					'type'                => 'plugin',
					'public_key'          => 'pk_5dd521a7afe891a30befe5040b0a6',
					'is_premium'          => false,
					'is_live'             => true,
					// Freemium, not premium-only: the free build is a fully
					// functional accessibility checker, with CSA features gated
					// behind the license/markers. is_premium_only => true would
					// make the SDK report has_free_plan() === false and wall the
					// whole free build behind a license. The strip flips
					// is_premium => false for the free build; is_premium_only
					// stays false in both builds.
					'has_premium_version' => true,
					// is_premium_only is intentionally omitted; the SDK defaults
					// it to false, which is the freemium behavior we want.
					'has_addons'          => false,
					'has_paid_plans'      => true,
					'is_org_compliant'    => true,
					// Premium build: license holders connect their account, so the
					// SDK's opt-in/connection flow stays enabled here (anonymous_mode
					// => false). The strip flips this to true for the free build so
					// WP.org users are never pestered with opt-in nags and the plugin
					// runs fully anonymous. Mirrors the is_premium flip.
					'anonymous_mode'      => true,
					// Automatically removed in the free version. If you're not using the
					// auto-generated free version, delete this line before uploading to wp.org.
					'trial'               => array(
						'days'               => 30,
						'is_require_payment' => true,
					),
					// Freemius menu unified with the plugin's own settings page.
					// menu.slug === the `add_options_page( …, 'ed11y' )` slug
					// (Form\SettingsPage::register_settings_menu), so the SDK
					// ATTACHES to a page the plugin already registers rather than
					// owning a slug of its own. This is load-bearing: the `ed11y`
					// settings page is registered in EVERY opt-in state, so every
					// SDK redirect that targets the menu slug — the post-skip
					// redirect (`after_skip_url`), `connect_again()` on reconnect,
					// account links — lands on a real registered page instead of
					// 403ing. A distinct slug (the old top-level `ed11ycsa`) only
					// had a registered page in activation mode, so skip / reconnect
					// / anonymous all 403'd. In activation mode the SDK overrides
					// the settings-page callback with its opt-in screen; otherwise
					// the plugin's own settings render. The SDK's account / pricing
					// / contact pages live at `ed11y-account` / `-pricing` /
					// `-contact`; the nav-tab row links to them and the redundant
					// sidebar submenus are hidden in CSS.
					'menu'                => array(
						'slug'    => 'ed11y',
						'support' => false,
						'network' => true,
						'parent'  => array(
							'slug' => 'options-general.php',
						),
					),
				)
			);

			// Replace unprofessional SDK strings.
			\Editoria11y\FreemiusOverrides::apply( $ed11ycsa );

			// Catch-all for stray `options-general.php?page=ed11y-contact`
			// links the SDK emits from places we can't intercept
			// individually (React pricing app, deactivation modal,
			// contact_url('bug') notices, etc.). Without this fallback
			// the link 403s whenever the SDK chose not to register
			// the contact submenu in the current admin context — most
			// visibly per-site admin on a network-active install in
			// activation mode.
			try {
				\Editoria11y\FreemiusContactFallback::register();
			} catch ( \Throwable $e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- one-shot diagnostic, gated by the catch arm above.
				error_log( '[Editoria11y] FreemiusContactFallback::register() failed: ' . $e->getMessage() );
			}

			// Pricing-page UX: hide the SDK's hardcoded "Need more sites?"
			// box (whose link to options-general.php?page=ed11y-contact 403s
			// whenever that submenu wasn't registered) and inject our own
			// "Support the project" / CSA-supporters banner in its place.
			// Registered in BOTH builds: this is a freemium plugin
			// (has_paid_plans => true), so a free user who clicks Upgrade
			// lands on the SDK pricing page too — the broken-link fix and
			// the conversion banner belong there. FreemiusPricingPage.php
			// and assets/css/editoria11y-pricing.css are therefore kept off
			// the premium-only strip list and ship in the free build.
			//
			// Throwable boundary: apply() only registers SDK filters and
			// should never throw on a healthy SDK, but if a future
			// self-update arbitration drops in a build that renames
			// `add_filter` (or otherwise breaks our seam) we want a missing
			// banner — not a fataled admin request.
			try {
				\Editoria11y\FreemiusPricingPage::apply( $ed11ycsa );
			} catch ( \Throwable $e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- one-shot diagnostic, gated by the catch arm above.
				error_log( '[Editoria11y] FreemiusPricingPage::apply() failed: ' . $e->getMessage() );
			}

			// Restrict the SDK's network-admin pages to super-admins and
			// pin the "Activate License" modal to all-sites mode so very
			// large multisites are not enumerated row-by-row in the DOM.

		}

		return $ed11ycsa;
	}

	// Init Freemius.
	ed11ycsa();
	// Signal that SDK was initiated.
	do_action( 'ed11ycsa_loaded' );
}

/*
 * PSR-4 autoloading is provided by Composer (see composer.json `autoload`
 * → psr-4 mapping `Editoria11y\` → `src/`). vendor/autoload.php is required
 * unconditionally at the top of this file (see the file-level
 * `require_once __DIR__ . '/vendor/autoload.php'` near the top), so FQCNs
 * below resolve via the spl_autoload registration without manual
 * require_once. In the PHPUnit context the test launcher has typically
 * already loaded it; the require_once at the top is a guarded no-op there.
 */

// Lifecycle hooks delegate to \Editoria11y\Installer (data side).
register_activation_hook( __FILE__, array( '\\Editoria11y\\Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\Editoria11y\\Installer', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( '\\Editoria11y\\Installer', 'uninstall' ) );

// Drop the plugin's three per-blog tables when a multisite subsite is
// deleted. `wpmu_drop_tables` is WordPress's canonical extensibility
// seam for this — fired by wp_uninitialize_site() with the deleted
// blog's ID. No-op on single-site (filter never fires there).
add_filter( 'wpmu_drop_tables', array( '\\Editoria11y\\Installer', 'wpmu_drop_tables_filter' ), 10, 2 );

// Register the 15-minute schedule used by the dismissal element_id rehash worker.
add_filter(
	'cron_schedules',
	function ( $schedules ) {
		if ( ! isset( $schedules[ \Editoria11y\Installer::REHASH_CRON_SCHEDULE ] ) ) {
			$schedules[ \Editoria11y\Installer::REHASH_CRON_SCHEDULE ] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every fifteen minutes (Editoria11y rehash)', 'editoria11y' ),
			);
		}
		return $schedules;
	}
);
add_action( \Editoria11y\Installer::REHASH_CRON_HOOK, array( '\\Editoria11y\\Installer', 'run_rehash_cron' ) );

// Register the network-license activation worker's cron callback at
// file-load time so wp-cron loopback requests find the hook regardless
// of whether plugins_loaded has fired. CSA-only — the free build's
// preprocessor strips both the class file and the gated runtime check.
//
// Throwable boundary mirrors the FreemiusAccessControl::apply() guard
// above: if the worker class file fails to autoload (corrupted vendor
// tree, partial deploy) we want WP-Cron to keep running for everything
// else on the site rather than fatal at plugin file-load.


// Register the network-defaults backfill worker + site-creation seeder.
// Free-build-safe — the seeder writes both the free and the CSA options
// (CSA branch gated inside the worker class), and the backfill worker is
// the propagation half of every `mode = 'all'` network default. Same
// Throwable boundary as the license worker registration above.
try {
	\Editoria11y\Form\NetworkDefaultsWorker::register();
} catch ( \Throwable $e ) {
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- one-shot diagnostic.
	error_log( '[Editoria11y] NetworkDefaultsWorker::register() failed: ' . $e->getMessage() );
}

new \Editoria11y\Plugin();
