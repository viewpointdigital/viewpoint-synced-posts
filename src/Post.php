<?php
/**
 * Post operations.
 *
 * @package Viewpoint\ViewpointSyncedPosts
 */

namespace Viewpoint\ViewpointSyncedPosts;

class Post {
	/**
	 * Delete a post.
	 *
	 * @param int $post_id Destination Post ID.
	 * @return int|false
	 */
	public static function delete( $post_id ) {
		return wp_delete_post( $post_id, true );
	}

	/**
	 * Create a new post.
	 *
	 * @param object $post_data Source post data.
	 * @param Map    $map       Data mapping.
	 * @return int|false
	 */
	public static function create( $post_data, $map ) {
		return self::update( 0, $post_data, $map );
	}

	/**
	 * Update a post.
	 *
	 * @param integer $post_id   Destination Post ID.
	 * @param object  $post_data Source post data.
	 * @param Map     $map       Data mapping.
	 * @return int|false
	 */
	public static function update( $post_id = 0, $post_data, $map ) {
		$args = [
			'ID'           => $post_id,
			'post_type'    => $map->post_type,
			'post_title'   => $post_data->{$map->post_title},
			'post_content' => $post_data->{$map->post_content},
		];

		$args['meta_input'] = [
			'src_id' => $post_data->{$map->src_id},
		];
		foreach ( $map->post_meta as $dest_key => $src_key ) {
			$args['meta_input'][ $dest_key ] = $post_data->{$src_key};
		}

		$args['tax_input'] = [];
		foreach ( $map->taxonomies as $dest_key => $src_key ) {
			$tax_value = $post_data->{$src_key};

			// If a nested value.
			if ( str_contains( $src_key, '.' ) ) {
				$tax_value  = [];
				$tax_fields = explode( '.', $src_key );
				$tax_value  = wp_list_pluck( $post_data->{$tax_fields[0]}, $tax_fields[1] );
			}

			$args['tax_input'][ $dest_key ] = $tax_value;
		}

		return wp_insert_post( $args );
	}
}
