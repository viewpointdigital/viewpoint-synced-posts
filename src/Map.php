<?php
/**
 * Map dest fields to src fields.
 *
 * @package Viewpoint\ViewpointSynedPosts
 */

namespace Viewpoint\ViewpointSyncedPosts;

use Viewpoint\Utilities\Singleton;

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
	 * @param string|false   $post_modified Src modified date field name.
	 * @param array          $post_meta     Src post meta, keyed with dest field name and value of src field name.
	 * @param array          $taxonomies    Src taxonomies, keyed with dest taxonomy name and value of src field name.
	 */
	public function __construct(
		public int|string $src_id,
		public string $post_type,
		public string $post_title,
		public string $post_content = '',
		public string|false $post_modified = false,
		public array $post_meta = [],
		public array $taxonomies = [],
	) {}

	/**
	 * Get dest field value from src post data.
	 *
	 * @param string $field     Dest field name.
	 * @param object $post_data Post data.
	 * @return mixed
	 */
	public function get( string $field, object $post_data ) {
		$field_key = $this->get_field_key( $field );

		// If a nested value defined by .
		if ( str_contains( $field_key, '.' ) ) {
			$field_keys = explode( '.', $field_key );
			return $post_data->{$field_keys[0]}->{$field_keys[1]};
		}
		return $post_data->{$field_key};
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
}
