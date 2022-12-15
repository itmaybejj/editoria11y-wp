<?php
/**
 * Builds report view pages
 *
 * @package         Editoria11y
 */
class Ed11y_Dashboard {

	/**
	 * Wrapper
	 */
	public static function header( $heading ) {
		return '<h1>' . $heading . '</h1>';
	}

	/**
	 * Results getter
	 */
	// public static function results (int $count = 50, int $offset = 0, string $sort, string $page_url, $result_key ) {
	public static function results_query() {
		global $wpdb;
		$results = $wpdb->get_results(
			"SELECT
                {$wpdb->prefix}ed11y_results.page_url,
                {$wpdb->prefix}ed11y_urls.page_title,
                {$wpdb->prefix}ed11y_urls.entity_type,
                {$wpdb->prefix}ed11y_results.result_key,
                {$wpdb->prefix}ed11y_results.result_count,
                {$wpdb->prefix}ed11y_results.created
                FROM {$wpdb->prefix}ed11y_results
                INNER JOIN {$wpdb->prefix}ed11y_urls ON {$wpdb->prefix}ed11y_results.page_url={$wpdb->prefix}ed11y_urls.page_url;
                ;",
		);
		return $results;
	}

	/**
	 * Results HTML
	 */
	public static function results_view() {
		wp_enqueue_script( 'ed11y-wp-js', trailingslashit( ED11Y_ASSETS ) . 'js/editoria11y-dashboard.js', array( 'wp-api' ), true, Ed11y::ED11Y_VERSION, false );
		$base = get_site_url();
		?>
		<table>
			<tr>
				<th>Page</th>
				<th>Count</th>
				<th>Type</th>
				<th>Url</th>
			</tr>
		<?php
		$results = self::results_query();
		foreach ( $results as $key => $value ) {
			?>
			<tr>
				<td><a href="<?php echo $value->page_url; ?>"><?php echo $value->page_title; ?></a></td>
				<td><?php echo $value->result_count; ?></td>
				<td><?php echo $value->entity_type; ?></td>
				<td><?php echo str_replace( $base, '', $value->page_url ); ?></td>
				<td></td>
			</tr>
			<?php
		}
		echo '</table>';
	}

	/**
	 * Landing page
	 */
	public static function dashboard() {
		ob_start();
		echo self::header( 'hola' );
		echo self::results_view();

		// Cleaned Query var:
		// $my_c = filter_input( INPUT_GET, "c", FILTER_SANITIZE_STRING );
		// Unclean:
		// $my_c = isset( $_GET['c'] ) ? $_GET['c'] : "";

		return ob_get_clean();
	}

}
