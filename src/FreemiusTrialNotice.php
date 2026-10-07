<?php
/**
 * Limit the Freemius trial promotion ("If you like Editoria11y …, consider
 * supporting its development; start with a 30-day free trial …") to one
 * display per site per plugin version.
 *
 * The SDK adds that promotion (`Freemius::_add_trial_notice()`) as a
 * STICKY notice with id `trial_promotion`: once added it renders on
 * every admin page until an admin clicks its dismiss X, and the SDK
 * re-adds it every 30 days after that. The premium build never reaches
 * it (`is_premium()` short-circuits the method outside WP_FS__DEV_MODE),
 * so in practice this governs the free build — the class is registered
 * in both builds and must not be premium-gated.
 *
 * Mechanism:
 *
 *   - `show_trial` (checked in `_add_trial_notice()` on admin_init)
 *     returns false once this site has already shown the promotion for
 *     the running Plugin::VERSION, so the SDK never re-adds it on its
 *     30-day cadence. Install and every version change reset the gate.
 *   - `show_first_trial_after_n_sec` / `reshow_trial_after_every_n_sec`
 *     are zeroed: the version gate replaces the SDK's own timers, so the
 *     promotion appears on the first admin page after install / update
 *     instead of 24 hours or 30 days later.
 *   - `show_admin_notice` lets the promotion render, then records the
 *     version and removes the sticky. Recording at render time (not at
 *     add time) means an admin request that never prints notices — a
 *     redirect, a screen that suppresses them — doesn't consume the one
 *     showing. Removal happens mid-render, which is safe: the notice
 *     manager iterates a copy of its notice array.
 *
 * Per-site option, matching the SDK's per-site sticky storage; on a
 * network-active install each delegated subsite shows it once.
 *
 * @package Editoria11y
 */

namespace Editoria11y;

defined( 'ABSPATH' ) || exit;

/**
 * Once-per-version gate for the Freemius trial promotion.
 */
final class FreemiusTrialNotice {

	/**
	 * SDK notice id for the trial promotion (see `_add_trial_notice()`).
	 */
	const NOTICE_ID = 'trial_promotion';

	/**
	 * Option holding the Plugin::VERSION the promotion was last shown for.
	 */
	const OPTION = 'ed11y_trial_notice_version';

	/**
	 * The SDK instance, kept for `remove_sticky()` at render time.
	 *
	 * @var \Freemius|null
	 */
	private static $sdk = null;

	/**
	 * Wire the SDK filters against the live SDK instance.
	 *
	 * @param \Freemius $ed11ycsa Live SDK instance returned by fs_dynamic_init().
	 */
	public static function apply( \Freemius $ed11ycsa ): void {
		self::$sdk = $ed11ycsa;
		$ed11ycsa->add_filter( 'show_trial', array( __CLASS__, 'filter_show_trial' ) );
		$ed11ycsa->add_filter( 'show_first_trial_after_n_sec', '__return_zero' );
		$ed11ycsa->add_filter( 'reshow_trial_after_every_n_sec', '__return_zero' );
		$ed11ycsa->add_filter( 'show_admin_notice', array( __CLASS__, 'filter_show_admin_notice' ), 10, 2 );
	}

	/**
	 * Freemius `show_trial` filter: allow the SDK to add the promotion
	 * only if it hasn't been shown for the running plugin version.
	 *
	 * @param bool $show Whether the SDK would otherwise show the promotion.
	 * @return bool
	 */
	public static function filter_show_trial( $show ) {
		return $show && ! self::shown_for_current_version();
	}

	/**
	 * Freemius `show_admin_notice` filter.
	 *
	 * Passes every other notice through unchanged. For the trial
	 * promotion, lets this render through, then marks the version as
	 * shown and removes the sticky so later page loads don't repeat it.
	 *
	 * @param bool                $show Whether the SDK would otherwise render this notice.
	 * @param array<string,mixed> $msg  Notice descriptor (see FS_Admin_Notice_Manager).
	 * @return bool
	 */
	public static function filter_show_admin_notice( $show, $msg ) {
		if ( true !== $show || self::NOTICE_ID !== ( $msg['id'] ?? '' ) ) {
			return $show;
		}

		update_option( self::OPTION, Plugin::VERSION );

		if ( null !== self::$sdk ) {
			self::$sdk->remove_sticky( self::NOTICE_ID );
		}

		return $show;
	}

	/**
	 * Whether this site already showed the promotion for Plugin::VERSION.
	 */
	private static function shown_for_current_version(): bool {
		return Plugin::VERSION === get_option( self::OPTION, '' );
	}
}
