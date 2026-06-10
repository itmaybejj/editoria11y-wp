<?php
/**
 * CSV export for the Editoria11y dashboard.
 *
 * Hooks into `admin_init`, gates on `?ed11y_export_results_csv` + a valid
 * nonce + the same capability the dashboard menu uses, then streams a CSV
 * to the browser. CSV-injection-safe: every cell value runs through
 * `sanitize_value()` before write.
 *
 * @package Editoria11y
 */

namespace Editoria11y\Admin;

use Editoria11y\TestNames;
use WP_User_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the CSV export endpoint.
 */
class CsvExport {

	/** Wire the admin_init listener. */
	public function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_export' ) );
	}

	/**
	 * Sanitize a value for safe CSV output, preventing CSV injection.
	 *
	 * @see https://owasp.org/www-community/attacks/CSV_Injection
	 *
	 * @param mixed $value The value to sanitize.
	 * @return mixed The sanitized value (strings are escaped, other types
	 *               passed through).
	 */
	public static function sanitize_value( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}
		// Replace double quotes and control characters that could split cells.
		$escaped = preg_replace( array( '/"/', '/[\t\n\r]/' ), array( '""', ' ' ), $value );
		if ( preg_match( '/^[=\-@+"]/', $escaped ) ) {
			return "'" . $escaped;
		}
		return $escaped;
	}

	/**
	 * If the current request is a CSV export request, stream the CSV and
	 * exit. Otherwise no-op.
	 *
	 * @SuppressWarnings(PHPMD.MissingImport)
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 */
	public static function maybe_export() {

		$setting    = ed11y_get_raw_setting( 'ed11y_report_restrict' );
		$capability = '1' === $setting ? 'manage_options' : 'edit_others_posts';
		if ( ! ( isset( $_GET['ed11y_export_results_csv'] ) && isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'ed1ref' ) && current_user_can( $capability ) ) ) { // phpcs:ignore
			// Incorrect referrer, nonce or user role.
			return;
		}

		$test_name = TestNames::core_names();

		// Generate filename with site name and current date.
		$site_title = sanitize_file_name( get_bloginfo( 'name' ) );
		$date       = gmdate( 'Y-m-d' );
		$filename   = "{$site_title}_accessibility_issues_{$date}.csv";

		/**
		 * Filter the CSV export filename.
		 *
		 * @param string $filename The default filename.
		 */
		$filename = apply_filters( 'ed11y_csv_export_filename', $filename );

		header( 'Content-type: text/csv' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$file = fopen( 'php://output', 'w' );

		global $wpdb;
		$utable     = $wpdb->prefix . 'ed11y_urls';
		$rtable     = $wpdb->prefix . 'ed11y_results';
		$post_table = $wpdb->prefix . 'posts';

		/*
		Complex counts and joins required a direct DB call.
		Variables are all validated or sanitized.
		*/
		// phpcs:disable
		$data = $wpdb->get_results(
			"SELECT
			{$rtable}.result_key,
			{$rtable}.result_count,
			{$utable}.pid,
			{$utable}.page_url,
			{$utable}.page_title,
			{$utable}.entity_type,
			{$utable}.page_total,
			{$utable}.post_id,
			{$post_table}.post_author,
			{$post_table}.post_status,
			{$rtable}.created as created
			FROM {$rtable}
			LEFT JOIN {$utable} ON {$utable}.pid={$rtable}.pid
			LEFT JOIN {$post_table} ON {$utable}.post_id={$post_table}.ID
			ORDER BY page_url ASC;"
		);

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
		$authors = [];
		foreach ( $users as $value ) {
			$authors[ $value->ID ] = $value->display_name;
		}

		// phpcs:enable

		/**
		 * Filter the CSV export data rows.
		 *
		 * @param array $data The results from the database query.
		 */
		$data = apply_filters( 'ed11y_csv_export_data', $data );

		/**
		 * Action hook to add metadata rows before the header and data rows.
		 * Hooked functions should write their own fputcsv() calls.
		 *
		 * @param resource $file The file handle for php://output.
		 * @param array $data The export data.
		 */
		do_action( 'ed11y_csv_export_before_headers', $file, $data );

		// Default CSV headers.
		$headers = array(
			'Page',
			'URL',
			'Issue',
			'Count',
			'Author',
			'Page Type',
			'Detected on',
			'Status',
			'Edit',
		);

		/**
		 * Filter the CSV column headers.
		 *
		 * @param array $headers The default column headers.
		 */
		$headers = apply_filters( 'ed11y_csv_export_headers', $headers );

		fputcsv( $file, $headers );

		$admin = get_admin_url();

		foreach ( $data as $result ) {

			$row = array(
				$result->page_title,
				$result->page_url,
				$test_name[ $result->result_key ] ?? '',
				$result->result_count,
				$result->author ?? $authors[ $result->post_author ] ?? '',
				$result->entity_type,
				$result->created,
				$result->post_status ?? 'publish',
				// Gate the edit-link on the integer post_id, not on
				// $result->post_status — the latter is a string ('publish' /
				// 'draft' / etc.) and `0 < 'publish'` evaluates falsy in PHP 8,
				// which previously sent every row down the page_url branch.
				// post_id===0 (archive / taxonomy / search routes) has no
				// backing edit page, so the page_url is the right link there.
				0 < (int) $result->post_id ?
					$admin . 'post.php?post=' . (int) $result->post_id . '&action=edit'
					: $result->page_url,
			);

			/**
			 * Filter each CSV row before export.
			 *
			 * @param array $row The current row data.
			 * @param object $result The current result object from the database.
			 */
			$row = apply_filters( 'ed11y_csv_export_row', $row, $result );

			$row = array_map( array( __CLASS__, 'sanitize_value' ), $row );
			fputcsv( $file, $row );
		}

		/**
		 * Action hook to add additional rows after the data.
		 * Hooked functions should write their own fputcsv() calls.
		 *
		 * @param resource $file The file handle for php://output.
		 * @param array $data The export data.
		 */
		do_action( 'ed11y_csv_export_after_data', $file, $data );

		exit(); // @SuppressWarnings(ExitExpression)
	}
}
