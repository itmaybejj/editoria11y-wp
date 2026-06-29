<?php
/**
 * Centralized i18n overrides for the bundled Freemius SDK.
 *
 * The SDK ships English copy with notice titles like "Yee-haw!" and
 * "Hmm..." that don't fit Editoria11y's plain, professional voice. It
 * also uses generic "Pro / plan / premium" terminology where we want
 * "Editoria11y CSA" specifically. We intercept those strings via the
 * SDK's documented `fs_override_i18n()` API so we don't fork vendor/.
 *
 * Each override is keyed by the SDK's internal string key (the third
 * arg to `*_text_x_inline` / second arg to `*_text_inline`) — NOT by
 * the English text. The SDK looks up `$fs_text_overrides[$slug][$key]`
 * before falling back to its inline default, so our replacements take
 * effect regardless of the active locale.
 *
 * The `entries()` method is the source of truth: each entry pins the
 * `original` English we reviewed against, the `replacement` we chose,
 * and a `note` explaining the surrounding pairing. The audit script at
 * `scripts/audit-freemius-strings.php` reads `entries()` and diffs the
 * `original` field against the live SDK source so a Freemius bump
 * surfaces drift before it ships to users.
 *
 * @package Editoria11y
 */

namespace Editoria11y;

defined( 'ABSPATH' ) || exit;

/**
 * Registers replacement strings against the SDK's text-override
 * registry.
 */
final class FreemiusOverrides {

	/**
	 * Override keys the SDK resolves through the *global*
	 * `fs_text_inline()` with no slug argument, so the lookup hits the
	 * shared `'freemius'` namespace instead of our module slug — which
	 * means apply()'s module-slug `override_i18n()` registration never
	 * reaches them.
	 *
	 * Currently a single key: the opt-in "why" line at
	 * templates/connect.php:235. Its three sibling calls (226/237/240)
	 * all pass `$slug`; line 235 omits it — an SDK inconsistency, not
	 * ours. apply() mirrors these into the 'freemius' namespace so the
	 * override actually lands. Keep the list minimal: anything here also
	 * overrides the same string for every other Freemius-based plugin on
	 * the site.
	 *
	 * @var string[]
	 */
	private const GLOBAL_SLUG_KEYS = array( 'connect-message_on-update_why' );

	/**
	 * Apply our overrides against the running Freemius instance.
	 *
	 * Most SDK strings are emitted through the instance text methods
	 * (`$fs->get_text_inline()` etc.), which inject the module slug, so
	 * the instance method `override_i18n()` — which registers under that
	 * same slug — is all they need. The exceptions in GLOBAL_SLUG_KEYS
	 * are read via the *global* `fs_text_inline()` under the default
	 * 'freemius' slug, so we mirror just those into the shared namespace
	 * below.
	 *
	 * Safe to call once per request after fs_dynamic_init() returns;
	 * sticky notices read the title at the time of `add_sticky()`, so
	 * applying the overrides in the same bootstrap as fs_dynamic_init
	 * is sufficient — by the time a user-facing notice renders on a
	 * later admin request, the override map is already populated.
	 *
	 * @param \Freemius $ed11ycsa Live SDK instance returned by
	 *                            fs_dynamic_init().
	 */
	public static function apply( \Freemius $ed11ycsa ): void {
		$map = self::map();
		$ed11ycsa->override_i18n( $map );

		// GLOBAL_SLUG_KEYS are looked up under the default 'freemius'
		// slug rather than ours, so register them there too. Guarded so
		// the unit test — which stubs \Freemius but not the SDK's global
		// functions — does not fatal; in a real request the SDK defined
		// fs_override_i18n() long before our bootstrap runs.
		if ( function_exists( 'fs_override_i18n' ) ) {
			$global = array();
			foreach ( self::GLOBAL_SLUG_KEYS as $key ) {
				if ( isset( $map[ $key ] ) ) {
					$global[ $key ] = $map[ $key ];
				}
			}
			if ( ! empty( $global ) ) {
				fs_override_i18n( $global, 'freemius' );
			}
		}
	}

	/**
	 * Return the override map keyed for `fs_override_i18n()`.
	 *
	 * Plain `key => replacement` shape derived from `entries()`.
	 *
	 * @return array<string, string>
	 */
	public static function map(): array {
		$out = array();
		foreach ( self::entries() as $key => $entry ) {
			$out[ $key ] = $entry['replacement'];
		}
		return $out;
	}

	/**
	 * Annotated override entries — used by the audit script and tests.
	 *
	 * Categories:
	 *   - Tier 1 — interjection notice titles ("Yee-haw", "Oops",
	 *     etc.). Each is concatenated with "!" or "..." by the caller.
	 *   - Tier 2 — body strings paired with Tier 1 titles. Rewritten
	 *     to use Editoria11y CSA naming and avoid word duplication.
	 *   - Tier 3 — generic "Pro / premium / plan" copy retargeted to
	 *     Editoria11y CSA.
	 *   - Tier 4 — informal phrasing ("Thanks so much!", "Hey there",
	 *     "AWESOME") softened to plain professional voice.
	 *
	 * @return array<string, array{original:string, replacement:string, note:string}>
	 */
	public static function entries(): array {
		return array(

			/* === Tier 1 — interjection notice titles === */

			'yee-haw'                                    => array(
				'original'    => 'Yee-haw',
				'replacement' => 'Success',
				'note'        => 'Notice title shown after license purchase / plan upgrade. Pairs with body strings rewritten in Tier 2.',
			),
			'woot'                                       => array(
				'original'    => 'W00t',
				'replacement' => 'Success',
				'note'        => 'Notice title after install of premium version to filesystem',
			),
			'oops'                                       => array(
				'original'    => 'Oops',
				'replacement' => 'Error',
				'note'        => 'Notice title for ~10 distinct error states. Caller appends "..." → renders as "Error..."',
			),
			'right-on'                                   => array(
				'original'    => 'Right on',
				'replacement' => 'Verified',
				'note'        => 'Notice title shown after email verification.',
			),
			'hmm'                                        => array(
				'original'    => 'Hmm',
				'replacement' => 'Notice',
				'note'        => 'Notice title for ambiguous license-sync / plan-mismatch states. Caller appends "..." → "Notice..."',
			),
			'awesome'                                    => array(
				'original'    => 'Awesome',
				'replacement' => 'Success',
				'note'        => 'Notice title after successful opt-in.',
			),
			'hey'                                        => array(
				'original'    => 'Hey',
				'replacement' => 'Welcome',
				'note'        => 'Concatenated as "Hey! " before the trial promo body. With "Welcome" the resulting sentence reads naturally.',
			),

			/* === Tier 2 — paired body strings === */

			'connect-message'                            => array(
				'original'    => 'Opt in to get email notifications for security & feature updates, educational content, and occasional offers, and to share some basic WordPress environment info. This will help us make the %s more compatible with your site and better at doing what you need it to.',
				'replacement' => 'Opt in to allow Freemius to send email notifications for security and product updates, and to match licenses and support tickets to your site (relevant permissions listed below). The Editoria11y maintainers have gone 6 years without sending a single email, so you should not expect much after an initial automated opt-in verification and trial offer.',
				'note'        => 'Fresh-install opt-in body (Freemius present from first activation; SDK connect.php "! is_plugin_update" branch). Users who instead update into a Freemius build see the connect-message_on-update* variants below.',
			),
			'connect-message_on-update_why'              => array(
				'original'    => 'We have introduced this opt-in so you never miss an important update and help us make the %s more compatible with your site and better at doing what you need it to.',
				'replacement' => 'Opt in to allow Freemius to send email notifications for security and product updates, and to match licenses and support tickets to your site (relevant permissions listed below). The Editoria11y maintainers have gone 6 years without sending a single email, so you should not expect much after an initial automated opt-in verification and trial offer.',
				'note'        => 'First paragraph of the opt-in body shown when Freemius is added during a plugin update; this is the path most existing free users hit. Carries the entire replacement; connect-message_on-update and _skip are blanked so the two-paragraph SDK message collapses to this single sentence. The SDK hardcodes <br><br> after this string (connect.php), so a small trailing gap before the buttons remains that i18n overrides cannot remove. Unlike its siblings, connect.php:235 looks this key up without a module slug, so apply() also mirrors it under the shared "freemius" slug — see GLOBAL_SLUG_KEYS.',
			),
			'connect-message_on-update'                  => array(
				'original'    => 'Opt in to get email notifications for security & feature updates, educational content, and occasional offers, and to share some basic WordPress environment info.',
				'replacement' => '',
				'note'        => 'Second paragraph of the on-update opt-in body. Blanked so the message collapses into connect-message_on-update_why above.',
			),
			'connect-message_on-update_skip'             => array(
				'original'    => 'If you skip this, that\'s okay! %1$s will still work just fine.',
				'replacement' => '',
				'note'        => 'Trailing "you can skip this" sentence, appended only when anonymous mode is enabled. Blanked to collapse into connect-message_on-update_why above. Original keeps %1$s so the audit diff still matches the SDK source.',
			),
			'few-plugin-tweaks'                          => array(
				'original'    => 'We made a few tweaks to the %s, %s',
				'replacement' => 'Editoria11y CSA %s has been activated. %s',
				'note'        => 'Opt in whining in activation mode, first half.',
			),
			'optin-x-now'                                => array(
				'original'    => 'Opt in to make "%s" better!',
				'replacement' => 'Manage privacy settings and notifications for security and product updates.',
				'note'        => 'Opt in whining in activation mode, first half.',
			),
			'plan-activated-message'                     => array(
				'original'    => 'Your plan was successfully activated.',
				'replacement' => 'Your Editoria11y CSA license is now active.',
				'note'        => 'Body paired with "Success!" title. Avoids repeating "Success".',
			),
			'plan-upgraded-message'                      => array(
				'original'    => 'Your plan was successfully upgraded.',
				'replacement' => 'Your Editoria11y CSA plan has been updated.',
				'note'        => 'Body paired with "Success!" title.',
			),
			'license-activated-message'                  => array(
				'original'    => 'Your license was successfully activated.',
				'replacement' => 'Your Editoria11y CSA license is now active.',
				'note'        => 'Body shown on manual license activation.',
			),
			'addon-successfully-upgraded-message'        => array(
				'original'    => 'Your %s Add-on plan was successfully upgraded.',
				'replacement' => 'Your Editoria11y CSA add-on has been updated.',
				'note'        => 'Body for add-on upgrade. Drops the %s arg — sprintf safely ignores extras.',
			),
			'plan-changed-to-x-message'                  => array(
				'original'    => 'Your plan was successfully changed to %s.',
				'replacement' => 'Your Editoria11y CSA plan was changed to %s.',
				'note'        => 'Preserves the %s plan-title placeholder.',
			),
			'premium-activated-message'                  => array(
				'original'    => 'Premium %s version was successfully activated.',
				'replacement' => 'Editoria11y CSA features are now active.',
				'note'        => 'Body shown when premium build replaces free build. %s drops harmlessly.',
			),
			'activation-with-plan-x-message'             => array(
				'original'    => 'Your account was successfully activated with the %s plan.',
				'replacement' => 'Your account was activated with the %s plan.',
				'note'        => 'Drops redundant adverb. Preserves %s.',
			),
			'you-have-x-license'                         => array(
				'original'    => 'You have purchased a %s license.',
				'replacement' => 'Your Editoria11y CSA license has been activated.',
				'note'        => 'Body of the post-checkout sticky notice. %s drops harmlessly.',
			),
			'email-verified-message'                     => array(
				'original'    => 'Your email has been successfully verified - you are AWESOME!',
				'replacement' => 'Your email address has been verified.',
				'note'        => 'Drops the all-caps celebratory tail.',
			),

			/* === Tier 3 — generic "Pro / premium / plan" copy retargeted === */

			'trial-x-promotion-message'                  => array(
				'original'    => 'How do you like %s so far? Test all our %s premium features with a %d-day free trial.',
				'replacement' => 'How do you like %s so far? Try the Editoria11y CSA feature set with a %2$d-day free trial.',
				'note'        => 'Drops the %s plan title (sprintf safely ignores extras). Uses %2$d to skip past it to the trial period int.',
			),
			'license-expired-non-blocking-message'       => array(
				'original'    => 'Your license has expired. You can still continue using all the %s features, but you\'ll need to renew your license to continue getting updates and support.',
				'replacement' => 'Your Editoria11y CSA license has expired. You can keep using the features you have, but renew your license to continue receiving updates and support.',
				'note'        => 'Drops "%s features" generic phrasing. %s drops harmlessly.',
			),
			'activate-premium-version'                   => array(
				'original'    => ' The paid version of %1$s is already installed. Please activate it to start benefiting from the %2$s features. %3$s',
				'replacement' => ' The Editoria11y CSA build of %1$s is already installed. Activate it to use the CSA features. %3$s',
				'note'        => 'Replaces "paid version" / "%2$s features" with explicit CSA naming. Preserves trailing %3$s call-to-action link.',
			),
			'activate-premium-version-plugins-page'      => array(
				'original'    => ' The paid version of %1$s is already installed. Please navigate to the %2$s to activate it and start benefiting from the %3$s features.',
				'replacement' => ' The Editoria11y CSA build of %1$s is already installed. Navigate to %2$s to activate it and use the CSA features.',
				'note'        => 'Same retarget as activate-premium-version. %2$s = "plugins page" link. Drops %3$s plan-title arg (sprintf ignores extras).',
			),
			'permissions-profile_tooltip'                => array(
				'original'    => 'Never miss important updates, get security warnings before they become public knowledge, and receive notifications about special offers and awesome new features.',
				'replacement' => 'Never miss important updates or security notices, and get notifications about noteworthy new features.',
				'note'        => 'Drops "awesome".',
			),

			/* === Tier 4 — informal phrasing softened === */

			'thank-you-for-using-product'                => array(
				'original'    => 'Thank you so much for using %s!',
				'replacement' => 'Thank you for using %s.',
				'note'        => 'Drops effusive "so much" and exclamation.',
			),
			'thank-you-for-using-products'               => array(
				'original'    => 'Thank you so much for using our products!',
				'replacement' => 'Thank you for using our products.',
				'note'        => 'Multi-product variant.',
			),
			'thank-you-for-using-product-and-its-addons' => array(
				'original'    => 'Thank you so much for using %s and its add-ons!',
				'replacement' => 'Thank you for using %s and its add-ons.',
				'note'        => 'Add-on variant.',
			),
			'become-an-ambassador-admin-notice'          => array(
				'original'    => 'Hey there, did you know that %s has an affiliate program? If you like the %s you can become our ambassador and earn some cash!',
				'replacement' => '%s has an affiliate program. If you would like to refer others to %s, you can earn a commission as an ambassador.',
				'note'        => 'Drops "Hey there" and "earn some cash". Preserves both %s placeholders.',
			),
		);
	}
}
