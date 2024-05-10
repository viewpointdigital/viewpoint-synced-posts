<?php
/**
 * Map dest fields to src fields.
 *
 * @package Viewpoint\ViewpointSynedPosts
 */

namespace Viewpoint\ViewpointSyncedPosts;

use Viewpoint\Singleton;

/**
 * Map dest fields to src fields.
 */
class Map extends Singleton {
	public function __construct(
		public int|string $src_id,
		public string $post_type,
		public string $post_title,
		public string $post_content = '',
		public string|false $post_modified = false,
		public array $post_meta = [],
		public array $taxonomies = [],
	) {}

	public function get_field( string $field, object $post_data ) {
		$field_key = $this->get_field_key( $field );
		
	}

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
