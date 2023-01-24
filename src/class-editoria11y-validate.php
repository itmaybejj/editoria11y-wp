<?php  // phpcs:ignore
/**
 * Validates
 *
 * @package         Editoria11y
 */
class Editoria11y_Validate {

	/**
	 * Validate entity types.
	 *
	 * @param string $string Content type name.
	 */
	public static function entity_type( $string ) {
		$valid = array(
			'Front',
			'Page',
			'Home',
			'Attachment',
			'Post',
			'Category',
			'Tag',
			'Taxonomy',
			'Author',
			'Archive',
			'Search',
			'404',
		);
		return in_array( $string, $valid, true );
	}

	/**
	 * Validate filters and sorts.
	 *
	 * @param string $string Allowed field names.
	 */
	public static function sort( $string ) {
		$valid = array(
			'pid',
			'page_url',
			'page_title',
			'entity_type',
			'page_total',
			'result_key',
			'result_count',
			'created',
			'display_name',
			'dismissal_status',
		);
		return in_array( $string, $valid, true );
	}

	/**
	 * Validate results.
	 *
	 * @param string $string Valid test names.
	 */
	public static function test_name( $string ) {
		$valid = array(
			'headingLevelSkipped',
			'headingEmpty',
			'headingIsLong',
			'blockQuoteIsShort',
			'altMissing',
			'altNull',
			'altURL',
			'alURLLinked',
			'altImageOf',
			'altImageOfLinked',
			'altDeadspace',
			'altDeadspaceLinked',
			'altEmptyLinked',
			'altLong',
			'altLongLinked',
			'altPartOfLinkWithText',
			'linkNoText',
			'linkTextIsUrl',
			'linkTextIsGeneric',
			'linkDocument',
			'linkNewWindow',
			'tableNoHeaderCells',
			'tableContainsContentHeading',
			'tableEmptyHeaderCell',
			'textPossibleList',
			'textPossibleHeading',
			'textUppercase',
			'embedVideo',
			'embedAudio',
			'embedVisualization',
			'embedTwitter',
			'embedCustom',
		);
		return in_array( $string, $valid, true );
	}

}
