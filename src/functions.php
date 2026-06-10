<?php
/**
 * Editoria11y functions settings loader.
 *
 * @package Editoria11y
 */

use Editoria11y\Installer;
use Editoria11y\Plugin;
use Editoria11y\TestNames;

defined( 'ABSPATH' ) || exit;

add_filter( 'plugin_action_links_' . ED11Y_BASE, 'ed11y_add_action_links' );
/**
 * Adds link to setting page on plugin admin screen.
 *
 * @param array $links WP action link array.
 */
function ed11y_add_action_links( $links ) {
	$mylinks = array(
		'<a href="' . admin_url( 'options-general.php?page=ed11y' ) . '">Settings</a>',
	);
	return array_merge( $links, $mylinks );
}

/**
 * Whether the current theme is on the known-incompatible-with-visibility-checks
 * list. Drives the "Theme default" branch of `ed11y_checkvisibility`: when the
 * stored value is `''` (the form's "Theme default" sentinel), the static-settings
 * getter resolves to this bool before emitting `checkVisible` to JS.
 *
 * Lifted out of `ed11y_get_default_options()` so the defaults array can be
 * pure data (typed fields default to the form's empty-sentinel string). The
 * theme-detection result is per-request, not part of the storage contract.
 *
 * @return bool true when checkVisibility should be ON for this theme.
 */
function ed11y_checkvisibility_theme_default(): bool {
	// Todo check with 3.x.
	$incompatible = array(
		'Twenty Seventeen',
		'OnePress',
	);
	$theme        = array( wp_get_theme()->get( 'Name' ) );
	// Todo check this with an actual child theme.
	if ( false !== wp_get_theme()->parent() ) {
		array_push( $theme, wp_get_theme()->parent()->get( 'Name' ) );
	}
	return 0 === count( array_intersect( $incompatible, $theme ) );
}

/**
 * Return the default plugin settings.
 *
 * @param bool|string $option False for all, or specify one by key.
 */
function ed11y_get_default_options( string $option = '' ) {

	$default_options = array(
		// Todo: Web components
		// Todo: JS unfold theme handler
		// Todo: Language.
		'ed11y_theme'               => 'sleekTheme',
		'ed11y_checkRoots'          => '',
		'ed11y_livecheck'           => 'all',
		'ed11y_alert_mode'          => 'polite',

		'ed11y_ignore_elements'     => '#comments *, .wp-block-post-comments *, img.avatar',

		'ed11y_videoContent'        => 'youtube.com, vimeo.com, yuja.com, panopto.com',
		'ed11y_audioContent'        => 'soundcloud.com, simplecast.com, podbean.com, buzzsprout.com, blubrry.com, transistor.fm, fusebox.fm, libsyn.com',
		'ed11y_datavizContent'      => 'datastudio.google.com, tableau',

		// Static settings getter resolves these to a real bool before
		// emitting `checkVisible` to JS.
		'ed11y_checkvisibility'     => '',
		'ed11y_no_run'              => '',
		'ed11y_report_restrict'     => false,
		'ed11y_hide_report_link'    => false,
		'ed11y_custom_tests'        => 0,

		// Empty means every content test is enabled.
		'tests_off'                 => '',
		// Theme compatibility > Positioning.
		'hide_edit_links'           => '',
		'panel_pin'                 => 'right',
		// Some have install defaults but no code default.
		// ed11y_get_settings() would otherwise re-apply a non-empty default
		// on every read, making "delete" impossible.
		'panel_no_cover'            => '',
		'element_hides_overflow'    => '',
		'hidden_handlers'           => '',

		// Theme compatibility > Detecting dynamic and shadow content.
		'watch_for_changes'         => 'checkRoots',
		'shadow_components'         => '',
		'detect_shadow'             => false,

		// Theme compatibility > Syncing results to reports.
		'redundant_prefix'          => '',
		'preserve_params'           => '',
		'disable_sync'              => false,

		// Theme compatibility > Heading outline position of editable
		// content.
		'live_h2'                   => '',
		'live_h3'                   => '',
		'live_h4'                   => '',

		// Group-level refinements injected into per-test groups.
		'embedded_content_warning'  => '',
		'ed11y_documentContent'     => 'a[href$=".pdf"], a[href*=".pdf?"], a[href$=".doc"], a[href$=".docx"], a[href*=".doc?"], a[href*=".docx?"], a[href$=".ppt"], a[href$=".pptx"], a[href*=".ppt?"], a[href*=".pptx?"], a[href^="https://docs.google"]',
		'link_ignore_selector'      => '',
		'link_strings_new_windows'  => '',
		'ed11y_link_ignore_strings' => '',
	);

	// Allow dev to filter the default settings.
	$filtered = apply_filters( 'ed11y_default_options', $default_options );

	return $option ? $filtered[ $option ] : $filtered;
}

/**
 * Default values for the separate CSA settings option.
 *
 * Mirrors Drupal's `editoria11y_csa.settings` config object. Stored as a
 * separate WordPress option (`ed11y_csa_plugin_settings`) so the data can
 * persist across trial-expired / activation-toggled states without
 * polluting the main option that ships in the eventual free build.
 *
 * @param string $option Optional single key to read.
 * @return mixed Full defaults array, or the single requested value.
 */
function ed11y_get_csa_default_options( string $option = '' ) {
	// Body wrapped in the preprocessor gate so the free build returns
	// an empty value without referencing CSA-only defaults. Callsites
	// (validator, settings-page renderer) are already runtime-gated by
	// `ed11y_is_csa_active()`, which is false in the free build, so the
	// empty-return path is unreachable there but provides a safe fallback.

	return '' !== $option ? '' : array();
}

/**
 * WP roles that can edit posts, keyed by slug, value = display name.
 *
 * Drives the `roles` checkbox group on the CSA developer-mode settings
 * panel and the validator that runs over the saved CSV. Subscriber and
 * other roles without `edit_posts` are excluded — they have no path to
 * see editor alerts in the first place.
 *
 * Reads via `wp_roles()` so custom roles registered by other plugins /
 * themes show up automatically.
 *
 * @return array<string, string> Role slug → translated display name.
 */
function ed11y_get_developer_role_options(): array {
	$out = array();

	return $out;
}

/**
 * Full effective CSA settings (stored values overlaid on CSA defaults).
 *
 * Parallel to `ed11y_get_settings()` but reads from
 * `ed11y_csa_plugin_settings`. Same empty-overlay semantics: missing OR
 * empty stored keys fall through to defaults.
 *
 * @return array<string, mixed>
 */
function ed11y_get_csa_settings(): array {

	return array();
}

/**
 * Stored network-defaults blob for the CSA option (`ed11y_csa_plugin_settings`).
 *
 * Shape mirrors {@see ed11y_get_network_default_settings_storage()}. CSA-mode
 * gating happens at the caller layer — this function returns the raw storage
 * unconditionally so the network admin page can author CSA defaults even
 * before activating a license on any site.
 *
 * @return array{values: array<string,mixed>, modes: array<string,string>}
 */
function ed11y_get_network_default_csa_settings_storage(): array {
	$raw = get_site_option( 'ed11y_network_default_csa_settings', array() );
	if ( ! is_array( $raw ) ) {
		return array(
			'values' => array(),
			'modes'  => array(),
		);
	}
	return ed11y_normalize_network_default_storage( $raw );
}

/**
 * Raw stored network-default CSA value for one key, or `''` if unset.
 *
 * @param string $key CSA setting key.
 */
function ed11y_get_network_default_csa_setting( string $key ): string {
	$storage = ed11y_get_network_default_csa_settings_storage();
	if ( ! array_key_exists( $key, $storage['values'] ) ) {
		return '';
	}
	$value = $storage['values'][ $key ];
	if ( is_bool( $value ) ) {
		return $value ? '1' : '';
	}
	return (string) $value;
}

/**
 * Whether the network admin has locked the named CSA setting.
 *
 * @param string $key CSA setting key.
 */
function ed11y_is_csa_setting_locked( string $key ): bool {
	return ed11y_effective_network_csa_lock( $key )['locked'];
}

/**
 * Effective lock state + value for one CSA key, considering both direct
 * locks and {@see \Editoria11y\Form\SettingsValidator::CSA_LOCK_SUBORDINATIONS}.
 *
 * Direct lock: requires the key's own value to be non-empty (an
 * "enforced empty" direct lock is a footgun and is treated as unset).
 *
 * Subordinated lock: parent key must be properly locked. The child is
 * then locked regardless of its own value, because the empty value is
 * the network admin's deliberate choice (e.g., locking `dev_check_root`
 * to "automatic" reasonably implies `specify_root` should also be
 * empty and unchangeable per-site).
 *
 * @param string $key CSA setting key.
 * @return array{locked:bool, value:mixed} `value` is null when not locked.
 */
function ed11y_effective_network_csa_lock( string $key ): array {
	$storage = ed11y_get_network_default_csa_settings_storage();

	// Direct lock.
	if (
		( $storage['modes'][ $key ] ?? null ) === 'lock'
		&& isset( $storage['values'][ $key ] )
		&& ! empty( $storage['values'][ $key ] )
	) {
		return array(
			'locked' => true,
			'value'  => $storage['values'][ $key ],
		);
	}

	// Bundle lock (synthetic). When the tests + roles bundle is locked,
	// each of its four governed CSA keys is locked as a unit even with an
	// empty stored value — the super-admin's "everyone gets the network
	// default set" is a valid configuration. Mirrors the save-time
	// coercion in {@see \Editoria11y\Form\SettingsValidator::enforce_network_csa_locks()}.
	if (
		in_array( $key, \Editoria11y\Form\SettingsValidator::BUNDLE_LOCK_TESTS_AND_ROLES_KEYS, true )
		&& ed11y_is_bundle_locked()
	) {
		return array(
			'locked' => true,
			'value'  => $storage['values'][ $key ] ?? '',
		);
	}

	// Subordinated lock.
	foreach ( \Editoria11y\Form\SettingsValidator::CSA_LOCK_SUBORDINATIONS as $parent => $children ) {
		if ( ! in_array( $key, $children, true ) ) {
			continue;
		}
		if ( ( $storage['modes'][ $parent ] ?? null ) !== 'lock' ) {
			continue;
		}
		if ( empty( $storage['values'][ $parent ] ) ) {
			continue;
		}
		return array(
			'locked' => true,
			'value'  => $storage['values'][ $key ] ?? '',
		);
	}

	return array(
		'locked' => false,
		'value'  => null,
	);
}

/**
 * Single effective CSA setting value.
 *
 * @param string $key Setting key.
 * @return mixed Effective value, or null if the key is unknown.
 */
function ed11y_get_csa_setting( string $key ) {

	return null;
}

/**
 * Raw stored value for a single CSA setting, or `''` if unset.
 *
 * Settings-page form-input companion. See the docblock on
 * `ed11y_get_raw_setting()` for the same trade-off discussion.
 *
 * @param string $key Setting key.
 * @return string Stored value cast to string, or empty string if unset.
 */
function ed11y_get_csa_raw_setting( string $key ): string {

	return '';
}

/*
 * Custom-rule storage, validation, and helpers moved to
 * `Editoria11y\CustomRules` (src/CustomRules.php). The class owns the
 * `ed11y_csa_custom_rules` option, the validator gates the CRUD page +
 * the static-config payload reader both consult, and the constants
 * (TYPES / DISMISS_KEYS / ELEMENT_SETS / ELEMENT_SET_PRESETS) that were
 * previously top-level here.
 */

/**
 * Whether the CSA premium feature gate is currently active.
 *
 * Wraps Freemius's `can_use_premium_code__premium_only()` so the rest of
 * the plugin (sanitize callback, settings-form renderer, future static
 * payload extensions) can call a single helper that:
 *
 *   1. Degrades gracefully when `ed11ycsa()` returns null (the wp-phpunit
 *      bootstrap stubs it that way; defensive on production too).
 *   2. Is filterable via `ed11y_is_csa_active`, so tests can simulate
 *      CSA-active state with `add_filter( 'ed11y_is_csa_active',
 *      '__return_true' )` instead of mocking the Freemius singleton.
 *
 * Production callers should use this helper rather than calling Freemius
 * directly; otherwise the test harness can't exercise CSA-mode code paths.
 *
 * @return bool
 */
function ed11y_is_csa_active(): bool {
	$active = false;
	if ( function_exists( 'ed11ycsa' ) ) {
		$freemius = ed11ycsa();
		if ( is_object( $freemius ) && method_exists( $freemius, 'can_use_premium_code__premium_only' ) ) {
			$active = (bool) $freemius->can_use_premium_code__premium_only();
		}
	}
	return (bool) apply_filters( 'ed11y_is_csa_active', $active );
}

/**
 * Resolve the bare lang-pack filename for the current user's locale.
 *
 * The bundled library ships ~20 lang packs in [assets/lib/js/lang/](../assets/lib/js/lang/);
 * this helper maps a WP locale (e.g. `en_US`, `de_DE`, `pt_BR`) to the
 * matching pack filename without extension (`en-us`, `de`, `pt-br`).
 *
 * Match order:
 *   1. Full WP locale lowercased with `-` separators (`pt_BR` → `pt-br`).
 *   2. Language code only (`de_DE` → `de`), so script-tagged locales like
 *      `zh_CN` / `zh_TW` collapse to the single `zh` pack we ship.
 *   3. `en` as a final fallback for unsupported locales.
 *
 * The available list is hardcoded rather than globbed — globbing at every
 * page load is wasteful, the upstream file set is stable per release, and
 * `scripts/get.sh` is the only thing that adds files here.
 *
 * @return string Lang-pack filename without `.js` extension.
 */
function ed11y_lang_pack_filename(): string {
	$available = array(
		'da',
		'de',
		'el',
		'en',
		'en-ca',
		'en-gb',
		'en-us',
		'es',
		'fr',
		'hu',
		'it',
		'ja',
		'nb',
		'nl',
		'pl',
		'pt-br',
		'pt-pt',
		'sv',
		'uk',
		'zh',
	);

	$locale = strtolower( str_replace( '_', '-', (string) get_user_locale() ) );
	if ( '' !== $locale && in_array( $locale, $available, true ) ) {
		return $locale;
	}

	$language = strtok( $locale, '-' );
	if ( false !== $language && in_array( $language, $available, true ) ) {
		return $language;
	}

	return 'en';
}

/**
 * Full effective plugin settings (stored values overlaid on defaults).
 *
 * Use this in runtime code that needs the *current value* of any setting:
 * the JS payload builder, the static config endpoint, anywhere the plugin
 * has to act on a setting. Stored values that are missing OR empty fall
 * back to the matching hardcoded default — that empty-overlay is intentional
 * (an accidentally cleared field still works) and is a behavior the WP plugin
 * has shipped for years; it is preserved here.
 *
 * Network defaults: the read path only overlays network values for keys
 * with `mode = 'lock'` (super-admin policy enforcement). Unlocked modes
 * (`'new'` / `'all'`) propagate to site storage out-of-band — via the
 * site-creation seeder for `'new'` / `'all'`, and the
 * {@see NetworkDefaultsWorker} backfill for `'all'`. Once a value lands in
 * site storage, the site owns it; clearing it sticks. This eliminates the
 * earlier bug where an unlocked network default would silently re-apply
 * itself after a per-site clear.
 *
 * Settings outside `ed11y_get_default_options()` are passed through unchanged
 * so a third-party that adds keys via the option array is not silently
 * stripped on read.
 *
 * @return array<string, mixed>
 */
function ed11y_get_settings(): array {
	$stored = get_option( 'ed11y_plugin_settings', array() );
	if ( ! is_array( $stored ) ) {
		$stored = array();
	}
	$defaults      = ed11y_get_default_options();
	$network       = ed11y_get_network_default_settings_storage();
	$bundle_locked = ed11y_is_bundle_locked();

	foreach ( $defaults as $key => $default_value ) {
		// Bundle lock: `tests_off` (main) is governed by the CSA-blob
		// bundle key. When the bundle is locked, overlay the network's
		// main-blob `tests_off` outright — even when empty, since "the
		// network default set" is a valid super-admin configuration.
		if ( 'tests_off' === $key && $bundle_locked ) {
			$stored[ $key ] = $network['values'][ $key ] ?? '';
			continue;
		}
		// Locked: network value wins outright when present. Lock without
		// value is inert (preserves the "empty-overlay" guarantee).
		if (
			( $network['modes'][ $key ] ?? null ) === 'lock'
			&& isset( $network['values'][ $key ] )
			&& ! empty( $network['values'][ $key ] )
		) {
			$stored[ $key ] = $network['values'][ $key ];
			continue;
		}

		// Site value wins for unlocked keys. Empty/missing falls through
		// to the hardcoded default — unlocked network values reach a site
		// via the seeder/backfill, not via this read path.
		if ( isset( $stored[ $key ] ) && ! empty( $stored[ $key ] ) ) {
			continue;
		}

		$stored[ $key ] = $default_value;
	}
	return $stored;
}

/**
 * Stored network-defaults blob for the main `ed11y_plugin_settings` option.
 *
 * Shape: `array( 'values' => array<string,mixed>, 'modes' => array<string,string> )`
 * where each `modes[$key]` is one of:
 *   - `'new'`  → seed into new sites at creation; do not touch existing sites.
 *   - `'all'`  → seed into new sites at creation AND backfill into existing
 *                sites that lack the key (see NetworkDefaultsWorker).
 *   - `'lock'` → overlay at read time; site cannot override.
 *
 * Either subkey may be absent. Non-array stored values are treated as absent
 * so a manual-edit corruption falls back to per-site behavior rather than
 * fataling the read path.
 *
 * Back-compat: legacy storage carrying `locked[]` instead of `modes[]` is
 * normalized on read — `locked[$key] === true` maps to `mode = 'lock'`,
 * other keys with stored values map to `mode = 'all'` (preserves the old
 * "unlocked default propagated everywhere" intent for migrations).
 *
 * `get_site_option()` transparently falls back to `get_option()` on single-
 * site installs, so callers do not need to gate on `is_multisite()`.
 *
 * @return array{values: array<string,mixed>, modes: array<string,string>}
 */
function ed11y_get_network_default_settings_storage(): array {
	$raw = get_site_option( 'ed11y_network_default_settings', array() );
	if ( ! is_array( $raw ) ) {
		return array(
			'values' => array(),
			'modes'  => array(),
		);
	}
	return ed11y_normalize_network_default_storage( $raw );
}

/**
 * Normalize a raw network-defaults blob into the canonical
 * `array( values, modes )` shape, migrating legacy `locked[]` storage.
 *
 * @param array<string,mixed> $raw Raw storage as returned by `get_site_option`.
 * @return array{values: array<string,mixed>, modes: array<string,string>}
 */
function ed11y_normalize_network_default_storage( array $raw ): array {
	$values = isset( $raw['values'] ) && is_array( $raw['values'] ) ? $raw['values'] : array();

	if ( isset( $raw['modes'] ) && is_array( $raw['modes'] ) ) {
		$modes = array();
		foreach ( $raw['modes'] as $key => $mode ) {
			if ( is_string( $mode ) && in_array( $mode, array( 'new', 'all', 'lock' ), true ) ) {
				$modes[ $key ] = $mode;
			}
		}
		return array(
			'values' => $values,
			'modes'  => $modes,
		);
	}

	// Legacy shape: derive modes from `locked[]`. Keys with stored values
	// default to `'all'` (the closest analog to "unlocked default propagated
	// on read" in the old code), and keys flagged as locked map to `'lock'`.
	// Bundle locks (lock flag set with no matching value, e.g.
	// SettingsValidator::BUNDLE_LOCK_TESTS_AND_ROLES) are preserved as
	// `'lock'` so they keep coercing on save.
	$legacy_locked = isset( $raw['locked'] ) && is_array( $raw['locked'] ) ? $raw['locked'] : array();
	$modes         = array();
	foreach ( $values as $key => $_unused ) {
		$modes[ $key ] = ! empty( $legacy_locked[ $key ] ) ? 'lock' : 'all';
	}
	foreach ( $legacy_locked as $key => $flag ) {
		if ( ! empty( $flag ) && ! array_key_exists( $key, $modes ) ) {
			$modes[ $key ] = 'lock';
		}
	}
	return array(
		'values' => $values,
		'modes'  => $modes,
	);
}

/**
 * Raw stored network-default value for one key, or `''` if unset.
 *
 * Settings-page form-input companion for the Network Settings page —
 * parallel to `ed11y_get_raw_setting()` but reads from the network-level
 * option. Returns `''` for missing keys so the form input shows blank and
 * the placeholder reveals the hardcoded default.
 *
 * @param string $key Setting key.
 */
function ed11y_get_network_default_setting( string $key ): string {
	$storage = ed11y_get_network_default_settings_storage();
	if ( ! array_key_exists( $key, $storage['values'] ) ) {
		return '';
	}
	$value = $storage['values'][ $key ];
	if ( is_bool( $value ) ) {
		return $value ? '1' : '';
	}
	return (string) $value;
}

/**
 * Whether the network admin has locked the named setting.
 *
 * A locked key means: site-level POSTs of that key are coerced back to the
 * network value by `SettingsValidator`, and the field renders disabled on
 * the per-site settings page. A lock with no matching value is treated as
 * unset (the form handler never writes that combination, but the read path
 * is tolerant).
 *
 * Bundle lock: the synthetic `tests_assignment_bundle` lives in the CSA
 * blob's modes but covers `tests_off` on the main blob too (see {@see
 * \Editoria11y\Form\SettingsValidator::BUNDLE_LOCK_TESTS_AND_ROLES_KEYS}).
 * When the bundle is locked, `tests_off` (main) is reported locked here
 * regardless of its per-key mode, so the form / read pipeline matches the
 * save-time coercion.
 *
 * @param string $key Setting key.
 */
function ed11y_is_setting_locked( string $key ): bool {
	if ( 'tests_off' === $key && ed11y_is_bundle_locked() ) {
		return true;
	}
	$storage = ed11y_get_network_default_settings_storage();
	if ( ( $storage['modes'][ $key ] ?? null ) !== 'lock' ) {
		return false;
	}
	return isset( $storage['values'][ $key ] ) && ! empty( $storage['values'][ $key ] );
}

/**
 * Whether the synthetic tests + roles bundle is locked at the network level.
 *
 * Source of truth for "is the bundle lock currently in effect" — both the
 * main-blob lock check ({@see ed11y_is_setting_locked()} for `tests_off`)
 * and the CSA-blob lock check ({@see ed11y_effective_network_csa_lock()}
 * for the four bundle-governed keys) defer to this.
 */
function ed11y_is_bundle_locked(): bool {
	$storage = ed11y_get_network_default_csa_settings_storage();
	return ( $storage['modes'][ \Editoria11y\Form\SettingsValidator::BUNDLE_LOCK_TESTS_AND_ROLES ] ?? null ) === 'lock';
}

/**
 * Single effective setting value.
 *
 * Convenience wrapper around `ed11y_get_settings()` for callers that only
 * need one key. Returns null if the key is neither in the stored option
 * nor in `ed11y_get_default_options()`.
 *
 * @param string $key Setting key (e.g. 'ed11y_theme').
 * @return mixed Effective value, or null if the key is unknown.
 */
function ed11y_get_setting( string $key ) {
	$settings = ed11y_get_settings();
	return $settings[ $key ] ?? null;
}

/**
 * Raw stored value for a single setting, or `''` if the user has not set it.
 *
 * Pair with `ed11y_get_default_options( $key )` for settings-page form
 * rendering: `<input value="{raw}" placeholder="{default}">` shows the
 * user's actual stored value while exposing the default through the
 * placeholder. Do NOT use this in runtime code — the `''` return for
 * unset keys silently blanks downstream behavior. Use `ed11y_get_setting()`
 * (or read from `ed11y_get_settings()`) for runtime values.
 *
 * @param string $key Setting key.
 * @return string Stored value cast to string, or empty string if unset.
 */
function ed11y_get_raw_setting( string $key ): string {
	$stored = get_option( 'ed11y_plugin_settings', array() );
	if ( ! is_array( $stored ) || ! array_key_exists( $key, $stored ) ) {
		return '';
	}
	$value = $stored[ $key ];
	if ( is_bool( $value ) ) {
		return $value ? '1' : '';
	}
	return (string) $value;
}


/**
 * Returns the hashed form of a (result_key, element_id) pair.
 *
 * Recipe matches Drupal editoria11y_update_9011: lowercase the element_id for
 * a known set of keys, concatenate with the result_key, strip non-alphanumeric,
 * truncate to 256 chars, then SHA-256 with the pepper as salt.
 *
 * Stays a global function rather than a method on the installer because it is
 * the documented JS-parity contract — the bundled library hashes the same way
 * client-side, and naming/locating it as a free function makes that pairing
 * easier to find in either codebase.
 *
 * @param string $result_key Editoria11y test key (e.g. 'LINK_URL').
 * @param string $element_id Raw element identifier from the JS checker.
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
function ed11y_hash_element_id( string $result_key, string $element_id ): string {
	$lower_keys = array( 'LINK_NEW_TAB', 'LINK_STOPWORD', 'LINK_URL' );
	$normalized = in_array( $result_key, $lower_keys, true ) ? strtolower( $element_id ) : $element_id;
	$id_string  = $result_key . $normalized;
	$id_string  = preg_replace( '/[^0-9a-zA-Z]/', '', $id_string );
	$id_string  = substr( (string) $id_string, 0, 256 );
	return hash( 'sha256', Installer::get_pepper() . $id_string );
}

/**
 * Current value of the static config cache-bust counter.
 *
 * Stored as a plain autoloaded option (not a transient) — analog of Drupal's
 * `state` storage. Bumped on every settings save, so the per-page payload's
 * `config_url` injects a fresh `?v=<n>` and the browser fetches new content
 * past the 30-day `Cache-Control: immutable` directive on the static config
 * REST endpoint.
 *
 * Returns 1 on a clean install (matches the Drupal default and lets the test
 * suite assert a deterministic baseline).
 *
 * @return int
 */
function ed11y_get_config_version(): int {
	return absint( get_option( 'ed11y_config_version', 1 ) );
}

/**
 * Increment the cache-bust counter.
 *
 * Hooked off `add_option_*` and `update_option_*` for option names that
 * influence the static config payload — see the `add_action()` registrations
 * at the bottom of this block. Page-scoped dismissal writes do NOT hit this:
 * they only affect the per-page payload (rebuilt every request) so a cache
 * bump would be wasted invalidation.
 */
function ed11y_bump_config_version(): void {
	update_option( 'ed11y_config_version', ed11y_get_config_version() + 1, true );
}

/**
 * Site-wide dismissals (the `okAll` branch).
 *
 * Splits the historical UNION ALL query in `ed11y_get_params()` so the
 * global dismissals can be served from the static config REST endpoint
 * (browser-cached for 30 days; cachebust on every okAll write or reset)
 * while page-scoped dismissals stay per-request.
 *
 * Index path: `KEY dismissal_status` on `ed11y_dismissals` — no JOIN
 * needed because okAll rows match every page.
 *
 * Returns an empty array if the schema is not initialized yet (the lazy
 * Installer::check_tables() guard mirrors `ed11y_get_params()` so a
 * network-activation race never errors the static endpoint).
 *
 * @return array<string, array<string, string>> Nested array
 *   `[ result_key ][ element_id ] = 'okAll'`.
 */
function ed11y_get_global_dismissals(): array {
	global $wpdb;
	$dtable = $wpdb->prefix . 'ed11y_dismissals';

	// Read-only helpers must not trigger DDL — Installer::check_tables()
	// would lazy-create the schema *and* emit "table doesn't exist" errors
	// during its inspection step, which the static-config endpoint can't
	// surface. Suppress wpdb errors for this query so a missing table
	// degrades to an empty result set instead.
	$previous = $wpdb->suppress_errors();
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $dtable is $wpdb->prefix.'ed11y_dismissals' (literal); no user input in WHERE.
	$rows = $wpdb->get_results(
		"SELECT result_key, element_id FROM {$dtable} WHERE dismissal_status = 'okAll';"
	);
	// phpcs:enable
	$wpdb->suppress_errors( $previous );

	if ( ! is_array( $rows ) ) {
		return array();
	}
	$out = array();
	foreach ( $rows as $row ) {
		$out[ $row->result_key ][ $row->element_id ] = 'okAll';
	}
	return $out;
}

/**
 * Per-user profile classification for the per-page payload.
 *
 * Returns `'dev'` if the user's roles intersect the CSA `roles` CSV (and
 * CSA is currently active); returns `'content'` otherwise. The bundled JS
 * library reads this to pick which test routing applies — `tests_dev` for
 * developers, `tests_content` for everyone else.
 *
 * Lives in the per-page payload (not the static `/wp-json/ed11y/v1/config`
 * blob) because it varies per user. Putting it on the static endpoint
 * would defeat the 30-day immutable cache, since `private` cache means
 * each user's browser caches their own copy independently — fine if the
 * payload is otherwise identical, broken if a per-user dimension sneaks in.
 *
 * Returns `'content'` when CSA is inactive so downstream consumers always
 * see one of the two known values rather than null/empty.
 *
 * @param WP_User|null $user Current user. null defaults to wp_get_current_user().
 * @return string `'dev'` or `'content'`.
 */
function ed11y_get_user_profile( $user = null ): string {
	if ( ! ed11y_is_csa_active() ) {
		return 'content';
	}
	if ( null === $user ) {
		$user = wp_get_current_user();
	}
	if ( ! is_object( $user ) || empty( $user->roles ) || ! is_array( $user->roles ) ) {
		return 'content';
	}
	$dev_roles_csv = ed11y_get_csa_setting( 'roles' );
	if ( empty( $dev_roles_csv ) ) {
		return 'content';
	}
	$dev_roles = array_filter( array_map( 'trim', explode( ',', (string) $dev_roles_csv ) ) );
	$intersect = array_intersect( $user->roles, $dev_roles );
	return empty( $intersect ) ? 'content' : 'dev';
}

/**
 * Page-scoped dismissals for the current user + URL.
 *
 * The other half of the UNION ALL split. Returns the `ok` rows that apply
 * to this specific page (so other editors see them) plus the `hide` rows
 * that belong to the current user (so they are private dismissals).
 *
 * Index path: page lookup via `KEY page_url` / `KEY post_id` on the urls
 * side, then `KEY pid_result_key_element_id` on the dismissals side.
 *
 * @param int    $user_id      Current user ID (for the `hide` filter).
 * @param string $current_page Canonical URL of the page being scanned.
 * @param int    $post_id      Post ID, or 0 for archives / non-singular routes.
 *
 * @return array<string, array<string, string>> Nested array
 *   `[ result_key ][ element_id ] = 'ok' | 'hide'`.
 */
function ed11y_get_page_dismissals( int $user_id, string $current_page, int $post_id ): array {
	global $wpdb;
	$utable = $wpdb->prefix . 'ed11y_urls';
	$dtable = $wpdb->prefix . 'ed11y_dismissals';

	// Read-only — see the matching comment in ed11y_get_global_dismissals().
	$previous = $wpdb->suppress_errors();
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table names interpolated from $wpdb->prefix; user input passed via $wpdb->prepare().
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT {$dtable}.result_key, {$dtable}.element_id, {$dtable}.dismissal_status
			FROM {$dtable}
			INNER JOIN {$utable} ON {$utable}.pid = {$dtable}.pid
			WHERE (
				{$utable}.page_url = %s
				OR (
					0 < %d
					AND {$utable}.post_id = %d
				)
			)
			AND (
				{$dtable}.dismissal_status = 'ok'
				OR (
					{$dtable}.dismissal_status = 'hide'
					AND {$dtable}.user = %d
				)
			)
			;",
			array(
				$current_page,
				$post_id,
				$post_id,
				$user_id,
			)
		)
	);
	// phpcs:enable
	$wpdb->suppress_errors( $previous );

	if ( ! is_array( $rows ) ) {
		return array();
	}
	$out = array();
	foreach ( $rows as $row ) {
		$out[ $row->result_key ][ $row->element_id ] = $row->dismissal_status;
	}
	return $out;
}

/**
 * Build the `ignoreTests` payload that the JS shim folds into
 * `options.checks[KEY] = false` before constructing Ed11y.
 *
 * The library's option contract treats a missing `checks[KEY]` entry as
 * "fall through to library defaults". That bites whenever an admin toggles
 * a test off but the test is enabled in the upstream defaults — the form
 * save records the intent in `tests_off`, but unless PHP explicitly disables
 * the key on the wire, the library runs it anyway.
 *
 * Three layers compose the final list (Drupal\editoria11y\Controller\Ed11yConfigController
 * does the same merge):
 *
 *   1. The user's stored `tests_off` CSV (free-mode form toggles, plus any
 *      developer-tier entries lingering from a prior CSA-active session).
 *   2. `TestNames::library_artifacts()` — upstream defaults the WP port
 *      does not implement (LINK_LABEL) or has not wired up (the LANG
 *      suite needs `langOfPartsPlugin`). Always disabled.
 *   3. When CSA is inactive: `TestNames::template_tests()`. The free-mode
 *      form does not expose developer-tier tests, so without CSA there is
 *      no UI to manage them and the library must leave them off.
 *
 * @param string $tests_off_csv Stored `tests_off` value (empty for fresh
 *                              installs).
 * @return array<int, string>   De-duplicated test keys, in merge order.
 */
function ed11y_build_ignore_tests( string $tests_off_csv ): array {
	$user_off = '' === $tests_off_csv
		? array()
		: array_values( array_filter( array_map( 'trim', explode( ',', $tests_off_csv ) ) ) );

	$ignore = array_merge( $user_off, TestNames::library_artifacts() );

	// Free build always merges the template tests into the ignore list.
	// In the premium build, the CSA branch (when CSA is active at runtime)
	// keeps them included so the user-facing UI can route them. The
	// preprocessor strips the premium-only override below, leaving the
	// unconditional merge in the free build.
	$ignore = array_merge( $ignore, TestNames::template_tests() );

	return array_values( array_unique( $ignore ) );
}

/**
 * Settings shared between the per-page payload and the static config API.
 *
 * Returns the JS-side view of the plugin settings option: keys are the names
 * the bundled library reads at runtime — camelCase library option names like
 * `checkRoot`, `containerIgnore`, `panelPosition`. Values are derived from
 * the WP option `ed11y_plugin_settings` (which keeps its v2 `ed11y_*` /
 * snake_case storage keys for backward compatibility — see the field-naming
 * decision in the v3 migration plan).
 *
 * Pure: no transients, no DB outside `get_option()`. Safe to call multiple
 * times per request.
 *
 * The keys returned here are the "static" subset — values that depend only
 * on site config, not on the current request, current user, or current
 * entity. Per-request overlays (alertMode upgrade, post_id, currentPage,
 * pepper, syncedDismissals, etc.) live in `ed11y_get_params()`.
 *
 * Field-name decoder for the v3 rename: the library reads `checkRoot`
 * (singular), `containerIgnore`, `panelPosition`, `linkIgnoreStrings`,
 * `linkStringsNewWindows`, `linkIgnoreSpan`, `panelNoCover`,
 * `constrainButtons`, `hiddenHandlers`, `shadowComponents`,
 * `autoDetectShadowComponents`, `watchForChanges`. The shim files
 * (`editoria11y-wp.js`, `editoria11y-editor.js`) do only the runtime
 * conversions PHP cannot do (string → RegExp, CSV → array, etc.).
 *
 * @return array<string, mixed>
 */
function ed11y_get_static_settings(): array {
	$settings = ed11y_get_settings();

	// `ed11y_checkRoots` is the user-facing scan area selector. Emit empty
	// strictly when storage is empty — DON'T coerce '' to 'main' here.
	// "Empty" and "explicit 'main'" are semantically distinct:
	//
	// - Empty (admin made no choice) → JS should autodetect: prefer the
	// <main> element if present, else <body>. The library does exactly
	// this at ed11y.esm.js:9305-9306 (`if (!checkRoot) ... 'main' || 'body'`).
	// - Explicit 'main' (admin typed 'main' into the field) → use 'main'
	// unconditionally, even on pages that lack a <main> element. The
	// library logs MISSING_ROOT for those pages, which is the
	// admin-facing diagnostic they want.
	//
	// The CSA-clobber concern that previously motivated coercion here turns
	// out to be a non-issue: the WP shim's `devOptions.checkRoot` (in
	// `addCSAFields`) is only set when `syncRoot` is explicitly non-empty,
	// so when no developer scan area is configured, `Object.assign(devOptions)`
	// at ed11y.esm.js:9317 doesn't carry a `checkRoot` key — the autodetect
	// result survives. We still string-coerce a non-string (legacy bool)
	// stored value to '' so the JSON wire shape is always a string.
	$check_root = is_string( $settings['ed11y_checkRoots'] ) ? trim( $settings['ed11y_checkRoots'] ) : '';

	// Storage shape for `ed11y_checkvisibility` is the form's `<select>`
	// values: '' (Theme default), 'true', 'false'. Resolve to a real bool
	// here so JS gets a strictly-typed `checkVisible` and the shim can
	// drop its `=== 'true'` coercion. The library's own default is `true`
	// (assets/lib/js/ed11y.esm.js), and `ed11y_checkvisibility_theme_default()`
	// keeps "Theme default" honest — bool false only on themes we know
	// produce false-positive "may be hidden" alerts.
	$check_visible_raw = (string) $settings['ed11y_checkvisibility'];
	if ( 'true' === $check_visible_raw ) {
		$check_visible = true;
	} elseif ( 'false' === $check_visible_raw ) {
		$check_visible = false;
	} else {
		$check_visible = ed11y_checkvisibility_theme_default();
	}

	// `watch_for_changes` storage is a string ('true' / 'checkRoots' / 'false').
	// The library at ed11y.esm.js:6973 gates with `if (State.option.watchForChanges)`,
	// and the string 'false' is truthy in JS — so saving "Do not watch" was
	// silently turning into "watch everywhere". Resolve 'false' to bool here;
	// 'true' stays as a string (any truthy non-'checkRoots' value means
	// "watch anywhere" in the library's branching).
	$watch_raw         = (string) $settings['watch_for_changes'];
	$watch_for_changes = 'false' === $watch_raw ? false : $watch_raw;

	return array(
		// Theme + scan area.
		'theme'                      => $settings['ed11y_theme'],
		'checkRoot'                  => $check_root,
		// Always prepend the wp-admin bar selector — never user-configurable.
		// `ed11y_get_settings()`'s empty-overlay guarantees the user value
		// is never an empty string here, so no trailing-comma worry.
		'containerIgnore'            => '#wpadminbar *,' . $settings['ed11y_ignore_elements'],

		// Embed-content selectors (keyed by *Content for the editor's library
		// fall-through; the `embeddedContentWarning` field below feeds
		// options.checks.EMBED_CUSTOM in the JS shim).
		'videoContent'               => $settings['ed11y_videoContent'],
		'audioContent'               => $settings['ed11y_audioContent'],
		'documentLinks'              => $settings['ed11y_documentContent'],
		'dataVizContent'             => $settings['ed11y_datavizContent'],
		'embeddedContentWarning'     => $settings['embedded_content_warning'],

		// Link refinements. Note the deliberate split: the WP storage key
		// `ed11y_link_ignore_strings` *logically* maps to the library's
		// `linkIgnoreStrings` ("phrases to remove before testing link
		// text"), and the storage key `link_strings_new_windows` maps to
		// the library's `linkStringsNewWindows` ("phrases that warn the
		// link opens a new tab"). Earlier code conflated the two; keep the
		// mapping explicit here.
		'linkIgnoreStrings'          => $settings['ed11y_link_ignore_strings'],
		'linkStringsNewWindows'      => $settings['link_strings_new_windows'],
		'linkIgnoreSpan'             => $settings['link_ignore_selector'],

		// Visibility / no-run gates.
		'checkVisible'               => $check_visible,
		'preventCheckingIfPresent'   => $settings['ed11y_no_run'],

		// Theme compatibility — Positioning.
		'hideEditLinks'              => $settings['hide_edit_links'],
		'panelPosition'              => $settings['panel_pin'],
		'panelNoCover'               => $settings['panel_no_cover'],
		'constrainButtons'           => $settings['element_hides_overflow'],
		'hiddenHandlers'             => $settings['hidden_handlers'],

		// Theme compatibility — Dynamic / shadow.
		'watchForChanges'            => $watch_for_changes,
		'shadowComponents'           => $settings['shadow_components'],
		'autoDetectShadowComponents' => ! empty( $settings['detect_shadow'] ),

		// Theme compatibility — Heading outline (consumed by editor shim
		// for `initialHeadingLevel` / `editorHeadingLevel`; passed through
		// for the frontend, where they are no-ops).
		'liveH2'                     => $settings['live_h2'],
		'liveH3'                     => $settings['live_h3'],
		'liveH4'                     => $settings['live_h4'],

		// Test routing.
		'customTests'                => $settings['ed11y_custom_tests'],
		// `ignoreTests` is the list of test keys the JS shim disables via
		// `options.checks[KEY] = false`. The library treats unset entries
		// as "fall through to library defaults", which historically drifted
		// — admins toggling a test off in the form would have no effect on
		// tests the library shipped as on. We close the gap by building the
		// list as the union of:
		// - user-toggled tests (free-mode `tests_off` CSV);
		// - `TestNames::library_artifacts()` — upstream defaults that have
		// no implementation here (LINK_LABEL, the unwired LANG suite);
		// - when CSA is inactive: `TestNames::template_tests()` — the
		// developer-tier tests the free-mode form does not expose.
		// Mirrors Drupal\editoria11y\Controller\Ed11yConfigController.
		'ignoreTests'                => ed11y_build_ignore_tests( (string) $settings['tests_off'] ),

		// WP shim-only helpers (not library options).
		'liveCheck'                  => $settings['ed11y_livecheck'],
		'hideReportLink'             => $settings['ed11y_hide_report_link'],
		'cssLocation'                => trailingslashit( ED11Y_ASSETS ) . 'lib/css/editoria11y.min.css?ver=' . Plugin::VERSION,
		'mceInnerJS'                 => trailingslashit( ED11Y_ASSETS ) . 'js/editoria11y-mce-inner.js?ver=' . Plugin::VERSION,
		'adminUrl'                   => get_admin_url(),
		// alertMode is intentionally NOT here: the per-page payload upgrades
		// it to 'assertive' for posts modified within the last 10 minutes,
		// so it is per-request, not static.
	);
}

/**
 * URL the per-page payload hands the browser as `config_url`.
 *
 * Format mirrors Drupal's `editoria11y.api_config?v=<n>`: the route stays
 * constant, the version segment is the cachebust. The browser caches the
 * response for 30 days (see `ApiConfig::set_cache_headers`), so changing the
 * version is the only way to force a new fetch.
 *
 * @return string
 */
function ed11y_get_config_url(): string {
	return rest_url( 'ed11y/v1/config' ) . '?v=' . ed11y_get_config_version();
}

// Bump the version whenever an option that feeds the static payload is
// touched. Hooks fire only when the value actually changes (WP guards both
// add_option and update_option against no-op writes), so a settings page
// "Save" with no field changes is a no-op here too.
add_action( 'add_option_ed11y_plugin_settings', 'ed11y_bump_config_version', 10, 0 );
add_action( 'update_option_ed11y_plugin_settings', 'ed11y_bump_config_version', 10, 0 );
// CSA-side option storage (test routing, dev-mode selectors, role list).
// Settings-form save in CSA mode writes both options, which produces two
// version bumps for one logical save — harmless: every browser fetches the
// fresh static payload once either way.
add_action( 'add_option_ed11y_csa_plugin_settings', 'ed11y_bump_config_version', 10, 0 );
add_action( 'update_option_ed11y_csa_plugin_settings', 'ed11y_bump_config_version', 10, 0 );
// Custom-rules list (admin-defined tests). Bumps the cachebust so a new
// or removed rule reaches every browser without waiting for the next
// settings-page save.
add_action( 'add_option_ed11y_csa_custom_rules', 'ed11y_bump_config_version', 10, 0 );
add_action( 'update_option_ed11y_csa_custom_rules', 'ed11y_bump_config_version', 10, 0 );

// Network-defaults storage. Both the multisite `update_site_option_*`
// and the single-site `update_option_*` hooks fire — `update_site_option`
// transparently falls back to `update_option` on single-site installs,
// and which one fires depends on the request. Wire both so the cache
// busts no matter the multisite state.
add_action( 'add_site_option_ed11y_network_default_settings', 'ed11y_bump_config_version', 10, 0 );
add_action( 'update_site_option_ed11y_network_default_settings', 'ed11y_bump_config_version', 10, 0 );
add_action( 'add_option_ed11y_network_default_settings', 'ed11y_bump_config_version', 10, 0 );
add_action( 'update_option_ed11y_network_default_settings', 'ed11y_bump_config_version', 10, 0 );

add_action( 'add_site_option_ed11y_network_default_csa_settings', 'ed11y_bump_config_version', 10, 0 );
add_action( 'update_site_option_ed11y_network_default_csa_settings', 'ed11y_bump_config_version', 10, 0 );
add_action( 'add_option_ed11y_network_default_csa_settings', 'ed11y_bump_config_version', 10, 0 );
add_action( 'update_option_ed11y_network_default_csa_settings', 'ed11y_bump_config_version', 10, 0 );

// Network custom rules + per-site disable list — same dual-hook pattern.
add_action( 'add_site_option_ed11y_network_custom_rules', 'ed11y_bump_config_version', 10, 0 );
add_action( 'update_site_option_ed11y_network_custom_rules', 'ed11y_bump_config_version', 10, 0 );
add_action( 'add_option_ed11y_network_custom_rules', 'ed11y_bump_config_version', 10, 0 );
add_action( 'update_option_ed11y_network_custom_rules', 'ed11y_bump_config_version', 10, 0 );

add_action( 'add_option_ed11y_disabled_network_rules', 'ed11y_bump_config_version', 10, 0 );
add_action( 'update_option_ed11y_disabled_network_rules', 'ed11y_bump_config_version', 10, 0 );

/**
 * Loads the scripts for the plugin.
 */
function ed11y_load_scripts(): void {
	// Refuse to enqueue when the schema is in an unknown state so the checker
	// doesn't 500 on every dismiss; the admin notice surfaces a Retry button.
	if ( 'broken' === Installer::schema_state() ) {
		return;
	}
	$user               = wp_get_current_user();
	$allowed_roles      = array( 'editor', 'administrator', 'author', 'contributor' );
	$allowed_user_roles = array_intersect( $allowed_roles, $user->roles );

	if ( is_user_logged_in()
		&& ( $allowed_user_roles || current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ) )
	) {
		// added last two parameters 10/27/22 need to test.
		wp_enqueue_script_module( 'editoria11y-js', trailingslashit( ED11Y_ASSETS ) . 'lib/js/ed11y.esm.min.js', array(), Plugin::VERSION, array() );
		wp_enqueue_script( 'wp-api' );
		wp_enqueue_script_module( 'editoria11y-lang', trailingslashit( ED11Y_ASSETS ) . 'lib/js/lang/' . ed11y_lang_pack_filename() . '.js', array(), Plugin::VERSION, array() );
		wp_enqueue_script_module( 'editoria11y-js-shim', trailingslashit( ED11Y_ASSETS ) . 'js/editoria11y-wp.js', array( 'editoria11y-js', 'editoria11y-lang' ), Plugin::VERSION, array() );
		wp_enqueue_style( 'editoria11y-lib-css', trailingslashit( ED11Y_ASSETS ) . 'lib/css/editoria11y.min.css', null, Plugin::VERSION );
	}
}
add_action( 'wp_enqueue_scripts', 'ed11y_load_scripts' );

/**
 * Enqueue content assets but only in the Editor.
 */
function ed11y_enqueue_editor_content_assets() {

	if ( is_admin() ) {

		if ( 'broken' === Installer::schema_state() ) {
			return;
		}

		// Allowed roles.
		$user               = wp_get_current_user();
		$allowed_roles      = array( 'editor', 'administrator', 'author', 'contributor' );
		$allowed_user_roles = array_intersect( $allowed_roles, $user->roles );
		if ( ( $allowed_user_roles || current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ) ) && 'none' !== ed11y_get_setting( 'ed11y_livecheck' ) ) {
			wp_enqueue_script_module(
				'editoria11y-js',
				trailingslashit( ED11Y_ASSETS ) . 'lib/js/ed11y.esm.min.js',
				array(),
				Plugin::VERSION,
				array()
			);
			wp_enqueue_script( 'wp-api' );
			wp_enqueue_script_module(
				'editoria11y-lang',
				trailingslashit( ED11Y_ASSETS ) . 'lib/js/lang/' . ed11y_lang_pack_filename() . '.js',
				array(),
				Plugin::VERSION,
				array()
			);
			wp_enqueue_script_module(
				'editoria11y-editor',
				trailingslashit( ED11Y_ASSETS ) . 'js/editoria11y-editor.js',
				array( 'editoria11y-js', 'editoria11y-lang' ),
				Plugin::VERSION,
				array()
			);
			// Config flows through the JSON <script id="editoria11y-init"> block printed by ed11y_init();
			// wp_add_inline_script does not attach to script modules.
			wp_enqueue_style(
				'editoria11y-lib-css',
				trailingslashit( ED11Y_ASSETS ) . 'lib/css/editoria11y.min.css',
				null,
				Plugin::VERSION
			);
		}
	}
}
// Block-editor iframe loaders cannot consume `wp_enqueue_script_module` registrations
// (WP_Script_Modules only prints into wp_head/wp_footer/admin_print_footer_scripts).
// We run a single outer-page copy of the library and feed it the iframe body via
// fixedRoots. Gating on `wp_enqueue_editor` keeps the module off non-editor admin
// screens (Dashboard, Plugins, Users, …) where the init-JSON block is never printed
// and the script would crash trying to read it.

/**
 * Returns page-specific config for the Editoria11y library.
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 *
 * @param Object $user WP_User.
 * @param String $context Viewing or editing mode.
 */
function ed11y_get_params( object $user, string $context = 'viewing' ) {
	// Get settings array from cache, if available. The cache key is intentionally
	// 'editoria11y_settings' — earlier versions wrote to that key but read from
	// 'editoria11y_settinges' (typo), which meant the cache effectively never
	// hit and the blob was rebuilt on every editor page load.
	$ed1vals = get_site_transient( 'editoria11y_settings' );
	if ( false === $ed1vals ) {
		// Static (site-wide) keys come from the shared helper so the static
		// config REST endpoint and this per-page payload can never disagree.
		$ed1vals = ed11y_get_static_settings();
		// alertMode is per-request: the static helper omits it because the
		// post-modification heuristic below upgrades it to 'assertive' for
		// recently-edited posts. Pull the effective default here.
		$ed1vals['alertMode'] = ed11y_get_setting( 'ed11y_alert_mode' );
		set_site_transient( 'editoria11y_settings', $ed1vals, 360 );
	}

	// URL of the static config endpoint. Lives outside the transient cache
	// because the version segment can change between transient builds (any
	// settings save bumps the version, but only the admin-side save path
	// also clears the transient — bumping here keeps the URL fresh
	// regardless).
	$ed1vals['config_url'] = ed11y_get_config_url();

	$ed1vals['title'] = trim( wp_title( '', false, 'right' ) );

	// Get entity type and post id (if single).
	$ed1vals['post_id']     = get_the_ID();
	$ed1vals['entity_type'] = 'other';
	// Ref https://wordpress.stackexchange.com/questions/83887/return-current-page-type .
	if ( is_page() ) {
		$ed1vals['entity_type'] = is_front_page() ? 'Front' : 'Page';
	} elseif ( is_home() ) {
		$ed1vals['entity_type'] = 'Home';
		$ed1vals['post_id']     = 0;
	} elseif ( is_single() ) {
		$ed1vals['entity_type'] = ( is_attachment() ) ? 'Attachment' : 'Post';
	} elseif ( is_category() ) {
		$ed1vals['entity_type'] = 'Category';
		$ed1vals['post_id']     = 0;
	} elseif ( is_tag() ) {
		$ed1vals['entity_type'] = 'Tag';
		$ed1vals['post_id']     = 0;
	} elseif ( is_tax() ) {
		$ed1vals['entity_type'] = 'Taxonomy';
		$ed1vals['post_id']     = 0;
	} elseif ( is_archive() ) {
		$ed1vals['post_id'] = 0;
		if ( is_author() ) {
			$ed1vals['entity_type'] = 'Author';
		} else {
			$ed1vals['entity_type'] = 'Archive';
		}
	} elseif ( is_search() ) {
		$ed1vals['post_id']     = 0;
		$ed1vals['entity_type'] = 'Search';
	} elseif ( is_404() ) {
		$ed1vals['post_id']     = 0;
		$ed1vals['entity_type'] = '404';
	}

	global $wp;

	// Use permalink as sync URL if available, otherwise use query path.
	if ( $ed1vals['post_id'] > 0 ) {
		$ed1vals['currentPage'] = get_permalink( $ed1vals['post_id'] );
	} else {
		$ed1vals['currentPage'] = home_url( $wp->request );
	}

	// Mode is assertive from 0ms to 10minutes after a post is modified.
	$page_edited = get_post_modified_time( 'U', true );
	$page_edited = $page_edited ? abs( 1 + $page_edited - time() ) : false;
	if ( 'polite' === $ed1vals['alertMode'] && $page_edited && $page_edited < 600 ) {
		$ed1vals['alertMode'] = 'assertive';
	}

	if ( 'editing' === $context ) {
		$ed1vals = apply_filters( 'editoria11y_editing_params', $ed1vals );
	} else {
		$ed1vals = apply_filters( 'editoria11y_viewing_params', $ed1vals );
	}

	// Lazy-create DB if network activation failed.
	if ( ! Installer::check_tables() ) {
		// No DB available.
		$ed1vals['syncedDismissals'] = false;
		return $ed1vals;
	}

	// Pepper is shared with authenticated editors via the config blob so the
	// bundled library can compute matching element_id hashes locally — it
	// reads State.option.pepper for createDismissalKey() / dismissDigest().
	// It is intentionally scoped to users who already have permission to view
	// and dismiss alerts: the hash protects against anonymous DB-snapshot
	// leakage and cross-site ID collisions, not against authors who can
	// already see the elements.
	$ed1vals['pepper'] = Installer::get_pepper();

	// Page-scoped dismissals only — the okAll (site-wide) branch lives on
	// the static config endpoint where it can be browser-cached for 30 days
	// alongside the rest of the global config. The JS merge layer in
	// editoria11y-wp.js / editoria11y-editor.js combines both halves into a
	// single dictionary before construction.
	$ed1vals['syncedDismissals'] = ed11y_get_page_dismissals(
		(int) $user->ID,
		(string) $ed1vals['currentPage'],
		(int) $ed1vals['post_id']
	);

	// Per-user profile: 'dev' when the user's roles intersect the CSA
	// dev-roles CSV; 'content' otherwise. Stays in the per-page payload
	// (not the static API) because it varies per user and would defeat
	// the 30-day immutable cache there.
	//
	// Only emitted when CSA is active. `ed11y_get_user_profile()` always
	// returns one of two strings so direct callers get a stable value, but
	// a truthy profile here when CSA is inactive would make the bundled
	// JS split-config gate (`if (!options.profile) return` in
	// ed11yApplyCsaSplitConfiguration) run anyway — turning developer-tier
	// tests on in a CSA-inactive premium build. Mirrors Drupal, where
	// `dS.profile` exists only when the editoria11y_csa submodule is
	// active. The preprocessor strips the inner block from the free build,
	// where the JS that reads `profile` is itself stripped.
	$ed1vals['profile'] = false;

	return( $ed1vals );
}

/**
 * Initialize.
 */
function ed11y_init() {

	// Instantiates Editoria11y on the page for allowed users.
	if ( is_user_logged_in() ) {
		// Allowed roles.
		$user               = wp_get_current_user();
		$allowed_roles      = array( 'editor', 'administrator', 'author', 'contributor' );
		$allowed_user_roles = array_intersect( $allowed_roles, $user->roles );
		if ( $allowed_user_roles || current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ) ) {

			// At the moment, PHP escapes HTML breakouts. This would not be safe in other languages.
			echo '
			<script id="editoria11y-init" type="application/json">
				' . wp_json_encode( ed11y_get_params( $user ) ) . '
			</script>
			';
		}
	}
}
add_action( 'wp_footer', 'ed11y_init' );

/**
 * Add id to images if absent for edit links.
 *
 * @param Object $attr Existing image tag attributes.
 * @param Object $attachment Available metadata.
 *
 * @return Object
 */
function ed11y_add_attachment_id_on_images( $attr, $attachment ) {
	if ( ! isset( $attr['data-id'] ) && $attachment->ID ) {
		$attr['data-id'] = $attachment->ID;
	}
	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'ed11y_add_attachment_id_on_images', 10, 2 );

/**
 * Preserve query Args
 *
 * @param string $link The redirect URL.
 *
 * @return string
 */
function ed11y_old_slug_redirect_url_filter( $link ) {
	if ( isset( $_GET['ed1ref'] ) && isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'ed1ref' ) ) { // phpcs:ignore
		$link = add_query_arg( 'ed1ref', intval( $_GET['ed1ref'] ), $link ); // phpcs:ignore
	}
	return $link;
}
add_filter( 'old_slug_redirect_url', 'ed11y_old_slug_redirect_url_filter' );


/**
 * Load live checker when editor is present.
 * */
function ed11y_editor_init() {
	if ( 'none' !== ed11y_get_setting( 'ed11y_livecheck' ) ) {
		ed11y_enqueue_editor_content_assets();
		add_action( 'admin_footer', 'ed11y_init' );
	}
}
add_action( 'wp_enqueue_editor', 'ed11y_editor_init' );
