<?php
/**
 * Sync
 *
 * @package Viewpoint\ViewpointSyncedPosts
 */

namespace Viewpoint\ViewpointSyncedPosts;

use Viewpoint\ViewpointApi\Endpoint;
use Viewpoint\ViewpointApi\JoinEndpoint;

/**
 * Sync
 */
class Sync {
	/**
	 * Sync task.
	 *
	 * @var SyncTask
	 */
	public SyncTask $task;

	/**
	 * Initialize Sync.
	 *
	 * @param string   $post_type WordPress post type slug.
	 * @param Endpoint $endpoint  Endpoint to call.
	 * @param Map      $map       Field mappings.
	 */
	public function __construct(
		private string $post_type,
		private Endpoint|JoinEndpoint $endpoint,
		public Map $map
	) {
		$this->task = new SyncTask( $this->map );
	}

	/**
	 * Start sync task.
	 *
	 * @return SyncTask
	 */
	public function start_sync_task() {
		return $this->task->sync( $this->set_src_posts(), $this->set_dest_posts() );
	}

	/**
	 * Set the src posts.
	 *
	 * @return object[]
	 */
	private function set_src_posts() {
		return apply_filters(
			'viewpoint_sync_src_posts',
			$this->endpoint->request(),
			$this->post_type
		);
	}

	/**
	 * Set dest post ids.
	 *
	 * @return int[]
	 */
	private function set_dest_posts() {
		return apply_filters(
			'viewpoint_sync_dest_posts',
			get_posts(
				[
					'post_type'   => $this->post_type,
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
				]
			)
		);
	}
}
