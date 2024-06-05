<?php
/**
 * Sync
 *
 * @package Viewpoint\ViewpointSyncedPosts
 */

namespace Viewpoint\ViewpointSyncedPosts;

use Viewpoint\ViewpointApi\Endpoint;

/**
 * Sync
 */
class Sync {
	/**
	 * Initialize Sync.
	 *
	 * @param string   $post_type WordPress post type slug.
	 * @param Endpoint $endpoint  Endpoint to call.
	 * @param Map      $map       Field mappings.
	 */
	public function __construct( private string $post_type, private Endpoint $endpoint, private Map $map ) {
	}

	/**
	 * Start sync task.
	 *
	 * @return SyncTask
	 */
	public function start_sync_task() {
		return new SyncTask( $this->set_src_posts(), $this->set_dest_posts(), $this->map );
	}

	/**
	 * Set the src posts.
	 *
	 * @return object[]
	 */
	private function set_src_posts() {
		return $this->endpoint->request( 'get' );
	}

	/**
	 * Set dest post ids.
	 *
	 * @return int[]
	 */
	private function set_dest_posts() {
		return get_posts(
			[
				'post_type'   => $this->post_type,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			]
		);
	}
}
