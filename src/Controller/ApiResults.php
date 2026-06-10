<?php
/**
 * Stores tests results
 * Reference https://developer.wordpress.org/rest-api/extending-the-rest-api/controller-classes/
 * POST v PUT in https://developer.wordpress.org/reference/classes/wp_rest_server/
 *
 * @package         Editoria11y
 */

namespace Editoria11y\Controller;

use Editoria11y\Installer;
use Editoria11y\Validate;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_User_Query;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for scan results and the dashboard read endpoints (PUT /result, GET /dashboard).
 */
class ApiResults extends WP_REST_Controller {

	/**
	 * Register routes
	 */
	public function init() {
		add_action(
			'rest_api_init',
			array( $this, 'register_routes' ),
		);
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {

		$version   = '1';
		$namespace = 'ed11y/v' . $version;

		// Report results from scan.
		register_rest_route(
			$namespace,
			'/result',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( true ),
			)
		);

		// Return sitewide data.
		register_rest_route(
			$namespace,
			'/dashboard',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_results' ),
					'permission_callback' => array( $this, 'read_dashboard_permissions_check' ),
					'args'                => array(
						'context' => array(
							'default' => 'view',
						),
					),
				),

			)
		);
	}

	/**
	 * Associate old records with post ID. Todo: remove.
	 */
	private function add_post_id() {
		set_site_transient( 'ed11y_got_ids', true );

		global $wpdb;
		$utable = $wpdb->prefix . 'ed11y_urls';

		// phpcs:disable
		$missing_id = $wpdb->get_results(
			"SELECT
						{$utable}.page_url
						FROM {$utable}
						WHERE (
						    $utable.post_id = 0
						    AND
						    (
						        $utable.entity_type = 'Page'
						        OR
						        $utable.entity_type = 'Post'
						    )
						)
						;"
		);
		// phpcs:enable
		foreach ( $missing_id as $value ) {
			$post_id = url_to_postid( $value->page_url );
			if ( ! empty( $post_id ) ) {
				$wpdb->update( // phpcs:ignore
					$utable,
					array(
						'post_id' => $post_id,
					),
					array(
						'page_url' => $value->page_url,
					),
					array(
						'%d',
						'%s',
					)
				);
			}
		}
	}

	/**
	 * Get dashboard table data.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response
	 */
	public function get_results( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$validate = new Validate();
		$users    = array();

		$utable     = $wpdb->prefix . 'ed11y_urls';
		$rtable     = $wpdb->prefix . 'ed11y_results';
		$post_table = $wpdb->prefix . 'posts';

		$got_ids = get_site_transient( 'ed11y_got_ids' );
		if ( empty( $got_ids ) ) {
			$this->add_post_id();
		}

		// Sanitize all params before use.
		$params = $request->get_params();
		// Defensive defaults: PHP 8 raises "Undefined array key" warnings for
		// any param the caller omits. The dashboard JS sends every key on
		// every request, but third-party REST consumers (and PHPUnit tests
		// targeting the permission callback) reasonably omit them. `+=`
		// preserves keys the caller did set and only fills in the rest.
		$params     += array(
			'view'        => 'pages',
			'count'       => 25,
			'offset'      => 0,
			'direction'   => 'DESC',
			'sort'        => '',
			'result_key'  => '',
			'entity_type' => '',
			'post_status' => '',
			'p_author'    => '',
			'dismissor'   => '',
		);
		$count       = intval( $params['count'] );
		$offset      = intval( $params['offset'] );
		$direction   = 'ASC' === $params['direction'] ? 'ASC' : 'DESC';
		$order_by    = ! empty( $params['sort'] ) && $validate->sort( $params['sort'] ) ? $params['sort'] : false;
		$entity_type = ! empty( $params['entity_type'] ) && $validate->entity_type( $params['entity_type'] ) ? $params['entity_type'] : false;
		$result_key  = ! empty( $params['result_key'] ) && 'false' !== $params['result_key'] ? esc_sql( $params['result_key'] ) : false;
		$author      = is_numeric( $params['p_author'] ) ? intval( $params['p_author'] ) : false;
		$post_status = ! empty( $params['post_status'] ) && 'false' !== $params['post_status'] ? esc_sql( $params['post_status'] ) : false;

		$post_status_filter = function ( $where, $post_status, $utable, $post_table ) {
			if ( ! empty( $post_status ) ) {
				// Filtering by published status.
				$where = empty( $where ) ? 'WHERE ' : $where . 'AND ';
				$where = 'publish' === $post_status ?
					$where . "( {$utable}.post_id = '0' OR ( {$utable}.post_id > '0' AND {$post_table}.post_status = '{$post_status}' ) )"
					: $where . "{$utable}.post_id > '0' AND {$post_table}.post_status = '{$post_status}'";
			}
			return $where;
		};

		if ( 'pages' === $params['view'] ) {
			/**
			 * Dashboard panel: list of pages with issues.
			 */

			// Sort by sanitized param; page total is default.
			$order_by = $order_by ? $order_by : 'page_total';

			// Build where clause based on sanitized params.
			// Alert + dev columns alias to per-key counts when a result_key
			// filter is active so the same `page_total` / `dev_total` sort
			// keys keep working in both filtered and unfiltered views.
			if ( $result_key ) {
				// Filtering by test name.
				$total_column = "{$rtable}.result_count";
				$dev_column   = "{$rtable}.dev_count";
				$where        = "WHERE {$rtable}.result_key = '{$result_key}' AND {$total_column} > '0'";
			} else {
				$total_column = "{$utable}.page_total";
				$dev_column   = "{$utable}.dev_total";
				$where        = "WHERE {$total_column} > '0'";
			}
			if ( $entity_type ) {
				// Filtering by entity type.
				$where = empty( $where ) ? 'WHERE ' : $where . 'AND ';
				$where = $where . "{$utable}.entity_type = '{$entity_type}'";
			}
			$where = $post_status_filter( $where, $post_status, $utable, $post_table );

			if ( 0 < $author ) {
				// Filtering by author ID number, which has been cast to integer.
				$where = empty( $where ) ? 'WHERE ' : $where . 'AND ';
				$where = $where . "{$post_table}.post_author = '{$author}'";
			}

			/*
			Complex counts and joins required a direct DB call.
			Variables are all validated or sanitized.
			*/
			// phpcs:disable
			$data = $wpdb->get_results(
				"SELECT DISTINCT
						{$utable}.pid,
						{$utable}.page_url,
						{$utable}.page_title,
						{$utable}.entity_type,
						$total_column AS page_total,
						$dev_column AS dev_total,
						{$post_table}.post_status AS post_status,
						{$post_table}.post_modified AS post_modified,
						{$post_table}.post_author AS post_author
						FROM {$utable}
						LEFT JOIN {$rtable} ON {$utable}.pid={$rtable}.pid
						LEFT JOIN {$post_table} ON {$utable}.post_id={$post_table}.ID
						{$where}
						ORDER BY {$order_by} {$direction}
						LIMIT {$count}
						OFFSET {$offset}
						;"
			);

			if ( empty($where) ) {
				$rowcount = $wpdb->get_var(
					"SELECT
    			COUNT({$utable}.pid)
				FROM {$utable}"
				);
			} else {
				$rowcount = $wpdb->get_var(
					"SELECT COUNT(DISTINCT {$utable}.pid) AS row_count
							  FROM {$utable}
							  LEFT JOIN {$rtable}      ON {$utable}.pid = {$rtable}.pid
							  LEFT JOIN {$post_table}  ON {$utable}.post_id = {$post_table}.ID
							  {$where};"
				);
			}

			// Get user display names.
			$user_ids = [];
			foreach ( $data as $value ) {
				if ( $value->post_author && !in_array($value->post_author, $user_ids ) )
					$user_ids[] = $value->post_author;
			}
			$user_query = new WP_User_Query(
				array(
					'include' => $user_ids,
					'fields'  => array(
						'ID',
						'display_name',
					),
				)
			);
			$users = $user_query->get_results();

			// phpcs:enable

		} elseif ( 'keys' === $params['view'] ) {
			/**
			 * Dashboard panel: list of issues.
			 */
			if ( false === $order_by || 'count' === $order_by ) {
				$order_by = 'SUM(' . $wpdb->prefix . 'ed11y_results.result_count)';
			} elseif ( 'dev_count' === $order_by ) {
				// Sort by aggregated dev count, mirroring the count branch.
				$order_by = 'SUM(' . $wpdb->prefix . 'ed11y_results.dev_count)';
			}

			/*
			Complex counts and joins required a direct DB call.
			Variables are all validated or sanitized.
			*/
			// phpcs:disable
			$rowcount = $wpdb->get_var(
				"SELECT COUNT( DISTINCT result_key ) AS row_count
				FROM {$rtable};"
			);

			$data = $wpdb->get_results(
				"SELECT
					SUM({$rtable}.result_count) AS count,
					SUM({$rtable}.dev_count) AS dev_count,
					{$rtable}.result_key,
					MAX({$rtable}.result_name) AS result_name
					FROM {$rtable}
					INNER JOIN {$utable} ON {$rtable}.pid={$utable}.pid
					LEFT JOIN {$post_table} ON {$utable}.post_id={$post_table}.ID
					GROUP BY {$rtable}.result_key
					ORDER BY {$order_by} {$direction}
					LIMIT {$count}
					OFFSET {$offset}
					;"
			);
			// phpcs:enable

		} elseif ( 'recent' === $params['view'] ) {
			/**
			* Dashboard panel: recent issues.
			*/

			// Sort by sanitized param; page total is default.
			$order_by = $order_by ? $order_by : 'page_total';

			// Build where clause based on sanitized params.
			$where = '';
			if ( $result_key ) {
				// Filtering by test name.
				$where = "WHERE {$rtable}.result_key = '{$result_key}'";
			}
			if ( $entity_type ) {
				// Filtering by entity type.
				$where = empty( $where ) ? 'WHERE ' : $where . 'AND ';
				$where = $where . "{$utable}.entity_type = '{$entity_type}'";
			}

			$where = $post_status_filter( $where, $post_status, $utable, $post_table );

			if ( ! empty( $where ) ) {
				/*
				Complex counts and joins required a direct DB call.
				Variables are all validated or sanitized.
				Subquery needed because I couldn't get DISTINCT working.
				*/
				// phpcs:disable
				$data = $wpdb->get_results(
					"SELECT
							{$rtable}.result_key,
					    	{$rtable}.result_count,
							{$rtable}.dev_count,
							{$rtable}.result_name,
							{$utable}.pid,
							{$utable}.page_url,
							{$utable}.page_title,
							{$utable}.entity_type,
							{$utable}.page_total,
							{$utable}.dev_total,
							{$post_table}.post_status,
							{$rtable}.created as created
							FROM {$utable}
							INNER JOIN {$rtable} ON {$utable}.pid={$rtable}.pid
							LEFT JOIN {$post_table} ON {$utable}.post_id={$post_table}.ID
							{$where}
							ORDER BY {$order_by} {$direction}
							LIMIT {$count}
							OFFSET {$offset}
							;"
				);

				$rowcount = $wpdb->get_var(
					"SELECT COUNT({$utable}.pid)
					FROM {$rtable}
					INNER JOIN {$utable} ON {$rtable}.pid={$utable}.pid
					LEFT JOIN {$post_table} ON {$utable}.post_id={$post_table}.ID
					{$where};"
				);
				// phpcs:enable

			} else {
				/*
				Complex counts and joins required a direct DB call.
				Variables are all validated or sanitized.
				*/
				// phpcs:disable
				$data = $wpdb->get_results(
					"SELECT
					    {$rtable}.result_key,
					    {$rtable}.result_count,
					    {$rtable}.dev_count,
					    {$rtable}.result_name,
						{$utable}.pid,
						{$utable}.page_url,
						{$utable}.page_title,
						{$utable}.entity_type,
						{$utable}.page_total,
						{$utable}.dev_total,
						{$post_table}.post_status,
						{$rtable}.created as created
					FROM {$rtable}
					INNER JOIN {$utable} ON {$rtable}.pid={$utable}.pid
					LEFT JOIN {$post_table} ON {$utable}.post_id={$post_table}.ID
					ORDER BY {$order_by} {$direction}
					LIMIT {$count}
					OFFSET {$offset}
					;"
				);

				$rowcount = $wpdb->get_var(
					"SELECT COUNT(pid)
					FROM {$utable};"
				);
				// phpcs:enable
			}
		}

		return new WP_REST_Response( array( $data, $rowcount, $users ), 200 );
	}


	/**
	 * Update one item from the collection
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response
	 */
	public function update_item( $request ): WP_REST_Response {

		$data = $this->send_results( $request );
		if ( ! ( in_array( false, $data, true ) ) ) {
			return new WP_REST_Response( $data, 200 );
		}
		return new WP_REST_Response( $data, 500 );
	}

	/**
	 * Returns the pid from the URL table.
	 *
	 * @param string $url of post.
	 * @param string $post_id WP post ID.
	 */
	public function get_pid( string $url, string $post_id ): ?string {
		global $wpdb;
		if ( $post_id > 0 ) {
			$pid = $wpdb->get_var( // phpcs:ignore
				$wpdb->prepare(
					"SELECT pid FROM {$wpdb->prefix}ed11y_urls
				WHERE post_id=%s;",
					array(
						$post_id,
					)
				)
			);
		}
		// Not found by post ID, or post ID not provided.
		if ( empty( $pid ) ) {
			global $wpdb;
			return $wpdb->get_var( // phpcs:ignore
				$wpdb->prepare(
					"SELECT pid FROM {$wpdb->prefix}ed11y_urls
				WHERE page_url=%s;",
					array(
						$url,
					)
				)
			);
		}
		return $pid;
	}

	/**
	 *
	 * Attempts to send item to DB
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function send_results( WP_REST_Request $request ): array {

		$params  = $request->get_params();
		$results = $params['data'];
		$now     = gmdate( 'Y-m-d H:i:s' );
		$rows    = $results['page_count'] > 0 || count( $results['dismissals'] ) > 0 ? 1 : 0; // If 0 at end, delete URL.
		$return  = array();
		global $wpdb;

		// Handle clicks from dashboard to changed URLS first to prevent URL collisions.
		if ( $results['pid'] > -1 ) {
			$wpdb->query( // phpcs:ignore
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}ed11y_urls
					WHERE
						(pid = %d AND page_url != %s)
					;",
					array(
						$results['pid'],
						$results['page_url'],
					)
				)
			);
		}

		$pid = $this->get_pid( $results['page_url'], $results['post_id'] ); // may be 0.

		// Check if any results exist.
		if ( 0 < $rows ) {

			$dev_total = isset( $results['dev_count'] ) ? (int) $results['dev_count'] : 0;
			if ( empty( $pid ) ) {
				// Insert results.
				$return[] = $wpdb->query( // phpcs:ignore
					$wpdb->prepare(
						"INSERT INTO {$wpdb->prefix}ed11y_urls
						(page_url,
						 post_id,
						entity_type,
						page_title,
						page_total,
						dev_total)
					VALUES (%s, %d, %s, %s, %d, %d);",
						array(
							$results['page_url'],
							$results['post_id'],
							$results['entity_type'],
							$results['page_title'],
							$results['page_count'],
							$dev_total,
						)
					)
				);
				// Get new pid.
				$pid = $this->get_pid( $results['page_url'], $results['post_id'] );
			} else {
				// Update result for existing PID.
				$return[] = $wpdb->update( // phpcs:ignore
					$wpdb->prefix . 'ed11y_urls',
					array(
						'page_url'    => $results['page_url'],
						'post_id'     => $results['post_id'],
						'entity_type' => $results['entity_type'],
						'page_title'  => $results['page_title'],
						'page_total'  => $results['page_count'],
						'dev_total'   => $dev_total,
					),
					array(
						'pid' => $pid,
					),
					array(
						'%s',
						'%d',
						'%s',
						'%s',
						'%d',
						'%d',
					),
					'%d'
				);
			}

			foreach ( $results['results'] as $key => $value ) {
				// Accept the legacy "value is an int" shape and the v3
				// "value is { content_count, dev_count, result_name }" shape.
				if ( is_array( $value ) ) {
					$content_count = (int) ( $value['content_count'] ?? 0 );
					$dev_count     = (int) ( $value['dev_count'] ?? 0 );
					$result_name   = (string) ( $value['result_name'] ?? '' );
				} else {
					$content_count = (int) $value;
					$dev_count     = 0;
					$result_name   = '';
				}

				$response = $wpdb->query( // phpcs:ignore
					$wpdb->prepare(
						"INSERT INTO {$wpdb->prefix}ed11y_results
                            (pid,
                            result_key,
                            result_count,
                            dev_count,
                            result_name,
                            created,
                            updated)
                        VALUES (%d, %s, %d, %d, %s, %s, %s)
                        ON DUPLICATE KEY UPDATE
                            result_count = %d,
                            dev_count = %d,
                            result_name = %s,
                            updated = %s
                            ;",
						array(
							$pid,
							$key,
							$content_count,
							$dev_count,
							$result_name,
							$now,
							$now,
							$content_count,
							$dev_count,
							$result_name,
							$now,
						)
					)
				);
				$rows    += $response ? $response : 0;
				$return[] = $response;
			}

			foreach ( $results['dismissals'] as $value ) {
				// $value is [ result_key, element_id ] from the JS payload.
				// element_id is already a hashed digest produced by the library
				// via createDismissalKey() — store/match as-is.
				$response = $wpdb->query( // phpcs:ignore
					$wpdb->prepare(
						"UPDATE {$wpdb->prefix}ed11y_dismissals
                        SET updated = %s, stale = 0, stale_date = NULL
                        WHERE pid = %d AND result_key = %s AND element_id = %s;",
						array(
							$now,
							$pid,
							$value[0],
							$value[1],
						)
					)
				);
				$rows    += $response ? $response : 0;
				$return[] = $response;
			}
		}

		if ( 0 < $pid ) {
			// Remove any old results.
			$response = $wpdb->query( // phpcs:ignore
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}ed11y_results
					WHERE pid = %d AND updated != %s ;",
					array(
						$pid,
						$now,
					)
				)
			);
			// Do not increment row count on deletions.
			$return[] = $response;

			// Mark any out-of-date dismissals as stale and stamp stale_date the
			// first time we notice. IFNULL keeps the original detection time
			// if a row was already marked stale on a previous scan.
			$response = $wpdb->query( // phpcs:ignore
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}ed11y_dismissals
					SET stale = 1, stale_date = IFNULL(stale_date, %s)
					WHERE pid = %d AND updated != %s ;",
					array(
						$now,
						$pid,
						$now,
					)
				)
			);
			$rows    += $response ? $response : 0;
			$return[] = $response;

			if ( 0 === $rows ) {
				// No records for this route.
				$response = $wpdb->query( // phpcs:ignore
					$wpdb->prepare(
						"DELETE FROM {$wpdb->prefix}ed11y_urls WHERE pid = %d;",
						array(
							$pid,
						)
					)
				);
			}
		}
		return $return;
	}

	/**
	 * Check if a given request has access to update a specific item
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return bool|WP_Error
	 */
	public function update_item_permissions_check( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
		if ( 'broken' === Installer::schema_state() ) {
			return new WP_Error(
				'ed11y_schema_unavailable',
				__( 'Editoria11y database upgrade did not complete; writes are paused until an administrator retries the upgrade.', 'editoria11y' ),
				array( 'status' => 503 )
			);
		}
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check if a given request has access to read sitewide dashboard data.
	 *
	 * The PUT /result writer keeps `edit_posts` so any logged-in editor —
	 * including authors editing their own drafts — can post scan results.
	 * The GET /dashboard reader is stricter: it surfaces every tracked
	 * page across the site plus author display names, which an Author role
	 * has no business seeing. The cap mirrors Admin\Dashboard::register_menu()
	 * so the REST gate matches the menu visibility (manage_options when
	 * `ed11y_report_restrict` is on, else edit_others_posts).
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return bool|WP_Error
	 */
	public function read_dashboard_permissions_check( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
		if ( 'broken' === Installer::schema_state() ) {
			return new WP_Error(
				'ed11y_schema_unavailable',
				__( 'Editoria11y database upgrade did not complete; the dashboard is paused until an administrator retries the upgrade.', 'editoria11y' ),
				array( 'status' => 503 )
			);
		}
		$capability = '1' === ed11y_get_raw_setting( 'ed11y_report_restrict' )
			? 'manage_options'
			: 'edit_others_posts';
		return current_user_can( $capability );
	}
}
