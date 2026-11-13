<?php
/**
 * Post operations.
 *
 * @package Viewpoint\ViewpointSyncedPosts
 */

namespace Viewpoint\ViewpointSyncedPosts;

/**
 * Post operations.
 */
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
	 * @return int|false
	 */
	public static function create( $post_data ) {
		return self::update( 0, $post_data );
	}

	/**
	 * Update a post.
	 *
	 * @param integer $post_id   Destination Post ID.
	 * @param array   $post_data Source post data.
	 * @return int|false
	 */
	public static function update( $post_id = 0, $post_data ) {
		$defaults  = [
			'ID'            => $post_id,
			'post_type'     => 'post',
			'post_status'   => 'draft',
			'post_modified' => current_time( 'mysql' ),
		];
		$post_data = wp_parse_args( $post_data, $defaults );

		// Create post.
		$post = wp_insert_post( $post_data );

		// Update ACF fields so they have field keys set too.
		foreach ( $post_data['meta_input'] as $field => $value ) {
			update_field( $field, $value, $post );
		}

		// Assign taxonomy terms.
		foreach ( $post_data['tax_input'] as $tax => $term ) {
			$terms = wp_set_object_terms( $post, $term, $tax, false );
			do_action( 'viewpoint_update_post_terms', $post, $terms, $post_data );
		}

		// Additional actions on post creation.
		do_action( 'viewpoint_post_updated', $post, $post_data );

		// Use wp_update_post so we can set the modified time too.
		return wp_update_post(
			[
				'ID'                => $post,
				'post_modified'     => $post_data['post_modified'],
				'post_modified_gmt' => get_gmt_from_date( $post_data['post_modified'] ),
			]
		);
	}
}
