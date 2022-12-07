<?php
/**
 * Stores tests results
 * Reference https://developer.wordpress.org/rest-api/extending-the-rest-api/controller-classes/
 * POST v PUT in https://developer.wordpress.org/reference/classes/wp_rest_server/
 *
 * @package         Editoria11y
 */
class Ed11y_Api_Result extends WP_REST_Controller {

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
		$base      = 'result';
		register_rest_route(
			$namespace,
			'/' . $base,
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( true ),
				/*
				array(
					// Report results for a URL.
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( true ),
				),
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
	/*
	public function get_item( $request ) {
		// get parameters from request
		$params = $request->get_params();
		$item   = array();// do a query, call another class, etc
		$data   = $this->prepare_item_for_response( $item, $request );

		// return a response or error based on some conditional
		if ( 1 == 1 ) {
			return new WP_REST_Response( $data, 200 );
		} else {
			return new WP_Error( 'code', __( 'message', 'text-domain' ) );
		}
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
		// $item = $this->prepare_results( $request );

		$data = $this->send_results( $request );
		if ( ! ( in_array( false, $data, true ) ) ) {
			return new WP_REST_Response( $data, 200 );
		}

		return new WP_Error( 'cant-update', __( 'Results not recorded', 'editoria11y' ), array( 'status' => 500 ) );
	}

	/**
	 *
	 * Attempts to send item to DB
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function send_results( $request ) {
		// not yet valid code
		// see https://developer.wordpress.org/reference/classes/wpdb/ for escaping. %s string %d digits
		$params  = $request->get_params();
		$results = $params['data'];
		$now     = gmdate( 'Y-m-d H:i:s' );
		$rows   = 0;
		$return = array();
		global $wpdb;

		if ( $results['page_count'] > 0 ) {
			// Upsert any result rows, included a new Now in updated.
			foreach ( $results['results'] as $key => $value ) {

				// Update URLs table with totals and date.
				$response = $wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$wpdb->prefix}ed11y_urls
                            (page_url,
                            entity_type,
                            page_title,
                            page_total)
                        VALUES (%s, %s, %s, %d)
                        ON DUPLICATE KEY UPDATE
                            entity_type = %s,
                            page_title = %s,
                            page_total = %d
                        ;",
						array(
							$results['page_url'],
							$results['entity_type'],
							$results['page_title'],
							$results['page_count'],
							$results['entity_type'],
							$results['page_title'],
							$results['page_count'],
						)
					)
				);

				$rows    += $response ? $response : 0;
				$return[] = $response;

				// Update results table.
				$response = $wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$wpdb->prefix}ed11y_results 
                            (page_url,
                            result_key,
                            result_count,
                            created,
                            updated)
                        VALUES (%s, %s, %d, %s, %s) 
                        ON DUPLICATE KEY UPDATE
                            result_count = %d,
                            updated = %s
                            ;",
						array(
							$results['page_url'],
							$key,
							$value,
                            $now,
                            $now,
                            $value,
                            $now,
						)
					)
				);

				$rows    += $response ? $response : 0;
				$return[] = $response;

				// Update dismissal table.
				$response = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->prefix}ed11y_dismissals 
                        SET updated = %s, stale = 0
                        WHERE page_url = %s AND result_key = %s;",
						array(
							$now,
							$results['page_url'],
							$key,
						)
					)
				);

				$rows    += $response ? $response : 0;
				$return[] = $response;
			}
		} else {
			// Delete URL if total is 0, record if it never existed.
			$response = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}ed11y_urls WHERE page_url = %s AND updated != %s;",
					array(
						$results['page_url'],
						$now,
					)
				)
			);

			$rows    += $response ? $response : 0;
			$return[] = $response;
		}

		// Clear old values if there is any chance they exist.
		if ( 0 !== $rows ) {
			// Remove any old results.
			$response = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}ed11y_results WHERE page_url = %s AND updated != %s;",
					array(
						$results['page_url'],
						$now,
					)
				)
			);
			$rows    += $response ? $response : 0;
			$return[] = $response;

			// Mark any out-of-date dismissals as stale.
			$response = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}ed11y_dismissals 
                    SET stale = 1
                    WHERE page_url = %s AND updated != %s;",
					array(
						$results['page_url'],
						$now,
					)
				)
			);
			$rows    += $response ? $response : 0;
			$return[] = $response;
		}

		return $return;
	}


	/**
	 * Delete one item from the collection
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function delete_item( $request ) {
		$item = $this->prepare_results( $request );

		if ( function_exists( 'slug_some_function_to_delete_item' ) ) {
			$deleted = slug_some_function_to_delete_item( $item );
			// like $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->comments} WHERE comment_id IN ( " . $format_string . " )", $comment_ids ) );

			if ( $deleted ) {
				return new WP_REST_Response( true, 200 );
			}
		}

		return new WP_Error( 'cant-delete', __( 'message', 'text-domain' ), array( 'status' => 500 ) );
	}

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
	protected function prepare_results( $request ) {
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
	}

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
