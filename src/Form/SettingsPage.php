<?php
/**
 * Editoria11y Settings page lifecycle.
 *
 * Owns:
 *   - The wp-admin entry points (Settings → Editoria11y, Settings →
 *     Editoria11y License, the cross-page nav-tab row).
 *   - WP Settings API registration: `register_setting()` plus the
 *     `add_settings_section()` / `add_settings_field()` calls that the
 *     `do_settings_sections('ed11y')` call in `render_page()` later
 *     consults.
 *   - The page render shell. The form body is built by the WP Settings
 *     API; all per-field markup lives on `SettingsFields` (callbacks
 *     resolve via `array( SettingsFields::class, 'method_name' )`).
 *
 * The companion classes wired by `Editoria11y\Plugin::admin()`:
 *   - `MigrationPanel` (admin notice + AJAX worker)
 *   - `SettingsValidator` (sanitize callback, registered here)
 *   - `Editoria11y\Admin\Dashboard` and `Editoria11y\Admin\CsvExport`
 *   - `CustomRulesPage` (sibling submenu)
 *
 * @package Editoria11y
 */

namespace Editoria11y\Form;

use Editoria11y\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the main settings page and the surrounding admin surfaces.
 */
class SettingsPage {

	/**
	 * Wire all admin_menu / admin_init / admin_head hooks the settings
	 * page needs.
	 *
	 * Called from `Editoria11y\Plugin::admin()`.
	 */
	public function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_settings_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_sections_and_fields' ) );
		self::register_freemius_account_filters();
	}

	/**
	 * Make the Freemius-rendered Account / Add-ons / Upgrade / Free
	 * Trial pages render under the same nav-tab row as our own
	 * Settings and Custom Rules pages.
	 *
	 * Two filters do the work:
	 *
	 *   - `hide_account_tabs` suppresses the secondary
	 *     `<h2 class="nav-tab-wrapper">` that account.php would
	 *     otherwise emit (vendor/.../templates/account.php near the
	 *     top of the .wrap.fs-section div). Without this we'd render
	 *     two horizontal tab rows on the Account page.
	 *
	 *   - `templates/account.php` is the wrapping filter Freemius
	 *     added "to allow developers wrapping the template in custom
	 *     HTML (e.g. within a wizard/tabs)" — see
	 *     Freemius::_account_page_render(). We use it to inject our
	 *     <h1> + nav-tab row into the rendered account.php output.
	 *
	 * The Add-ons / Upgrade / Free Trial destinations Freemius would
	 * have shown in its own row are re-emitted as siblings in
	 * `render_nav_tabs()`, so suppressing the Freemius row does not
	 * make any page unreachable from the UI.
	 */
	public static function register_freemius_account_filters() {
		if ( ! function_exists( 'ed11ycsa' ) ) {
			return;
		}
		// Premium-only: all three hooks drive the Freemius account / connect
		// screens. The free build runs fully anonymous (anonymous_mode => true,
		// no opt-in) and renders neither screen, so these handlers would be dead
		// there — and `hide_account_tabs` / `wrap_account_template` carry
		// "License & Account" chrome while `render_obtain_license_notice` carries
		// license-entry copy the WP.org build shouldn't ship. The free build's
		// upgrade funnel lives on the Settings page instead, in
		// `render_upgrade_notice()`. The preprocessor strips this whole block.
	}

	/**
	 * Banner shown on the Freemius connect / activation screen (the opt-in
	 * shown on `options-general.php?page=ed11y`), pointing premium users at the
	 * supporter / license path.
	 *
	 * Premium-only. Registered via the `connect/before` Freemius action in
	 * `register_freemius_account_filters()`, which is itself gated behind
	 * is__premium_only(): the free build runs fully anonymous (anonymous_mode,
	 * no opt-in) and never shows a connect screen, so this notice has nothing to
	 * render on there. The free-build equivalent is `render_upgrade_notice()` on
	 * the Settings page. The whole body is wrapped in is__premium_only() so the
	 * preprocessor strips the license-entry copy from the WP.org build entirely.
	 *
	 * The premium (CSA) connect screen DOES expose a license-key field
	 * (require_license_key can be true), so the "enter your existing license
	 * key" path is real here; the editoria11y.com/codes pointer covers how to
	 * *obtain* a key (the SDK only explains how to *enter* one). Copy is left
	 * verbatim so its existing 29-locale translations stay valid.
	 *
	 * The `connect/before` action fires just above
	 * `<div id="fs_connect" class="wrap">`, so we wrap our own `.wrap` for the
	 * standard admin gutter. Links are injected via printf `%s` placeholders
	 * rather than living inside the translated string so URLs stay in code (not
	 * editable by translators) and translators only see the human-readable copy.
	 */
	public static function render_obtain_license_notice() {
	}

	/**
	 * Free-build upgrade funnel, shown at the top of the Settings page.
	 *
	 * Replaces the supporter pitch that used to ride on the Freemius connect
	 * screen via `render_obtain_license_notice()`. The free build runs fully
	 * anonymous (anonymous_mode, no opt-in), so that screen never renders; the
	 * pitch needs a home that doesn't depend on it, and the Settings page loads
	 * in every state.
	 *
	 * Gated by a RUNTIME `is_premium()` check, NOT the is__premium_only() strip
	 * marker: the method ships in both builds but only paints in the free build,
	 * which has no License & Account tab / connect screen carrying the upgrade
	 * path. The premium build already has that funnel, so the notice stays
	 * hidden there to avoid a redundant pitch on the Settings page.
	 *
	 * Copy mirrors the free branch formerly in render_obtain_license_notice():
	 * no license-entry promise (the free build exposes no license field), only
	 * the supporter path. The strings are reused verbatim so their translations
	 * carry over unchanged.
	 */
	public static function render_upgrade_notice() {
		if ( ! function_exists( 'ed11ycsa' ) || ed11ycsa()->is_premium() ) {
			return;
		}
		$codes_link = sprintf(
			'<a href="%s" target="_blank" rel="noopener">%s</a>',
			esc_url( 'https://editoria11y.com/codes/' ),
			esc_html__( 'becoming a supporter', 'editoria11y' )
		);
		?>
		<div class="notice notice-info ed11y-upgrade-notice" style="margin-left: 0; margin-right: 0;">
			<p>
				<?php esc_html_e( 'This site is running the free version of Editoria11y from WordPress.org. The community-supported (CSA) features like developer and contrast tests, the custom test builder and site crawler, are not yet installed.', 'editoria11y' ); ?>
			</p>
			<p>
				<?php
				printf(
					/* translators: %s: link reading "becoming a supporter". */
					esc_html__( 'Unlock the community-supported features and help ongoing work on the free version by %s.', 'editoria11y' ),
					$codes_link // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Inject our admin chrome into the Freemius account.php output.
	 *
	 * The Freemius template opens with `<div class="wrap fs-section">`
	 * and goes straight into postboxes; we splice an <h1> and the
	 * shared nav-tab row in between so the page reads as the third
	 * tab of our settings UI rather than a standalone Freemius screen.
	 *
	 * @param string $html Rendered account.php output passed in by
	 *                     `fs_templates/account.php_<slug>`.
	 * @return string
	 */
	public static function wrap_account_template( $html ) {
		// Premium-only body (sentinel-return, not `else`): the filter that calls
		// this is registered only in the premium build, and the injected chrome
		// carries "License & Account" copy. The free build returns $html
		// untouched — though in practice it never reaches here, as the account
		// page that triggers the filter is never rendered in anonymous mode.

		return $html;
	}

	/**
	 * Allowed-HTML allowlist for `wp_kses` calls inside the settings page
	 * field-help text.
	 *
	 * @return array<string, array<string, true>> wp_kses-shape allowlist.
	 */
	public static function allowed_html() {
		return array(
			'em'     => array(),
			'strong' => array(),
			'code'   => array(),
			'br'     => array(),
			'p'      => array(),
		);
	}

	/**
	 * Register Settings → Editoria11y Settings.
	 */
	public static function register_settings_menu() {
		$page = add_options_page(
			esc_html__( 'Editoria11y', 'editoria11y' ),
			esc_html__( 'Editoria11y', 'editoria11y' ),
			'manage_options',
			'ed11y',
			array( __CLASS__, 'render_page' )
		);
		if ( ! $page ) {
			return;
		}
		// Provided hook_suffix that's returned to add scripts only on
		// settings page.
		add_action( 'load-' . $page, array( __CLASS__, 'enqueue_styles_scripts' ) );
	}

	/**
	 * Render the nav-tab row shown at the top of the plugin's admin pages.
	 *
	 * The Custom Rules tab only renders when CSA is active — without a
	 * license the page is a permanent upgrade-prompt and showing it as a
	 * sibling tab would imply parity with the always-available Settings /
	 * License pages.
	 *
	 * @param string $current Slug of the active page.
	 */
	public static function render_nav_tabs( $current ) {
		// Settings is the only tab in the free build, which runs fully anonymous
		// (anonymous_mode => true, no opt-in): it never registers a Freemius
		// account page and exposes no license entry, so "License & Account" has
		// no live destination there — the slug would only route back into the
		// opt-in flow the free build no longer has. The Freemius preprocessor
		// strips the whole if-block below, taking the account / CSA tabs with it.
		$tabs = array(
			'ed11y' => __( 'Settings', 'editoria11y' ),
		);

		// On network-activated installs the Freemius "License & Account"
		// destination resolves to a network-admin URL (see tab_url() /
		// Freemius's _get_admin_page_url). A non-super-admin who clicked it
		// would be redirected into network admin and either 403 against the
		// access guards in FreemiusAccessControl or land on a page with no
		// actionable content for them. Drop it from the row entirely so the
		// destination link is never visible. Our own ed11y-* pages stay: they
		// are per-site Settings pages, not network-admin Freemius pages.
		if ( self::is_network_locked_for_user() ) {
			unset( $tabs['ed11y-account'] );
		}
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			$class = ( $slug === $current ) ? 'nav-tab nav-tab-active' : 'nav-tab';
			printf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( self::tab_url( $slug ) ),
				esc_attr( $class ),
				esc_html( $label )
			);
		}
		self::render_freemius_extra_tabs();
		echo '</h2>';
	}

	/**
	 * Resolve the admin URL for a nav-tab slug.
	 *
	 * With the Freemius menu unified onto our settings page, every nav-tab is
	 * an `ed11y*` page under `options-general.php` — except the Freemius
	 * "License & Account" tab (`ed11y-account`), whose live destination depends
	 * on the opt-in state because the SDK only registers an account page once
	 * the user is connected. We route that one specially; everything else is
	 * one of our own pages and resolves with a plain `admin_url()`.
	 *
	 * The special-case routing is premium-only: the fully-anonymous free build
	 * has no `ed11y-account` tab, so after the strip every slug resolves via
	 * `admin_url()`.
	 *
	 * @param string $slug Tab slug from {@see render_nav_tabs()}.
	 */
	private static function tab_url( string $slug ): string {
		// Premium-only: ed11y-account is the only nav-tab whose destination
		// isn't one of our own pages — it resolves to the Freemius account /
		// reconnect / opt-in screens depending on connection state. None of
		// those exist in the fully-anonymous free build (which has no account
		// tab to route here in the first place), so the preprocessor strips
		// this block and every free-build slug falls through to admin_url().

		return admin_url( 'options-general.php?page=' . $slug );
	}

	/**
	 * Append the Add-ons / Upgrade / Free Trial tabs Freemius would
	 * normally render at the top of its Account page.
	 *
	 * `register_freemius_account_filters()` suppresses that row via
	 * the `hide_account_tabs` filter, so without re-emitting these
	 * destinations here they would only be reachable via deep links
	 * inside the Account body. The visibility gating mirrors
	 * vendor/.../templates/account.php so we surface exactly what
	 * Freemius itself would have surfaced — no more, no less.
	 *
	 * Shown once the user has either connected (registered) OR skipped the
	 * opt-in (anonymous) — both are settled states where the upgrade path is
	 * meaningful and the pricing page is reachable. Skipped only in fresh
	 * activation mode, where the opt-in (not an upgrade pitch) is the focus.
	 *
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity) The trial-tab and
	 *   upgrade-tab visibility branches are extracted to helpers, but
	 *   PHPMD's pdepend analyzer over-counts when the plugin's
	 *   `editoria11y.php` bootstrap file is included in the same
	 *   analysis pass (see scripts/test.sh).
	 */
	private static function render_freemius_extra_tabs() {
		if ( ! function_exists( 'ed11ycsa' ) ) {
			return;
		}
		// Add-Ons / Upgrade / Free Trial all land on network-admin pages
		// when the plugin is network-active; same reasoning as the main
		// nav-tab gate above — keep the destinations out of the row for
		// users who can't act on them.
		if ( self::is_network_locked_for_user() ) {
			return;
		}
		$freemius = ed11ycsa();
		// Shown for users who have connected (registered), skipped the opt-in
		// (anonymous), or opted in but not yet confirmed their email (pending
		// activation). All three are settled states using the free build where
		// the upgrade/pricing page is reachable and surfacing it is the whole
		// point of the freemium funnel. Hidden only in fresh activation mode
		// (none of the three), where the opt-in is the focus — and where the
		// settings page (and this row) isn't rendered anyway.
		if (
			! $freemius->is_registered() &&
			! $freemius->is_anonymous() &&
			! $freemius->is_pending_activation()
		) {
			return;
		}

		$slug         = $freemius->get_slug();
		$show_upgrade = self::should_show_upgrade_tab( $freemius );

		if ( $freemius->has_addons() ) {
			printf(
				'<a href="%s" class="nav-tab">%s</a>',
				esc_url( $freemius->_get_admin_page_url( 'addons' ) ),
				esc_html( fs_text_inline( 'Add-Ons', 'add-ons', $slug ) )
			);
		}

		if ( $show_upgrade ) {
			printf(
				'<a href="%s" class="nav-tab">%s</a>',
				esc_url( $freemius->get_upgrade_url() ),
				esc_html( fs_text_x_inline( 'Upgrade', 'verb', 'upgrade', $slug ) )
			);
			self::maybe_render_free_trial_tab( $freemius, $slug );
		}
	}

	/**
	 * Whether the Upgrade tab applies to the current Freemius state.
	 *
	 * Pulled out of `render_freemius_extra_tabs()` so PHPMD's cyclomatic
	 * counter doesn't trip on the four-clause AND chain inside what was
	 * already a multi-branch method.
	 *
	 * @param object $freemius Freemius instance from `ed11ycsa()`.
	 * @return bool
	 */
	private static function should_show_upgrade_tab( $freemius ): bool {
		$has_paid_plan = $freemius->apply_filters( 'has_paid_plan_account', $freemius->has_paid_plan() );
		return (
			! $freemius->is_whitelabeled() &&
			$has_paid_plan &&
			! $freemius->is_paying() &&
			! $freemius->is_paid_trial()
		);
	}

	/**
	 * Emit the Free Trial tab if the current state allows it.
	 *
	 * Sibling helper to `should_show_upgrade_tab()`; only ever called
	 * inside the `$show_upgrade` branch above. Mirrors the visibility
	 * gating in vendor/.../templates/account.php.
	 *
	 * @param object $freemius Freemius instance.
	 * @param string $slug     Plugin slug used by Freemius's i18n helpers.
	 */
	private static function maybe_render_free_trial_tab( $freemius, string $slug ): void {
		if (
			! $freemius->apply_filters( 'show_trial', true ) ||
			$freemius->is_trial_utilized() ||
			! $freemius->has_trial_plan()
		) {
			return;
		}
		printf(
			'<a href="%s" class="nav-tab">%s</a>',
			esc_url( $freemius->get_trial_url() ),
			esc_html( fs_text_inline( 'Free Trial', 'free-trial', $slug ) )
		);
	}

	/**
	 * Whether the current user is on a network-activated install but
	 * lacks super-admin rights.
	 *
	 * When true, every `ed11ycsa*` destination on the nav-tab row would
	 * route into network admin — a surface that either 403s for the
	 * user (see FreemiusAccessControl::guard_network_admin_pages /
	 * the SDK PR) or has no actionable content for them. Both
	 * render_nav_tabs and render_freemius_extra_tabs gate on this so
	 * the link is never displayed in the first place.
	 *
	 * `is_network_active()` here is the SDK accessor — it returns true
	 * when the plugin is active across the network, regardless of
	 * delegated-connection state. Lives in this class (not
	 * FreemiusAccessControl) because the helper is also exercised by
	 * the free-build nav-tab path; the gate is read-only and survives
	 * the free-build strip.
	 *
	 * @return bool
	 */
	private static function is_network_locked_for_user(): bool {
		return (
			function_exists( 'ed11ycsa' )
			&& is_multisite()
			&& ed11ycsa()->is_network_active()
			&& ! is_super_admin()
		);
	}

	/** Enqueue styles for the plugin's admin pages. */
	public static function enqueue_styles_scripts() {
		wp_enqueue_style(
			'editoria11y-wp-css',
			trailingslashit( ED11Y_ASSETS ) . 'css/editoria11y-wp-admin.css',
			null,
			Plugin::VERSION
		);
	}

	/**
	 * Register `ed11y_plugin_settings` with the WP Settings API.
	 *
	 * Sanitize callback delegates to `SettingsValidator::validate` —
	 * passed as a callable array because the callback is non-trivial
	 * (~300 lines) and has its own test coverage.
	 */
	public static function register_settings() {
		register_setting(
			'ed11y_settings',
			'ed11y_plugin_settings',
			array(
				'sanitize_callback' => array( SettingsValidator::class, 'validate' ),
			)
		);
	}

	/**
	 * Register every section + field via the WP Settings API.
	 *
	 * Section hierarchy mirrors Drupal's `Editoria11ySettings.php`. The
	 * "Getting started" section deliberately groups developer- and
	 * content-area fields together: developers scan a superset of what
	 * content editors see, and presenting them in the same fieldset
	 * exposes the funnel (the developer area constrains what content
	 * editors can even test).
	 *
	 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
	 */
	public static function register_sections_and_fields() {

		$is_csa = ed11y_is_csa_active();

		/**
		 * Need help?
		 * <ul><li>Developer docs: <a href="https://editoria11y.com/docs/" target="_blank" rel="noopener">editoria11y.com/docs/</a></li>
		 */

		add_settings_section(
			'ed11y_getting_started',
			__( 'Getting started', 'editoria11y' ),
			array( SettingsFields::class, 'getting_started_section_intro' ),
			'ed11y'
		);

		// Per-test enable/disable, split into the two top-level sections
		// `TestNames::groupSet()` exposes (content tests / template tests).
		add_settings_section(
			'ed11y_modify_content_tests',
			__( 'Modify content tests', 'editoria11y' ),
			array( SettingsFields::class, 'modify_content_tests_section_intro' ),
			'ed11y'
		);
		add_settings_field(
			'tests_off_content',
			esc_html__( 'Tests by group', 'editoria11y' ),
			array( SettingsFields::class, 'modify_content_tests_field' ),
			'ed11y',
			'ed11y_modify_content_tests'
		);

		add_settings_section(
			'ed11y_modify_template_tests',
			__( 'Modify template tests', 'editoria11y' ),
			array( SettingsFields::class, 'modify_template_tests_section_intro' ),
			'ed11y'
		);
		add_settings_field(
			'tests_off_template',
			esc_html__( 'Tests by group', 'editoria11y' ),
			array( SettingsFields::class, 'modify_template_tests_field' ),
			'ed11y',
			'ed11y_modify_template_tests'
		);

		// Custom rules section is a deep-link surface to the dedicated
		// submenu page that owns the CRUD UI. CSA-only.

		/* --- Getting started: page-area config (the funnel) --- */

		add_settings_field(
			'ed11y_checkRoots',
			esc_html__( 'Page regions with user-editable content', 'editoria11y' ),
			array( SettingsFields::class, 'check_roots_field' ),
			'ed11y',
			'ed11y_getting_started',
			array( 'label_for' => 'ed11y_checkRoots' )
		);

		// The label text differs when CSA is active; the field itself is
		// shared. Build the free-build label first; the premium-only block
		// below replaces it when CSA is active at runtime.
		$ignore_elements_label = esc_html__( 'Do not check for content errors inside these elements', 'editoria11y' );

		add_settings_field(
			'ed11y_ignore_elements',
			$ignore_elements_label,
			array( SettingsFields::class, 'ignore_elements_field' ),
			'ed11y',
			'ed11y_getting_started',
			array( 'label_for' => 'ed11y_ignore_elements' )
		);

		// Stack of collapsibles for fields that used to live in their own
		// top-level <h2> sections (Assertiveness / Theme compatibility /
		// WordPress compatibility). Folded into a single row at the end of
		// "Getting started" so the only remaining <h2>s are Getting started,
		// the two Modify-tests sections, and Custom rules.
		add_settings_field(
			'ed11y_advanced_settings',
			esc_html__( 'Advanced settings', 'editoria11y' ),
			array( SettingsFields::class, 'advanced_settings_field' ),
			'ed11y',
			'ed11y_getting_started'
		);
	}

	/**
	 * Render the Editoria11y Settings page shell.
	 *
	 * The form body is generated by the WP Settings API via
	 * `do_settings_sections('ed11y')`; the migration progress panel and
	 * nav-tab row are rendered above it.
	 */
	public static function render_page() {
		?>

		<div class="wrap">
			<h1><?php esc_html_e( 'Editoria11y Settings', 'editoria11y' ); ?></h1>
			<?php self::render_nav_tabs( 'ed11y' ); ?>
			<?php self::render_upgrade_notice(); ?>
			<?php MigrationPanel::render(); ?>

			<div id="poststuff">
				<div id="post-body" class="editoria11y-settings metabox-holder">
					<div id="post-body-content">

					<div class="announcement-component">
						<!-- stuff above the form -->
					</div>

					<form method="post" action="options.php" autocomplete="off" class="ed11y-form-admin">
						<?php settings_fields( 'ed11y_settings' ); ?>
						<?php
						// Marker that lets SettingsValidator::validate() tell a
						// real form submission from a programmatic
						// update_option() write (Installer schema/seed
						// backfills, the NetworkDefaultsWorker seeder/backfill,
						// third-party code). Only a submission carrying this
						// marker may re-derive `tests_off` or run CSA test
						// routing; without it the validator passes those
						// through untouched.
						//
						// DO NOT REMOVE without a replacement. Deleting this
						// field reintroduces the v2->v3 "all tests off"
						// corruption: any programmatic write to
						// `ed11y_plugin_settings` during an admin request would
						// be read as "every checkbox unchecked" and rewrite
						// `tests_off` to every content test. If this field must
						// go, restore an equivalent guard that detaches the
						// `sanitize_option_ed11y_plugin_settings` filter around
						// every such write (the former
						// NetworkDefaultsWorker::update_option_without_form_validator()).
						?>
						<input type="hidden" name="ed11y_plugin_settings[_ed11y_form_submit]" value="1" />
						<?php do_settings_sections( 'ed11y' ); ?>
						<?php submit_button( esc_html__( 'Save Settings', 'editoria11y' ), 'primary large' ); ?>
					</form>
					<?php ConditionalFields::print_script(); ?>
				</div><!-- .post-body-content -->


				</div><!-- .editoria11y-settings -->
				<br class="clear">
			</div>
		</div>
		<?php
	}
}
