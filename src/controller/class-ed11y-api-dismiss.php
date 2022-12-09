<?php
/**
 * Stores tests results
 * Reference https://developer.wordpress.org/rest-api/extending-the-rest-api/controller-classes/
 * POST v PUT in https://developer.wordpress.org/reference/classes/wp_rest_server/
 *
 * @package         Editoria11y
 */
class Ed11y_Api_Dismiss extends WP_REST_Controller {

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
		$base      = 'dismiss';
		// Set up single-page routes
		register_rest_route(
			$namespace,
			'/' . $base,
			array(
				/*array(
					// Report results for a URL.
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( true ),
				),*/
				array(
					// Report results for a URL.
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( true ),
				),
				/*
				array(
					// Purge results for a URL.
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => array(
						'force' => array(
							'default' => false,
						),
					),
				),*/
			)
		);
		/*
		register_rest_route(
			$namespace,
			'/' . $base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'context' => array(
							'default' => 'view',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( false ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => array(
						'force' => array(
							'default' => false,
						),
					),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/' . $base . '/schema',
			array(
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_public_item_schema' ),
			)
		);*/
	}

	/**
	 * Get a collection of items
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	/*
	public function get_items( $request ) {
		$items = array(); // do a query, call another class, etc
		$data  = array();
		foreach ( $items as $item ) {
			$itemdata = $this->prepare_item_for_response( $item, $request );
			$data[]   = $this->prepare_response_for_collection( $itemdata );
		}

		return new WP_REST_Response( $data, 200 );
	}*/

	/**
	 * Get one item from the collection
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	/*public function get_item( $request ) {
		// get parameters from request.
		$data = $this->get_dismissals_for_user( $request );
		if ( is_array( $data ) ) {
			return new WP_REST_Response( $data, 200 );
		}

		return new WP_Error( 'cant-update', __( 'Results not recorded', 'editoria11y' ), array( 'status' => 500 ) );
	}*/

	/**
	 * Pulls any dismissals relevant to a user for a given route.
	 */
	/*public function get_dismissals_for_user( $request ) {
		$params  = $request->get_params();
		$results = $params['data'];
		global $wpdb;
		$user       = wp_get_current_user();
		$dismissals = $wpdb->query(
			$wpdb->prepare(
				"SELECT FROM {$wpdb->prefix}ed11y_dismissals 
				WHERE page_url = %s 
				AND (
					dismissal_status = 'ok'
					OR
					(
						dismissal_status = 'hide'
						AND
						user = %d
					)
				);",
				array(
					$results['page_url'],
					wp_get_current_user(),
				),
			)
		);
		return $dismissals;
	}*/


	/**
	 * Edit one item from the collection
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	/*
	public function create_item( $request ) {
		$item = $this->prepare_results( $request );

		if ( function_exists( 'slug_some_function_to_update_item' ) ) {
			$data = slug_some_function_to_update_item( $item );
			if ( is_array( $data ) ) {
				return new WP_REST_Response( $data, 200 );
			}
		}

		return new WP_Error( 'cant-create', __( 'message', 'text-domain' ), array( 'status' => 500 ) );
	}*/

	/**
	 * Update one item from the collection
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_item( $request ) {

		$data = $this->send_dismissal( $request );
		if ( is_numeric( $data ) ) {
			return new WP_REST_Response( 'Success', 200 );
		}

		return new WP_Error( 'cant-update', __( 'Results not recorded', 'editoria11y' ), array( 'status' => 500 ) );
	}

	/**
	 *
	 * Attempts to send item to DB
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function send_dismissal( $request ) {
		$params  = $request->get_params();
		$results = $params['data'];
		$now     = gmdate( 'Y-m-d H:i:s' );
		global $wpdb;
		$pid = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pid FROM {$wpdb->prefix}ed11y_urls
				WHERE page_url=%s;",
				array(
					$results['page_url'],
				)
				)
		 );

		if ( 'reset' === $results['dismissal_status'] ) {

			// Delete URL if total is 0, record if it never existed.
			$response = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}ed11y_dismissals 
					WHERE pid = %d 
					AND (
						dismissal_status = 'ok'
						OR
						(
							dismissal_status = 'hide'
							AND
							user = %d
						)
					);",
					array(
						$pid,
						wp_get_current_user(),
					)
				)
			);

			return $response;

		} else {

			$response = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}ed11y_dismissals 
						(pid,
						result_key,
						user,
						element_id,
						dismissal_status,
						created,
						updated,
						stale)
					VALUES (%s, %s, %d, %s, %s, %s, %s, %d) 
						;",
					array(
						$pid,
						$results['result_key'],
						wp_get_current_user(),
						$results['element_id'],
						$results['dismissal_status'],
						$now,
						$now,
						0,
					)
				)
			);

			return $response;
		}

	}


	/**
	 * Delete one item from the collection
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	/*public function delete_item( $request ) {
		$item = $this->prepare_results( $request );

		if ( function_exists( 'slug_some_function_to_delete_item' ) ) {
			$deleted = slug_some_function_to_delete_item( $item );
			// like $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->comments} WHERE comment_id IN ( " . $format_string . " )", $comment_ids ) );

			if ( $deleted ) {
				return new WP_REST_Response( true, 200 );
			}
		}

		return new WP_Error( 'cant-delete', __( 'message', 'text-domain' ), array( 'status' => 500 ) );
	}*/

	/**
	 * Check if a given request has access to get items
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	/*
	public function get_items_permissions_check( $request ) {
		// return true; <--use to make readable by all
		return current_user_can( 'edit_something' );
	}*/

	/**
	 * Check if a given request has access to get a specific item
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	/*
	public function get_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}*/

	/**
	 * Check if a given request has access to create items
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	/*
	public function create_item_permissions_check( $request ) {
		return current_user_can( 'edit_something' );
	}*/

	/**
	 * Check if a given request has access to update a specific item
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function update_item_permissions_check( $request ) {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check if a given request has access to delete a specific item
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function delete_item_permissions_check( $request ) {
		return current_user_can( 'edit_others_posts' );
	}

	/**
	 * Prepare the item for create or update operation
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_Error|object $prepared_item
	 */
	/*protected function prepare_results( $request ) {
		$params = $request->get_params();
		$now    = time();
		$item   = (object) array();
		foreach ( $params['results'] as $key => $value ) {
			if ( $results['page_count'] > 0 ) {
				$item[] = array(
					'page_title'        => $results['page_title'],
					'page_path'         => $results['page_path'],
					'page_url'          => $results['page_url'],
					'page_language'     => $results['language'],
					'page_result_count' => $results['page_count'],
					'entity_type'       => $results['entity_type'],
					'route_name'        => $results['route_name'],
					'result_name'       => $key,
					'result_name_count' => $value,
					'updated'           => $now,
					'created'           => $now,
				);
			}
		}
		return $item;
	}*/

	/**
	 * Prepare the item for the REST response
	 *
	 * @param mixed           $item WordPress representation of the item.
	 * @param WP_REST_Request $request Request object.
	 * @return mixed
	 */
	public function prepare_item_for_response( $item, $request ) {
		return array();
	}

	/**
	 * Get the query params for collections
	 *
	 * @return array
	 */
	/*
	public function get_collection_params() {
		return array(
			'page'     => array(
				'description'       => 'Current page of the collection.',
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'description'       => 'Maximum number of items to be returned in result set.',
				'type'              => 'integer',
				'default'           => 10,
				'sanitize_callback' => 'absint',
			),
			'search'   => array(
				'description'       => 'Limit results to those matching a string.',
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}*/
}
