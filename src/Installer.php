<?php
/**
 * Schema, migration, rehash worker, and lifecycle hooks for the editoria11y plugin tables.
 *
 * The Editoria11y class is the runtime hook orchestrator; this class owns every
 * data-side concern: creating the three plugin tables, walking the
 * editoria11y_db_version state machine forward, running the dismissal
 * element_id rehash worker via WP-Cron and the inline progress UI, and
 * dropping the tables on uninstall.
 *
 * Version state machine (driven by the editoria11y_db_version option):
 *
 * | Version                | Meaning                                                       |
 * |------------------------|---------------------------------------------------------------|
 * | 1.2                    | Pre-v3 schema (no dev_total/dev_count/result_name/stale_date) |
 * | 1.3-failed             | Mid-migrate_to_1_3() ADD COLUMN run. Sticky until retry.      |
 * | 1.3                    | New columns present; pepper generated; element_id varchar.    |
 * | 2.0-migrating          | Cron / inline UI is migrating dismissal rows cursor-by-cursor:|
 * |                        | translating result_key (v2 camelCase → v3 UPPER_SNAKE) and    |
 * |                        | re-hashing element_id against the new key.                    |
 * | 2.0-narrow-pending     | Cursor reached MAX(id); ready for the element_id column       |
 * |                        | narrow.                                                       |
 * | 2.0-failed             | MODIFY element_id char(64) failed. Schema is functional but   |
 * |                        | column is wide; sticky until retry.                           |
 * | 1.4                    | Legacy alias for "all migration steps complete." Pre-v3 sites |
 * |                        | that completed the original WP plugin's 1.2 → 1.4 migration   |
 * |                        | before the v3 result_key translation landed; treated as a     |
 * |                        | transient state — check_tables() pushes it through a one-shot |
 * |                        | key-translation pass and advances to 2.0. Fresh installs and  |
 * |                        | newly-completed migrations skip 1.4 entirely.                 |
 * | 2.0                    | Final v3 shape: hashed element_ids, UPPER_SNAKE result_keys,  |
 * |                        | aligned with the bundled JS library.                          |
 *
 * The '-failed' markers are written BEFORE the destructive step and only
 * flipped to the success value AFTER it completes, so any partial DDL leaves
 * the marker as a circuit breaker. retry_migration() rolls back one state
 * and re-runs.
 *
 * @package Editoria11y
 */

namespace Editoria11y;

use Editoria11y\UpdateHelpers;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Installer / migrator / rehash worker for editoria11y plugin tables.
 */
class Installer {

	/** Batch size for the dismissal element_id rehash worker. */
	const REHASH_BATCH_SIZE = 250;

	/** Cron action name for the rehash worker. */
	const REHASH_CRON_HOOK = 'editoria11y_rehash_dismissals';

	/** Custom cron schedule slug registered in cron_schedules. */
	const REHASH_CRON_SCHEDULE = 'editoria11y_five_minutes';

	/**
	 * Result keys that are dropped during the v3 migration rather than carried
	 * forward.
	 *
	 * Source: Drupal's `editoria11y_update_9011()` drop list (file at
	 * `/Users/jj/Sites/ed11yddev/web/modules/custom/editoria11y/editoria11y.install`
	 * around line 416). The comment there marks them "untranslatable keys"
	 * pending a discussion with the upstream maintainer; we mirror the same
	 * decision so the WP migration matches Drupal's data shape.
	 *
	 * Existing dismissal/result rows in the WP plugin that translate to one
	 * of these keys are deleted on the way through the migration. Future
	 * scans may produce fresh dismissals with these keys (the JS library
	 * still emits them); those are stored normally.
	 */
	const DROP_KEYS = array(
		'TABLES_SEMANTIC_HEADING',
		'TABLES_EMPTY_HEADING',
	);

	// ------------------------------------------------------------------
	// Lifecycle hooks
	// ------------------------------------------------------------------

	/**
	 * Plugin activation.
	 *
	 * Eagerly runs check_tables() so a fresh single-site install lands at
	 * version 2.0 before any admin page renders. check_tables() is cheap on
	 * the hot path (one autoloaded option read once version is '2.0'), so
	 * repeat activate/deactivate cycles don't pay measurable DDL cost.
	 *
	 * Note: WordPress only fires this hook on the main blog when a plugin
	 * is network-activated; subsites still rely on the lazy check_tables()
	 * calls in the runtime read paths (and in MigrationPanel) to seed their
	 * own schema on first admin/editor request.
	 */
	public static function activate(): void {
		self::check_tables();
	}

	/**
	 * Plugin deactivation. Drops scheduled work; data is preserved.
	 */
	public static function deactivate(): void {
		self::unschedule_rehash();
	}

	/**
	 * Plugin uninstall: drops all three tables and clears every option the
	 * migration touches plus the settings transient.
	 *
	 * Multisite behavior: WordPress fires register_uninstall_hook once per
	 * blog when network-uninstalling, so the per-blog DROP / delete_option
	 * calls below do the right thing on every site. Network-scoped options
	 * (stored in wp_sitemeta) need to be cleared too, but only need to be
	 * cleared once for the whole network. delete_site_option() is
	 * idempotent on multisite and falls through to delete_option on
	 * single-site, so we can call it unconditionally on every invocation
	 * without worrying about which blog context fires last.
	 */
	public static function uninstall(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		global $wpdb;

		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ed11y_dismissals" ); // phpcs:ignore
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ed11y_results" ); // phpcs:ignore
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ed11y_urls" ); // phpcs:ignore

		delete_option( 'ed11y_plugin_settings' );
		// CSA-side options shipped with the v3 settings split. Always
		// remove on uninstall regardless of whether the plugin is currently
		// running its premium build, so a downgrade-then-uninstall path
		// can't strand orphan rows in the options table.
		delete_option( 'ed11y_csa_plugin_settings' );
		delete_option( 'ed11y_csa_custom_rules' );
		delete_option( 'ed11y_config_version' );
		delete_option( 'editoria11y_db_version' );
		delete_option( 'editoria11y_id_pepper' );
		delete_option( 'editoria11y_rehash_cursor' );
		delete_site_transient( 'editoria11y_settings' );

		// Network-scoped options written by the multisite super-admin
		// pages and the CSA license worker. Safe to call on single-site
		// too — delete_site_option falls through to delete_option there.
		delete_site_option( 'ed11y_network_default_settings' );
		delete_site_option( 'ed11y_network_default_csa_settings' );
		delete_site_option( 'ed11y_network_custom_rules' );
		delete_site_option( 'ed11ycsa_network_license_state' );
	}

	/**
	 * `wpmu_drop_tables` filter callback: include the plugin's three
	 * tables in the list WordPress drops when a subsite is deleted.
	 *
	 * Wired from editoria11y.php at file load (cheap — no DB access
	 * outside the actual delete event). Uses `$wpdb->get_blog_prefix()`
	 * so the table names are scoped to the blog being deleted, not the
	 * current request's blog.
	 *
	 * @param string[] $tables  Existing tables WP plans to drop.
	 * @param int      $blog_id Blog being deleted.
	 * @return string[]
	 */
	public static function wpmu_drop_tables_filter( array $tables, int $blog_id ): array {
		global $wpdb;
		$prefix   = $wpdb->get_blog_prefix( $blog_id );
		$tables[] = $prefix . 'ed11y_dismissals';
		$tables[] = $prefix . 'ed11y_results';
		$tables[] = $prefix . 'ed11y_urls';
		return $tables;
	}

	// ------------------------------------------------------------------
	// Schema
	// ------------------------------------------------------------------

	/**
	 * Provides DB table schema for fresh installs.
	 *
	 * Defines the target shape (v3) so a fresh install lands directly at
	 * editoria11y_db_version=2.0 — no rehash needed for sites that never had
	 * v2 data. Existing v1.2 sites: maybe_create_table is a no-op and the new
	 * columns / element_id type narrowing are handled by migrate_to_1_3() and
	 * narrow_element_id() under check_tables() orchestration.
	 */
	public static function create_database(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$table_urls       = $wpdb->prefix . 'ed11y_urls';
		$table_results    = $wpdb->prefix . 'ed11y_results';
		$table_dismissals = $wpdb->prefix . 'ed11y_dismissals';

		// ENGINE=InnoDB is explicit because the FOREIGN KEY clauses below silently
		// no-op on MyISAM (which some legacy hosts still default to). Charset/collation
		// follows the site default. element_id is CHARACTER SET ascii to keep the
		// 64-byte hex hash from reserving 4 bytes/char in utf8mb4 (saves ~192 B/row
		// of buffer-pool pressure on busy multisite installs).
		$sql_urls = "CREATE TABLE $table_urls (
			pid int(9) unsigned AUTO_INCREMENT NOT NULL,
			post_id int(9) unsigned NOT NULL default '0',
			page_url varchar(190) NOT NULL,
			entity_type varchar(255) NOT NULL,
			page_title varchar(1024) NOT NULL,
			page_total smallint(4) unsigned NOT NULL,
			dev_total int unsigned NOT NULL default '0',
			PRIMARY KEY pid (pid),
			KEY page_url (page_url),
			KEY post_id (post_id)
			) ENGINE=InnoDB $charset_collate;";

		$sql_results = "CREATE TABLE $table_results (
			pid int(9) unsigned NOT NULL,
			result_key varchar(32) NOT NULL,
			result_count smallint(4) NOT NULL,
			dev_count int unsigned NOT NULL default '0',
			result_name varchar(255) NOT NULL default '',
			created datetime DEFAULT current_timestamp NOT NULL,
			updated datetime DEFAULT current_timestamp NOT NULL,
			PRIMARY KEY (pid, result_key),
			FOREIGN KEY(pid) REFERENCES $table_urls (pid) ON DELETE CASCADE
			) ENGINE=InnoDB $charset_collate;";

		// The composite (pid, result_key, element_id) covers every WHERE the
		// dismissals controllers run today: per-page reset DELETE, per-page
		// stale-touch UPDATE, and the leftmost-prefix `pid` lookups the read
		// path uses. We deliberately do NOT add a separate single-column pid
		// or element_id index — the composite serves both via leftmost-prefix.
		$sql_dismissals = "CREATE TABLE $table_dismissals (
			id int(9) unsigned AUTO_INCREMENT NOT NULL,
			pid int(9) unsigned NOT NULL,
			result_key varchar(32) NOT NULL,
			user smallint(6) unsigned NOT NULL,
			element_id char(64) CHARACTER SET ascii NOT NULL default '',
			dismissal_status varchar(64) NOT NULL,
			result_name varchar(255) NOT NULL default '',
			created datetime DEFAULT current_timestamp NOT NULL,
			updated datetime DEFAULT current_timestamp NOT NULL,
			stale tinyint(1) NOT NULL default '0',
			stale_date datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY pid_result_key_element_id (pid, result_key, element_id),
			KEY user (user),
			KEY dismissal_status (dismissal_status),
			FOREIGN KEY(pid) REFERENCES $table_urls (pid) ON DELETE CASCADE
			) ENGINE=InnoDB $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		maybe_create_table( $table_urls, $sql_urls );
		maybe_create_table( $table_results, $sql_results );
		maybe_create_table( $table_dismissals, $sql_dismissals );

		// v1.0 → v1.2: backfill post_id on the urls table when missing.
		// Kept verbatim from the old code path — sites still on v1.0 hit this on
		// the way through. Use column-existence rather than column count so future
		// schema additions don't accidentally re-trigger the ALTER.
		$has_post_id = false;
		foreach ( $wpdb->get_results( "DESC $table_urls", ARRAY_A ) as $col ) { // phpcs:ignore
			if ( 'post_id' === $col['Field'] ) {
				$has_post_id = true;
				break;
			}
		}
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Schema-mutation block. Table identifiers come from $wpdb->prefix . literals (never user input), placeholders %1s are intentional for identifiers (not values), the cache is irrelevant for one-shot DDL, and the path is gated by check_tables() so duplicate schema work is prevented above this scope.
		if ( ! $has_post_id ) {
			$wpdb->query(
				"ALTER TABLE $table_urls
				ADD post_id int(9) unsigned NOT NULL default 0,
				DROP PRIMARY KEY, ADD PRIMARY KEY pid ( pid ),
				ADD KEY post_id (post_id)
				;"
			);
		}

		// Add foreign keys not reliably handled by maybe_create_table.
		$results_create_table_sql_row = $wpdb->get_row( "SHOW CREATE TABLE $table_results" );
		if ( $results_create_table_sql_row ) {
			$results_create_table_sql = $results_create_table_sql_row->{'Create Table'};
			$results_constraint       = $wpdb->prefix . 'ed11y_results_pid';

			$result_constraint_matches = preg_match( '/CONSTRAINT `(.+?)` FOREIGN KEY \(`pid`\)/', $results_create_table_sql, $result_matches );

			$result_foreign_key = null;
			if ( $result_constraint_matches ) {
				$result_foreign_key = $result_matches[1];
			}

			if ( $result_foreign_key ) {
				try {
					// MySQL syntax.
					$wpdb->get_var( // phpcs:ignore
						$wpdb->prepare(
							"ALTER TABLE $table_results
					DROP FOREIGN KEY %1s;",
							array( $result_foreign_key )
						)
					);
				} catch ( Exception $e ) {
					// MariaDB syntax.
					$wpdb->get_var( // phpcs:ignore
						$wpdb->prepare(
							"ALTER TABLE $table_results
					DROP CONSTRAINT %1s;",
							array( $result_foreign_key )
						)
					);
				} finally {
					// Add replacement constraint.
					$wpdb->get_var( // phpcs:ignore
						$wpdb->prepare(
							"ALTER TABLE $table_results
					ADD CONSTRAINT %1s FOREIGN KEY(pid) REFERENCES $table_urls (pid) ON DELETE CASCADE",
							$results_constraint
						)
					);
				}
			} else {
				// Add new constraint.
				$wpdb->get_var( // phpcs:ignore
					$wpdb->prepare(
						"ALTER TABLE $table_results
					ADD CONSTRAINT %1s FOREIGN KEY(pid) REFERENCES $table_urls (pid) ON DELETE CASCADE",
						$results_constraint
					)
				);
			}
		}

		$dismissal_create_table_sql_row = $wpdb->get_row( "SHOW CREATE TABLE $table_dismissals" );
		if ( $dismissal_create_table_sql_row ) {
			$dismissal_create_table_sql = $dismissal_create_table_sql_row->{'Create Table'};
			$dismissal_constraint       = $wpdb->prefix . 'ed11y_dismissal_pid';

			$dismissal_constraint_matches = preg_match( '/CONSTRAINT `(.+?)` FOREIGN KEY \(`pid`\)/', $dismissal_create_table_sql, $dismissal_matches );

			$dismissal_key = null;
			if ( $dismissal_constraint_matches ) {
				$dismissal_key = $dismissal_matches[1];
			}

			if ( $dismissal_key ) {
				try {
					// MySQL syntax.
					$wpdb->get_var( // phpcs:ignore
						$wpdb->prepare(
							"ALTER TABLE $table_dismissals
						DROP FOREIGN KEY %1s;",
							array( $dismissal_key )
						)
					);
				} catch ( Exception $e ) {
					// MariaDB syntax.
					$wpdb->get_var( // phpcs:ignore
						$wpdb->prepare(
							"ALTER TABLE $table_dismissals
						DROP CONSTRAINT %1s;",
							array( $dismissal_key )
						)
					);
				} finally {
					// Add new constraint.
					$wpdb->get_var( // phpcs:ignore
						$wpdb->prepare(
							"ALTER TABLE $table_dismissals
						ADD CONSTRAINT %1s FOREIGN KEY(pid) REFERENCES $table_urls (pid) ON DELETE CASCADE",
							$dismissal_constraint
						)
					);
				}
			} else {
				$wpdb->get_var( // phpcs:ignore
					$wpdb->prepare(
						"ALTER TABLE $table_dismissals
						ADD CONSTRAINT %1s FOREIGN KEY(pid) REFERENCES $table_urls (pid) ON DELETE CASCADE",
						$dismissal_constraint
					)
				);
			}
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * Detect whether ed11y_urls already carries the v3 columns.
	 *
	 * Used to short-circuit migration on a fresh install where create_database()
	 * produced the target shape directly.
	 */
	public static function tables_already_v3(): bool {
		global $wpdb;
		foreach ( $wpdb->get_results( "DESC {$wpdb->prefix}ed11y_urls", ARRAY_A ) as $col ) { // phpcs:ignore
			if ( 'dev_total' === $col['Field'] ) {
				return true;
			}
		}
		return false;
	}

	// ------------------------------------------------------------------
	// Migration orchestration
	// ------------------------------------------------------------------

	/**
	 * Make sure tables are in place and up to date.
	 *
	 * Forward-only state machine driven by editoria11y_db_version.
	 * '-failed' suffixes are sticky — set BEFORE the destructive step, flipped
	 * to the success value AFTER it completes — so any partial DDL leaves the
	 * marker as a circuit breaker. retry_migration() clears it.
	 *
	 * Concurrency: the actual DDL body is gated by ed11y_migration_lock so
	 * two simultaneous requests in the migration window can't race on
	 * version-marker writes. MySQL serializes DDL on the same table anyway,
	 * but the marker bookkeeping needs single-writer semantics.
	 */
	public static function check_tables(): bool {
		// Settings-side one-shot seed for `panel_no_cover`. Lives above the
		// 2.0 hot-path short-circuit so already-migrated sites still pick
		// up the seed on their next admin request. The method is itself a
		// fast no-op once the key is present in stored settings, so the
		// added cost on the hot path is one autoloaded option read.
		self::backfill_panel_no_cover();

		$version = (string) get_option( 'editoria11y_db_version', '' );

		// Hot path: already at the v3 terminal shape — no lock needed, no
		// work needed. Note that '1.4' is NOT terminal here: it indicates
		// the schema is structurally complete but the v3 result_key
		// translation may not have run, so we push such sites through one
		// more pass below.
		if ( '2.0' === $version ) {
			return true;
		}

		// Sticky -failed states. 1.2/1.3-failed mean the column shape is unknown
		// (refuse writes); 2.0-failed means only the type narrow is pending so
		// the schema is functional and we can return true.
		if ( '-failed' === substr( $version, -7 ) ) {
			return '2.0-failed' === $version;
		}

		// '2.0-migrating' is also functional; the cron / inline UI handles the rest.
		if ( '2.0-migrating' === $version ) {
			return true;
		}

		// Beyond this point we may run DDL or version-bumps. Gate the section
		// so two concurrent admin requests don't both try to migrate.
		if ( ! wp_cache_add( 'ed11y_migration_lock', 1, 'editoria11y', 60 ) ) {
			// Another request holds the lock; treat the schema as functional
			// for this request — the holder is doing the work. If they fail,
			// their -failed marker becomes visible to the next request.
			return '1.3' === $version || '2.0-narrow-pending' === $version || '1.4' === $version;
		}

		try {
			// Re-read inside the lock so we don't act on stale state.
			$version = (string) get_option( 'editoria11y_db_version', '' );

			// Settings-shape coercion. Done at the start of the migration zone
			// (before any DDL) because (a) it's the cheapest, lowest-risk write
			// in the routine — pure wp_options update — and (b) downstream
			// migration code reads these options, so getting them into the
			// canonical shape first removes a class of "stale type" surprises.
			// Idempotent and only writes when something actually changed.
			self::normalize_options();

			// Pre-1.2: lazy-create using the target (v3-equivalent) shape.
			if ( empty( $version ) || version_compare( $version, '1.2', '<' ) ) {
				update_option( 'editoria11y_db_version', '1.2-failed' );
				self::create_database();
				update_option( 'editoria11y_db_version', '1.2' );
				$version = '1.2';
			}

			// 1.2: either fresh install (already v3 shape) or v2 site needing ALTERs.
			if ( '1.2' === $version ) {
				if ( self::tables_already_v3() ) {
					// Fresh install short-circuit. ensure_pepper() must still run
					// here — the migrate_to_1_3() path (which also calls it)
					// is being skipped, but the JS shim still needs the pepper
					// to be present in the autoloaded options blob.
					self::ensure_pepper();
					update_option( 'editoria11y_db_version', '2.0' );
					return true;
				}
				update_option( 'editoria11y_db_version', '1.3-failed' );
				if ( ! self::migrate_to_1_3() ) {
					return false;
				}
				update_option( 'editoria11y_db_version', '1.3' );
				$version = '1.3';
			}

			// 1.3 -> 2.0-migrating: enqueue the cron drain. The rehash worker
			// translates result_keys + re-hashes element_ids against the new
			// keys per row.
			if ( '1.3' === $version ) {
				self::schedule_rehash();
				update_option( 'editoria11y_db_version', '2.0-migrating' );
				$version = '2.0-migrating';
			}

			// 2.0-narrow-pending -> 2.0: pre-flight + ALTER MODIFY.
			if ( '2.0-narrow-pending' === $version ) {
				update_option( 'editoria11y_db_version', '2.0-failed' );
				if ( self::narrow_element_id() ) {
					update_option( 'editoria11y_db_version', '2.0' );
					self::unschedule_rehash();
				}
				// On failure, narrow_element_id() either left '2.0-failed' (real
				// ALTER error — sticky) or rolled back to '2.0-migrating'.
			}

			// Legacy 1.4: pre-v3-translation site that completed the original
			// hash migration but never went through the result_key translation
			// step. Run a one-shot pass on both tables and advance to 2.0.
			// Element_ids are already hashed against the OLD keys here so the
			// linkDocument+pdf special case is unrecoverable for these sites
			// — the cursor walk applies the default `linkDocument` →
			// `QA_DOCUMENT` mapping uniformly.
			if ( '1.4' === $version ) {
				self::translate_results_keys();
				self::translate_dismissal_keys_only();
				update_option( 'editoria11y_db_version', '2.0' );
			}

			return true;
		} finally {
			wp_cache_delete( 'ed11y_migration_lock', 'editoria11y' );
		}
	}

	/**
	 * Returns the current schema-migration state.
	 *
	 * Branches in the read/write paths key off this rather than reading
	 * editoria11y_db_version directly so the version → state mapping stays
	 * in one place.
	 *
	 * Possible values:
	 *  - 'pre-v3'      : v2-shaped schema; check_tables() should migrate.
	 *  - 'dual'        : v3 columns present; legacy raw element_ids may still exist.
	 *  - 'hashed-only' : element_id is char(64); rehash complete.
	 *  - 'broken'      : a DDL step failed; do not write until fixed.
	 */
	public static function schema_state(): string {
		$version = (string) get_option( 'editoria11y_db_version', '' );
		// 1.2-failed and 1.3-failed = column shape is unknown; refuse writes.
		// 2.0-failed = columns exist, only the element_id type-narrow is pending; treat as 'dual'.
		if ( in_array( $version, array( '1.2-failed', '1.3-failed' ), true ) ) {
			return 'broken';
		}
		// '2.0' is the v3 terminal. '1.4' is structurally complete (element_id
		// is char(64)) but pre-v3-key-translation; from a runtime perspective
		// the schema is fully usable, so return 'hashed-only' — the
		// translation pass is the next thing check_tables() will do, but it
		// doesn't block reads/writes.
		if ( in_array( $version, array( '1.4', '2.0' ), true ) ) {
			return 'hashed-only';
		}
		if ( in_array( $version, array( '1.3', '2.0-migrating', '2.0-narrow-pending', '2.0-failed' ), true ) ) {
			return 'dual';
		}
		return 'pre-v3';
	}

	/**
	 * Coerce stored settings-option values to the canonical wire shape.
	 *
	 * Pre-3.0 the defaults array carried real PHP bools for typed fields whose
	 * settings-page form widget actually posts strings (`ed11y_checkvisibility`
	 * select: '' | 'true' | 'false'; the textareas `ed11y_checkRoots` /
	 * `ed11y_no_run` / `ed11y_link_ignore_strings` defaulted to `false`). The
	 * JS shim then coerced `checkVisible` with `=== 'true'`, so a real bool
	 * surviving on the wire became the wrong value. The mismatch was almost
	 * always a runtime artifact of the read-time `ed11y_get_settings()`
	 * empty-overlay rather than something written to `wp_options`, but
	 * round-tripping `ed11y_get_settings()` back to storage (or third-party
	 * code writing the option directly) could leak a real bool into the row.
	 *
	 * This pass rewrites any non-canonical typed value to the form's storage
	 * shape so post-3.0 readers (the static-settings getter, the JS shims, the
	 * sanitize callback) all see a consistent type. Idempotent — writes only
	 * when something actually changes — and called from `check_tables()`
	 * inside the migration lock before any DDL.
	 */
	public static function normalize_options(): void {
		$stored = get_option( 'ed11y_plugin_settings', null );
		if ( ! is_array( $stored ) ) {
			return;
		}

		$changed = false;

		// `ed11y_checkvisibility`: canonical storage is the form `<select>`'s
		// value set: '' | 'true' | 'false'. Any stored bool is almost certainly
		// a leaked theme-detection default rather than an explicit user choice
		// (the form posts strings, not bools), so map both bools to '' — the
		// "Theme default" sentinel — and let the static-settings getter
		// re-resolve via `ed11y_checkvisibility_theme_default()`.
		if ( array_key_exists( 'ed11y_checkvisibility', $stored ) ) {
			$value = $stored['ed11y_checkvisibility'];
			if ( ! is_string( $value ) || ! in_array( $value, array( '', 'true', 'false' ), true ) ) {
				$stored['ed11y_checkvisibility'] = '';
				$changed                         = true;
			}
		}

		// Textarea-backed keys whose pre-3.0 default was bool `false`. JS
		// shims tolerate the bool-to-string mismatch, but storing strings
		// keeps the sanitize callback / static getter / form widget all on
		// the same type contract.
		foreach ( array( 'ed11y_checkRoots', 'ed11y_no_run', 'ed11y_link_ignore_strings' ) as $key ) {
			if ( array_key_exists( $key, $stored ) && ! is_string( $stored[ $key ] ) ) {
				$stored[ $key ] = is_scalar( $stored[ $key ] ) ? (string) $stored[ $key ] : '';
				$changed        = true;
			}
		}

		if ( $changed ) {
			update_option( 'ed11y_plugin_settings', $stored );
		}
	}

	/**
	 * One-shot install seed for the `panel_no_cover` setting.
	 *
	 * Writes the Gutenberg sidebar selector into stored settings the first
	 * time we see a settings array that's missing the key. Once the key is
	 * present (with ANY value, including the empty string), the method is a
	 * no-op forever — so a user who later clears the field in the settings
	 * UI is not re-seeded on the next admin request.
	 *
	 * Why this exists rather than a read-time default in
	 * `ed11y_get_default_options()`: the empty-overlay in
	 * `ed11y_get_settings()` re-applies any non-empty default whenever the
	 * stored value is empty, so a non-empty default makes "delete" in the
	 * UI impossible — the value would snap back on every read. Moving the
	 * selector to a stored seed lets the user override OR delete it.
	 *
	 * Called from the top of `check_tables()` so existing v3 sites
	 * (already at db version 2.0, which short-circuits below) also get the
	 * backfill on their next admin request.
	 */
	public static function backfill_panel_no_cover(): void {
		$stored = get_option( 'ed11y_plugin_settings', null );
		if ( is_array( $stored ) && array_key_exists( 'panel_no_cover', $stored ) ) {
			return;
		}
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$stored['panel_no_cover'] = '.interface-interface-skeleton__sidebar';
		update_option( 'ed11y_plugin_settings', $stored );
	}

	/**
	 * Add v3 columns to existing v1.2 tables. Idempotent per column.
	 *
	 * Returns false if any ALTER fails — caller leaves the '1.3-failed' marker
	 * in place, which short-circuits subsequent check_tables() calls until the
	 * next plugin release or an explicit retry_migration().
	 */
	public static function migrate_to_1_3(): bool {
		global $wpdb;

		// Ensure the pepper exists and is autoloaded. Must happen before any
		// code path could try to hash; safe to re-run (idempotent).
		self::ensure_pepper();

		$urls       = $wpdb->prefix . 'ed11y_urls';
		$results    = $wpdb->prefix . 'ed11y_results';
		$dismissals = $wpdb->prefix . 'ed11y_dismissals';

		$alters = array(
			$urls       => array(
				'dev_total' => 'ADD COLUMN dev_total int unsigned NOT NULL default 0',
			),
			$results    => array(
				'dev_count'   => 'ADD COLUMN dev_count int unsigned NOT NULL default 0',
				'result_name' => "ADD COLUMN result_name varchar(255) NOT NULL default ''",
			),
			$dismissals => array(
				'result_name' => "ADD COLUMN result_name varchar(255) NOT NULL default ''",
				'stale_date'  => 'ADD COLUMN stale_date datetime DEFAULT NULL',
			),
		);

		foreach ( $alters as $table => $columns ) {
			$existing = self::table_columns( $table );
			$clauses  = array();
			foreach ( $columns as $name => $clause ) {
				if ( ! in_array( $name, $existing, true ) ) {
					$clauses[] = $clause;
				}
			}
			if ( empty( $clauses ) ) {
				continue;
			}
			$sql = "ALTER TABLE $table " . implode( ', ', $clauses );
			if ( false === $wpdb->query( $sql ) ) { // phpcs:ignore
				return false;
			}
		}

		// Composite index on dismissals: covers the per-page reset DELETE, the
		// per-page stale-touch UPDATE, and the leftmost-prefix pid join used by
		// the read path. Prefix element_id to 64 to stay under InnoDB's
		// 767/3072-byte key limit on either utf8 or utf8mb4 (still selective
		// since the value is a 64-char hex hash).
		$indexes = self::table_indexes( $dismissals );
		if ( ! in_array( 'pid_result_key_element_id', $indexes, true ) ) {
			$wpdb->query( "ALTER TABLE $dismissals ADD INDEX pid_result_key_element_id (pid, result_key, element_id(64))" ); // phpcs:ignore
		}
		// Drop the now-redundant single-column indexes the composite supersedes.
		// The original schema named the pid index "page_url" by mistake; tolerate
		// either name. element_id was added by an earlier development iteration
		// of this same migration and is now redundant.
		foreach ( array( 'page_url', 'pid', 'element_id' ) as $stale_index ) {
			if ( in_array( $stale_index, $indexes, true ) ) {
				$wpdb->query( "ALTER TABLE $dismissals DROP INDEX $stale_index" ); // phpcs:ignore
			}
		}

		// v3 result_key translation on the results table. Single-pass batch
		// SQL (no cursor needed — ed11y_results carries no element_id, so
		// the rehash worker's per-row dance doesn't apply). Failure here
		// keeps the 1.3-failed circuit breaker the caller wrote.
		if ( ! self::translate_results_keys() ) {
			return false;
		}

		return true;
	}

	/**
	 * Read-only fast path for the per-site pepper.
	 *
	 * Falls back to ensure_pepper() when the option is missing (which both
	 * generates the pepper and writes the autoload-flag fixup). Use this from
	 * hot paths — every editor page load reads the pepper to pass to the JS,
	 * so we want to avoid the autoload-flag fixup write on each call.
	 */
	public static function get_pepper(): string {
		$pepper = (string) get_option( 'editoria11y_id_pepper', '' );
		if ( '' === $pepper ) {
			return self::ensure_pepper();
		}
		return $pepper;
	}

	/**
	 * Generate the per-site pepper if missing and ensure the option is
	 * autoloaded so editor page loads don't pay a per-request DB hit.
	 *
	 * Storing pepper as autoload=yes piggybacks on WP's standard alloptions
	 * cache — no separate caching layer to track, and the pepper is a tiny
	 * 32-byte string so the alloptions blob impact is negligible. Existing
	 * sites that picked it up before this version may have it as autoload=no;
	 * we flip them via a direct $wpdb->update (update_option short-circuits
	 * when the value is unchanged on some WP versions) and bust the
	 * alloptions cache.
	 */
	public static function ensure_pepper(): string {
		global $wpdb;
		$pepper = (string) get_option( 'editoria11y_id_pepper', '' );
		if ( '' === $pepper ) {
			$pepper = bin2hex( random_bytes( 16 ) );
			add_option( 'editoria11y_id_pepper', $pepper, '', 'yes' );
			return $pepper;
		}
		$wpdb->update( // phpcs:ignore
			$wpdb->options,
			array( 'autoload' => 'yes' ),
			array( 'option_name' => 'editoria11y_id_pepper' )
		);
		wp_cache_delete( 'alloptions', 'options' );
		return $pepper;
	}

	/**
	 * Pre-flight gate, then ALTER MODIFY element_id char(64).
	 *
	 * Returns true on success. On pre-flight failure (stragglers exist) rolls
	 * the version back to '2.0-migrating' and returns false; on actual ALTER
	 * failure leaves '2.0-failed' (the caller wrote it before this call) and
	 * returns false.
	 */
	public static function narrow_element_id(): bool {
		global $wpdb;
		$dtable = $wpdb->prefix . 'ed11y_dismissals';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $dtable is $wpdb->prefix.literal; pre-flight COUNT and the ALTER MODIFY are migration-time DDL gated by check_tables() so caching is irrelevant.
		$bad = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM $dtable
			WHERE CHAR_LENGTH(element_id) <> 64
				OR element_id NOT REGEXP '^[0-9a-f]{64}$'"
		);
		if ( $bad > 0 ) {
			update_option( 'editoria11y_db_version', '2.0-migrating' );
			self::schedule_rehash();
			return false;
		}

		// CHARACTER SET ascii matches what fresh installs get from create_database().
		$result = $wpdb->query( "ALTER TABLE $dtable MODIFY element_id char(64) CHARACTER SET ascii NOT NULL DEFAULT ''" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return false !== $result;
	}

	/**
	 * Clear a -failed marker by stepping back to the most recent good state, then
	 * re-run check_tables(). Called by the admin "Retry migration" button.
	 */
	public static function retry_migration(): bool {
		$version = (string) get_option( 'editoria11y_db_version', '' );
		if ( '-failed' === substr( $version, -7 ) ) {
			$rollback = array(
				'1.2-failed' => '',
				'1.3-failed' => '1.2',
				// 2.0-failed: column-narrow already failed once. Roll back
				// to 2.0-narrow-pending so the next check_tables() retries
				// the ALTER MODIFY and, on success, advances to 2.0.
				'2.0-failed' => '2.0-narrow-pending',
			);
			update_option( 'editoria11y_db_version', $rollback[ $version ] ?? '' );
		}
		return self::check_tables();
	}

	// ------------------------------------------------------------------
	// Rehash worker
	// ------------------------------------------------------------------

	/**
	 * Process up to REHASH_BATCH_SIZE rows from a cursor position.
	 *
	 * Returns ['processed' => int, 'remaining' => int]. Drives the cron worker
	 * AND the inline progress UI on the settings page — both call this same
	 * method so they're interchangeable.
	 *
	 * Cursor design: rather than `WHERE CHAR_LENGTH(element_id) <> 64`
	 * (non-sargable, full table scan every batch), we walk the table by id
	 * range and only rehash rows whose element_id isn't already a 64-char hex
	 * hash. The cursor is stored in the editoria11y_rehash_cursor option so
	 * progress survives restarts, and the SELECT becomes an indexed range
	 * scan bounded by LIMIT — O(batch_size) regardless of table size.
	 *
	 * Concurrency: cron and admin-AJAX can both invoke this. We take a short
	 * cache-backed lock to keep two workers from re-processing the same window
	 * (the UPDATEs themselves are idempotent — same input gives same hash —
	 * but the wasted I/O on a busy site is worth avoiding).
	 *
	 * When the cursor reaches the table's MAX(id), this advances the version to
	 * 2.0-narrow-pending and unschedules the cron. The next check_tables()
	 * call runs the narrow.
	 */
	public static function rehash_batch(): array {
		if ( ! wp_cache_add( 'ed11y_rehash_lock', 1, 'editoria11y', 60 ) ) {
			// Another worker holds the lock; report no progress this tick.
			return array(
				'processed' => 0,
				'remaining' => self::rehash_remaining_estimate(),
			);
		}

		try {
			return self::rehash_batch_locked();
		} finally {
			wp_cache_delete( 'ed11y_rehash_lock', 'editoria11y' );
		}
	}

	/**
	 * Inner rehash worker — assumes the caller holds ed11y_rehash_lock.
	 */
	private static function rehash_batch_locked(): array {
		global $wpdb;
		$dtable    = $wpdb->prefix . 'ed11y_dismissals';
		$cursor    = (int) get_option( 'editoria11y_rehash_cursor', 0 );
		$max_id    = (int) $wpdb->get_var( "SELECT IFNULL(MAX(id), 0) FROM $dtable" ); // phpcs:ignore
		$processed = 0;

		if ( $cursor >= $max_id ) {
			// All ids walked. Mark complete and clean up cursor + cron.
			delete_option( 'editoria11y_rehash_cursor' );
			if ( '2.0-migrating' === (string) get_option( 'editoria11y_db_version', '' ) ) {
				update_option( 'editoria11y_db_version', '2.0-narrow-pending' );
			}
			self::unschedule_rehash();
			return array(
				'processed' => 0,
				'remaining' => 0,
			);
		}

		// Indexed range scan — O(batch_size) regardless of table size.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $dtable is $wpdb->prefix.'ed11y_dismissals' (literal, not user input); migration query needs to bypass object cache to drive forward state.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, result_key, element_id FROM $dtable WHERE id > %d ORDER BY id ASC LIMIT %d",
				$cursor,
				(int) self::REHASH_BATCH_SIZE
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$last_id = $cursor;
		foreach ( $rows as $row ) {
			$last_id     = (int) $row->id;
			$current_key = (string) $row->result_key;
			$current_id  = (string) $row->element_id;
			$is_hashed   = ( 64 === strlen( $current_id ) && ctype_xdigit( $current_id ) );

			// v3 key translation. The linkDocument+pdf special case relies on
			// the RAW element_id, so it only fires when $is_hashed is false.
			$new_key = self::translate_result_key( $current_key, $current_id );

			// Drop list: rows whose translated key falls into the
			// not migrate-able set (per Drupal's update_9011 drop list) are
			// removed entirely. The corresponding ed11y_results rows are
			// dropped in translate_results_keys().
			if ( in_array( $new_key, self::DROP_KEYS, true ) ) {
				$wpdb->delete( $dtable, array( 'id' => $last_id ), array( '%d' ) ); // phpcs:ignore
				++$processed;
				continue;
			}

			// Already-hashed row: can't rehash element_id (the raw input is
			// gone), but the result_key still needs the v3 translation if
			// it changed. Update the key column only.
			if ( $is_hashed ) {
				if ( $new_key !== $current_key ) {
					$wpdb->update( // phpcs:ignore
						$dtable,
						array( 'result_key' => $new_key ),
						array( 'id' => $last_id ),
						array( '%s' ),
						array( '%d' )
					);
					++$processed;
				}
				continue;
			}

			// Raw element_id: hash against the NEW key so the digest matches
			// what the v3 JS library will compute at lookup time. Update key
			// and element_id in one row write.
			$hashed = ed11y_hash_element_id( $new_key, $current_id );
			$wpdb->update( // phpcs:ignore
				$dtable,
				array(
					'result_key' => $new_key,
					'element_id' => $hashed,
				),
				array( 'id' => $last_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			++$processed;
		}

		update_option( 'editoria11y_rehash_cursor', $last_id, false );

		if ( $last_id >= $max_id ) {
			delete_option( 'editoria11y_rehash_cursor' );
			if ( '2.0-migrating' === (string) get_option( 'editoria11y_db_version', '' ) ) {
				update_option( 'editoria11y_db_version', '2.0-narrow-pending' );
			}
			self::unschedule_rehash();
			return array(
				'processed' => $processed,
				'remaining' => 0,
			);
		}

		return array(
			'processed' => $processed,
			'remaining' => max( 0, $max_id - $last_id ),
		);
	}

	/**
	 * Best-effort estimate of the number of rows still ahead of the cursor.
	 * Used when another worker holds the rehash lock so the UI still has a
	 * progress number to render.
	 */
	private static function rehash_remaining_estimate(): int {
		global $wpdb;
		$dtable = $wpdb->prefix . 'ed11y_dismissals';
		$cursor = (int) get_option( 'editoria11y_rehash_cursor', 0 );
		$max_id = (int) $wpdb->get_var( "SELECT IFNULL(MAX(id), 0) FROM $dtable" ); // phpcs:ignore
		return max( 0, $max_id - $cursor );
	}

	// ------------------------------------------------------------------
	// Cron lifecycle
	// ------------------------------------------------------------------

	/** Register a recurring rehash cron event if one isn't already queued. */
	public static function schedule_rehash(): void {
		if ( ! wp_next_scheduled( self::REHASH_CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, self::REHASH_CRON_SCHEDULE, self::REHASH_CRON_HOOK );
		}
	}

	/** Drain every queued rehash event. Idempotent. */
	public static function unschedule_rehash(): void {
		while ( $timestamp = wp_next_scheduled( self::REHASH_CRON_HOOK ) ) { // phpcs:ignore
			wp_unschedule_event( $timestamp, self::REHASH_CRON_HOOK );
		}
	}

	/** WP-Cron callback: process one batch. */
	public static function run_rehash_cron(): void {
		self::rehash_batch();
	}

	// ------------------------------------------------------------------
	// v3 key translation
	// ------------------------------------------------------------------

	/**
	 * Translate a legacy v2 `result_key` to its v3 equivalent.
	 *
	 * Two layers:
	 *
	 *   1. Direct map lookup in `UpdateHelpers::old_keys()` (camelCase →
	 *      UPPER_SNAKE).
	 *   2. The `linkDocument` + 'pdf' special case from Drupal's
	 *      `editoria11y_update_9011()` (around install line 408): a row
	 *      whose v2 key is `linkDocument` AND whose RAW element_id contains
	 *      the substring `pdf` becomes `QA_PDF` rather than the default
	 *      `QA_DOCUMENT`. The check is case-insensitive on the element_id
	 *      to match the JS-side selector matching.
	 *
	 * Note that the special case only fires when `$element_id` is the raw
	 * selector. Once a row's element_id has been hashed, the substring
	 * check is meaningless (a 64-char hex hash never contains 'pdf' as a
	 * meaningful token), so already-hashed rows always fall through to
	 * `QA_DOCUMENT`. That tradeoff is documented in the v3 migration plan
	 * — Drupal's update path catches the special case while element_ids
	 * are still raw, and so does our cursor walk, but a WP site that
	 * completed the existing 1.2 → 1.4 rehash before the v3 work landed
	 * has its `linkDocument` rows stranded as `QA_DOCUMENT`.
	 *
	 * @param string $current_key Current v2 result_key (camelCase).
	 * @param string $element_id  Current element_id (raw selector OR hashed).
	 * @return string The v3 result_key. Returns `$current_key` unchanged
	 *                when no translation applies (e.g. an UPPER_SNAKE key
	 *                already in v3 shape, or a custom key not in the
	 *                translation table).
	 */
	public static function translate_result_key( string $current_key, string $element_id ): string {
		if ( 'linkDocument' === $current_key && false !== stripos( $element_id, 'pdf' ) ) {
			return 'QA_PDF';
		}
		$map = UpdateHelpers::old_keys();
		return $map[ $current_key ] ?? $current_key;
	}

	/**
	 * One-shot batch translation of `ed11y_results.result_key` from v2
	 * camelCase to v3 UPPER_SNAKE.
	 *
	 * Unlike the dismissals table, ed11y_results doesn't carry an
	 * element_id — only `result_key` plus per-page counts — so the
	 * translation is a single SQL pass with no cursor walk. The
	 * `linkDocument` → `QA_DOCUMENT` mapping is applied uniformly here
	 * (the pdf special case requires an element_id, which doesn't exist
	 * in this table).
	 *
	 * Called once from `migrate_to_1_3()` as part of the additive
	 * migration. Idempotent: rows already at UPPER_SNAKE keys are
	 * untouched (the lookup returns the key unchanged for non-legacy
	 * values).
	 *
	 * @return bool True on success; false if any UPDATE failed (which
	 *              would be visible in the broader 1.3-failed circuit
	 *              breaker the caller writes).
	 */
	public static function translate_results_keys(): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'ed11y_results';
		$map   = UpdateHelpers::old_keys();

		foreach ( $map as $old_key => $new_key ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is $wpdb->prefix.literal; placeholders carry the user-facing values.
			$result = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET result_key = %s WHERE result_key = %s",
					$new_key,
					$old_key
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
			if ( false === $result ) {
				return false;
			}
		}

		// Drop the rows whose translated key falls into the not migrate-able
		// set; matching dismissals are deleted in the cursor walk.
		foreach ( self::DROP_KEYS as $drop_key ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$wpdb->query(
				$wpdb->prepare( "DELETE FROM {$table} WHERE result_key = %s", $drop_key )
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
		}
		return true;
	}

	/**
	 * Key-only batch translation for legacy `'1.4'` dismissal rows.
	 *
	 * Used by the `1.4 → 2.0` transition path in `check_tables()`. The
	 * element_ids on these rows are already hashed against the old
	 * camelCase keys, so re-hashing isn't possible — the dismissals are
	 * functionally lost relative to incoming v3 JS dispatches, but
	 * preserving the row with the new UPPER_SNAKE result_key keeps the
	 * dashboard's per-test grouping coherent and gives admins a path to
	 * clean them up.
	 *
	 * The `linkDocument` + 'pdf' special case is NOT applied here (would
	 * require a raw element_id, which we no longer have); all
	 * `linkDocument` rows fall through to `QA_DOCUMENT`.
	 */
	public static function translate_dismissal_keys_only(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'ed11y_dismissals';
		$map   = UpdateHelpers::old_keys();

		foreach ( $map as $old_key => $new_key ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET result_key = %s WHERE result_key = %s",
					$new_key,
					$old_key
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
		}

		foreach ( self::DROP_KEYS as $drop_key ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$wpdb->query(
				$wpdb->prepare( "DELETE FROM {$table} WHERE result_key = %s", $drop_key )
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
		}
	}

	// ------------------------------------------------------------------
	// Internal helpers
	// ------------------------------------------------------------------

	/**
	 * List column names for a table. Helper for migrate_to_1_3() idempotency.
	 *
	 * @param string $table Fully prefixed table name.
	 */
	private static function table_columns( string $table ): array {
		global $wpdb;
		$names = array();
		foreach ( $wpdb->get_results( "DESC $table", ARRAY_A ) as $col ) { // phpcs:ignore
			$names[] = $col['Field'];
		}
		return $names;
	}

	/**
	 * List index names for a table. Helper for migrate_to_1_3() idempotency.
	 *
	 * @param string $table Fully prefixed table name.
	 */
	private static function table_indexes( string $table ): array {
		global $wpdb;
		$names = array();
		foreach ( $wpdb->get_results( "SHOW INDEX FROM $table", ARRAY_A ) as $idx ) { // phpcs:ignore
			$names[ $idx['Key_name'] ] = true;
		}
		return array_keys( $names );
	}
}
