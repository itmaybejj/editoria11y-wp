<?php
/**
 * Sanitize / validate callback registered with the WP Settings API for
 * the per-site `ed11y_plugin_settings` option.
 *
 * The actual per-key sanitize rules live on {@see FieldSanitizer} so the
 * network defaults form dispatches through the same registry. This file
 * owns the cross-key logic: test-state routing, the side-effect CSA
 * option write, and server-side enforcement of network locks.
 *
 * @package Editoria11y
 */

namespace Editoria11y\Form;

use Editoria11y\TestNames;

defined( 'ABSPATH' ) || exit;

/**
 * Static sanitize callback for `ed11y_plugin_settings`.
 *
 * Registered via `register_setting()` in
 * {@see SettingsPage::register_settings()}.
 */
class SettingsValidator {

	/**
	 * Synthetic lock key in the CSA network-defaults storage. When set,
	 * coerces a site's `tests_off` / `tests_content` / `tests_dev` and
	 * `roles` to the network values as a unit. Defined here so both the
	 * network form and the enforcement pass agree on the key name.
	 */
	const BUNDLE_LOCK_TESTS_AND_ROLES = 'tests_assignment_bundle';

	/**
	 * Per-site CSA keys coerced by {@see BUNDLE_LOCK_TESTS_AND_ROLES}.
	 */
	const BUNDLE_LOCK_TESTS_AND_ROLES_KEYS = array(
		'tests_off',
		'tests_content',
		'tests_dev',
		'roles',
	);

	/**
	 * Parent → child(ren) lock subordinations in the CSA blob.
	 *
	 * When a parent key is properly locked (lock flag + non-empty value),
	 * each subordinated child key is treated as locked too — even if its
	 * own value is empty. The pair below covers the
	 * `csa_dev_check_root_field` UI: one "Lock this default" checkbox on
	 * the parent radio covers the conditional `specify_root` textarea
	 * underneath it.
	 *
	 * Consulted by both {@see ed11y_effective_network_csa_lock()} (read
	 * pipeline) and {@see enforce_network_csa_locks()} (per-site save).
	 *
	 * @var array<string,array<int,string>>
	 */
	const CSA_LOCK_SUBORDINATIONS = array(
		'dev_check_root' => array( 'specify_root' ),
	);

	/**
	 * Sanitize the posted settings array; returns the value WordPress
	 * will write to `ed11y_plugin_settings`.
	 *
	 * Side effect: in CSA mode, also writes to `ed11y_csa_plugin_settings`
	 * — Drupal's `submitForm()` saves both configs as a unit, and we
	 * mirror that here.
	 *
	 * @param array $settings Raw settings as posted from the form.
	 * @return array Sanitized settings.
	 *
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 */
	public static function validate( $settings ) {

		// Per-key sanitize via the shared registry — same rules whether
		// the POST came from the per-site form or the network defaults
		// form's per-site save replay.
		foreach ( FieldSanitizer::main_keys() as $key ) {
			if ( ! array_key_exists( $key, $settings ) ) {
				continue;
			}
			$settings[ $key ] = FieldSanitizer::sanitize_main( $key, $settings[ $key ] );
		}

		// Per-test enable/disable routing.
		//
		// Two branches picked by the CSA gate:
		//
		// - Free mode:
		// `tests_enabled[KEY]='1'` checkboxes → main `tests_off` CSV.
		// Developer-test entries already in `tests_off` (set during a
		// prior CSA-active session) pass through unchanged so a
		// trial-expired site doesn't silently re-enable every dev test.
		//
		// - CSA mode:
		// `tests_state[KEY]='nobody'|'developers'|'everyone'` → four CSVs
		// (main `tests_off` + CSA `tests_off` / `tests_content` /
		// `tests_dev`). See {@see TestStateNormalizer}.
		//
		// Production callers gate via `ed11y_is_csa_active()` (not Freemius
		// directly) so tests can simulate CSA via the
		// `ed11y_is_csa_active` filter.
		// Distinguish a genuine settings-form submission from a programmatic
		// update_option() write. The per-site form emits a hidden
		// `_ed11y_form_submit` marker (see SettingsPage::render_page());
		// programmatic writers — the Installer schema/seed backfills, the
		// NetworkDefaultsWorker seeder/backfill, and any third-party code —
		// never carry it. Only a real submission may re-derive `tests_off`
		// or run CSA routing: without the marker, a write that lacks the
		// form's `tests_enabled` sub-array would be misread as "every
		// checkbox unchecked" and clobber `tests_off` to "every content test
		// off" (the v2->v3 "all tests off" bug). This marker is the single
		// guard for every programmatic writer — it replaced the former
		// NetworkDefaultsWorker detach-the-filter helper.
		$is_form_submit = array_key_exists( '_ed11y_form_submit', $settings );
		unset( $settings['_ed11y_form_submit'] );

		$handled_csa = false;

		// CSA-mode branch wrapped in the preprocessor gate so it strips
		// from the free build. The `$handled_csa` flag, rather than an
		// `if/else`, gates the free branch — Freemius's preprocessor only
		// removes the `is__premium_only()` block, so a sibling `else`
		// would be orphaned and parse-fail. The `$is_form_submit` guards sit
		// on the INNER conditions so the `is__premium_only()` marker line the
		// strip script matches stays pristine.

		if ( $is_form_submit && ! $handled_csa ) {
			$existing_off          = ed11y_get_raw_setting( 'tests_off' );
			$enabled_post          = isset( $settings['tests_enabled'] ) && is_array( $settings['tests_enabled'] )
				? $settings['tests_enabled']
				: array();
			$settings['tests_off'] = TestStateNormalizer::from_free_post( $enabled_post, $existing_off );
		}

		// `tests_enabled`, `tests_state`, `csa_settings`, and
		// `csa_custom_rules` are UI artifacts for posting form state; do
		// not persist them into the main option row.
		unset(
			$settings['tests_enabled'],
			$settings['tests_state'],
			$settings['csa_settings'],
			$settings['csa_custom_rules']
		);

		// Server-side lock enforcement. The per-site form renders locked
		// fields as `disabled` (see {@see SettingsContext::field_disabled_attr()})
		// but that is a UX hint only — a hostile or scripted POST can
		// still include the locked key. Override any locked-key value
		// with the network-default value so the storage shape always
		// reflects the network admin's decision regardless of client
		// behavior.
		$settings = self::enforce_network_locks( $settings );

		// Reset cache.
		delete_site_transient( 'editoria11y_settings' );

		return $settings;
	}

	/**
	 * Apply CSA-mode test routing + dev-mode field sanitize, and side-
	 * effect-write `ed11y_csa_plugin_settings`.
	 *
	 * Returns the updated main settings array (with `tests_off`
	 * populated). CSA-specific `csa_settings` sub-array is consumed but
	 * dropped from the returned array — `validate()` unsets it after
	 * this returns regardless.
	 *
	 * @param array $settings Sanitized main settings, pre-routing.
	 * @return array Same array with `tests_off` set.
	 */
	private static function apply_csa_routing( array $settings ): array {
		$state_post = isset( $settings['tests_state'] ) && is_array( $settings['tests_state'] )
			? $settings['tests_state']
			: array();

		$routed                = TestStateNormalizer::from_csa_post( $state_post );
		$settings['tests_off'] = $routed['main_off'];

		// `csa_settings` is the form's CSA dev-mode sub-array. A forged
		// POST that includes it while CSA is INACTIVE never reaches this
		// branch, so free-mode submits can't sneak past the gate.
		$csa_post = isset( $settings['csa_settings'] ) && is_array( $settings['csa_settings'] )
			? $settings['csa_settings']
			: array();

		$csa_storage = array(
			'tests_off'         => $routed['csa_off'],
			'tests_content'     => $routed['csa_content'],
			'tests_dev'         => $routed['csa_dev'],
			'dev_check_root'    => FieldSanitizer::sanitize_csa( 'dev_check_root', $csa_post['dev_check_root'] ?? '' ),
			'specify_root'      => FieldSanitizer::sanitize_csa( 'specify_root', $csa_post['specify_root'] ?? '' ),
			'always_ignore'     => FieldSanitizer::sanitize_csa( 'always_ignore', $csa_post['always_ignore'] ?? '' ),
			'roles'             => RoleNormalizer::normalize( $csa_post['roles'] ?? array() ),
			'dev_assertiveness' => FieldSanitizer::sanitize_csa( 'dev_assertiveness', $csa_post['dev_assertiveness'] ?? '' ),
			'contrast_ignore'   => FieldSanitizer::sanitize_csa( 'contrast_ignore', $csa_post['contrast_ignore'] ?? '' ),
		);

		// Side-effect write — matches Drupal's `submitForm()`. The cache-bust
		// hook on the CSA option fires automatically; one logical save can
		// produce two version bumps, harmless because every browser fetches
		// the fresh static payload exactly once either way.
		$existing_csa = get_option( 'ed11y_csa_plugin_settings', array() );
		if ( ! is_array( $existing_csa ) ) {
			$existing_csa = array();
		}
		$merged_csa = array_merge( $existing_csa, $csa_storage );
		$merged_csa = self::enforce_network_csa_locks( $merged_csa );

		update_option( 'ed11y_csa_plugin_settings', $merged_csa );

		return $settings;
	}

	/**
	 * Coerce any locked-at-network keys back to their network-default value.
	 *
	 * Operates on the main-option array (the value WordPress is about to
	 * write to `ed11y_plugin_settings`). CSA option locks are applied
	 * separately in {@see enforce_network_csa_locks()} so the two option
	 * lifecycles stay independent.
	 *
	 * @param array $settings Post-sanitize main settings.
	 * @return array Same array with locked keys overwritten.
	 */
	private static function enforce_network_locks( array $settings ): array {
		$network = ed11y_get_network_default_settings_storage();
		if ( empty( $network['modes'] ) ) {
			return $settings;
		}
		foreach ( $network['modes'] as $key => $mode ) {
			if ( 'lock' !== $mode ) {
				continue;
			}
			if ( ! isset( $network['values'][ $key ] ) || empty( $network['values'][ $key ] ) ) {
				// Lock without value is inert — see {@see ed11y_is_setting_locked()}.
				continue;
			}
			$settings[ $key ] = $network['values'][ $key ];
		}
		return $settings;
	}

	/**
	 * Coerce locked CSA keys back to their network-default values.
	 *
	 * Includes the bundle lock {@see BUNDLE_LOCK_TESTS_AND_ROLES}: when
	 * set, coerces every key in {@see BUNDLE_LOCK_TESTS_AND_ROLES_KEYS}
	 * to the network value as a unit, regardless of whether the bundle
	 * key itself has a value (it is a synthetic lock with no own value).
	 *
	 * @param array $merged_csa Post-merge CSA settings about to be written.
	 * @return array Same array with locked keys overwritten.
	 */
	private static function enforce_network_csa_locks( array $merged_csa ): array {
		$network = ed11y_get_network_default_csa_settings_storage();
		if ( empty( $network['modes'] ) ) {
			return $merged_csa;
		}

		// Bundle lock first — covers tests + roles as one unit.
		if ( ( $network['modes'][ self::BUNDLE_LOCK_TESTS_AND_ROLES ] ?? null ) === 'lock' ) {
			foreach ( self::BUNDLE_LOCK_TESTS_AND_ROLES_KEYS as $csa_key ) {
				// Empty network value still coerces under the bundle —
				// the super-admin's "everyone gets the default set" is a
				// valid configuration.
				$merged_csa[ $csa_key ] = $network['values'][ $csa_key ] ?? '';
			}
		}

		// Per-key locks (direct + subordinated). Iterate the sanitize
		// registry so a key whose only lock is subordinated still gets
		// coerced — the storage's `locked` map alone would miss it.
		foreach ( FieldSanitizer::csa_keys() as $csa_key ) {
			$effective = ed11y_effective_network_csa_lock( $csa_key );
			if ( ! $effective['locked'] ) {
				continue;
			}
			$merged_csa[ $csa_key ] = $effective['value'];
		}

		return $merged_csa;
	}
}
