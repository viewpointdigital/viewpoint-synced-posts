<?php
/**
 * Map dest fields to src fields.
 *
 * @package Viewpoint\ViewpointSynedPosts
 */

namespace Viewpoint\ViewpointSyncedPosts;

use Viewpoint\Utilities\Singleton;
use Viewpoint\ViewpointApi\Formatter;

/**
 * Map dest fields to src fields.
 */
class Map extends Singleton {
	/**
	 * Initialize map.
	 *
	 * @param integer|string $src_id        Src ID field name.
	 * @param string         $post_type     Post type.
	 * @param string         $post_title    Src title field name.
	 * @param string         $post_content  Src content field name.
	 * @param string|false   $post_date     Src published date field name.
	 * @param string|false   $post_modified Src modified date field name.
	 * @param array          $post_meta     Src post meta, keyed with dest field name and value of src field name.
	 * @param array          $taxonomies    Src taxonomies, keyed with dest taxonomy name and value of src field name.
	 */
	public function __construct(
		public int|string $src_id,
		public string $post_type,
		public string $post_title,
		public string $post_content = '',
		public string|false $post_date = false,
		public string|false $post_modified = false,
		public array $post_meta = [],
		public array $taxonomies = [],
	) {}

	/**
	 * Get dest field value from src post data.
	 *
	 * @param string $field     Dest field name.
	 * @param object|array $post_data Post data.
	 * @return mixed
	 */
	public function get( string $field, object|array $post_data ) {
		$field_key = $this->get_field_key( $field );

		return Formatter::getNestedKeys( $field_key, $post_data );
	}

	/**
	 * Get src field key.
	 *
	 * @param string $field Field key.
	 * @return string
	 */
	public function get_field_key( string $field ) {
		if ( property_exists( $this, $field ) ) {
			return $this->{$field};
		}

		if ( array_key_exists( $field, $this->taxonomies ) ) {
			return $this->taxonomies[ $field ];
		}

		if ( array_key_exists( $field, $this->post_meta ) ) {
			return $this->post_meta[ $field ];
		}
	}

	/**
	 * Format data to match wp_insert_post().
	 *
	 * @param object|array $post_data Raw post data.
	 * @return array
	 */
	public function getInsertPostData( $post_data ) {
		$args = [
			'post_type'    => $this->post_type,
			'post_title'   => $this->get( 'post_title', $post_data ),
			'post_content' => $this->get( 'post_content', $post_data ),
			'post_status'  => 'publish',
			'post_date'    => $this->get( 'post_date', $post_data ),
		];

		$args['meta_input'] = [
			'src_id' => $this->get( 'src_id', $post_data ),
		];
		foreach ( $this->post_meta as $dest_key => $src_key ) {
			$args['meta_input'][ $dest_key ] = $this->get( $dest_key, $post_data );
		}

		$args['tax_input'] = [];
		foreach ( $this->taxonomies as $dest_key => $src_key ) {
			$tax_value                      = $this->get( $dest_key, $post_data );
			$args['tax_input'][ $dest_key ] = $tax_value;
		}
		return $args;
	}
}
