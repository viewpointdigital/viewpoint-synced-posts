<?php
/**
 * Sync task.
 *
 * @package Viewpoint\ViewpointSyncedPosts
 */

namespace Viewpoint\ViewpointSyncedPosts;

use Viewpoint\ViewpointApi\Formatter;

/**
 * Sync task.
 */
class SyncTask {
	/**
	 * Source post data.
	 *
	 * @var array
	 */
	public $src_posts = [];

	/**
	 * Destination post IDs.
	 *
	 * @var int[]
	 */
	public $dest_posts = [];
	/**
	 * Mapped post ids: src_id => dest_id.
	 *
	 * @var array
	 */
	public $mapped_posts = [];

	/**
	 * Sync status.
	 *
	 * @var array
	 */
	public $status = [
		'deleted' => 0,
		'created' => 0,
		'updated' => 0,
		'skipped' => 0,
	];
	/**
	 * Undocumented function
	 *
	 * @param Map $map        Field mapping.
	 */
	public function __construct( public Map $map ) {
		$this->asyncHooks( $map );
	}

	/**
	 * Sync posts.
	 *
	 * @param array $src_posts  Source post data.
	 * @param int[] $dest_posts Destination posts.
	 * @return SyncTask
	 */
	public function sync( $src_posts = [], $dest_posts = [] ) {
		$this->src_posts    = is_array( $src_posts ) ? $src_posts : [];
		$this->dest_posts   = is_array( $dest_posts ) ? $dest_posts : [];
		$this->mapped_posts = $this->setup_ids();
		$this->delete_posts();
		$this->update_posts();
		// Register sync complete hook.
		as_enqueue_async_action(
			'viewpoint_sync_complete',
			[],
			'',
			false,
			200
		);
		return $this;
	}

	public function asyncHooks( $map ) {
		add_action(
			'viewpoint_' . $map->post_type . '_delete_post',
			function ( $post_id ) use ( $map ) {
				Post::delete( $post_id );
			},
			10,
			1
		);

		add_action(
			'viewpoint_' . $map->post_type . '_create_post',
			function ( $store_key ) {
				$post_data = $this->retrieve_post_data( $store_key );
				if ( $post_data ) {
					Post::create( $post_data );
				}
			},
			10,
			2
		);

		add_action(
			'viewpoint_' . $map->post_type . '_update_post',
			function ( $post_id, $store_key ) {
				$post_data = $this->retrieve_post_data( $store_key );
				if ( $post_data ) {
					Post::update( $post_id, $post_data );
				}
			},
			10,
			3
		);

		/**
		 * Clear WP REST API cache.
		 */
		add_action(
			'viewpoint_sync_complete',
			function () {
				if ( is_plugin_active( 'wp-rest-cache/wp-rest-cache.php' ) ) {
					$caching = \WP_Rest_Cache_Plugin\Includes\Caching\Caching::get_instance();
					$caching->delete_all_caches( false );
				}
				return true;
			}
		);
	}

	/**
	 * Map src ids to dest ids.
	 *
	 * @return array
	 */
	private function setup_ids() {
		$mapped_posts = [];
		$exists       = [];
		foreach ( $this->dest_posts as $id ) {
			$src_id = get_post_meta( $id, 'src_id', true );
			if ( $src_id ) {
				if ( in_array( $src_id, $exists ) ) {
					// This is a duplicate.
					++$this->status['deleted'];
					\as_enqueue_async_action(
						'viewpoint_' . $this->map->post_type . '_delete_post',
						[
							$id,
						],
					);
				} else {
					$exists[] = $src_id;
					$mapped_posts[ $src_id ] = $id;
				}
			} else {
				// If it is not mapped to a src post, delete it.
				++$this->status['deleted'];
				\as_enqueue_async_action(
					'viewpoint_' . $this->map->post_type . '_delete_post',
					[
						$id,
					],
				);
			}
		}
		return $mapped_posts;
	}

	/**
	 * Delete posts that are no longer in src.
	 *
	 * @return void
	 */
	private function delete_posts() {
		$src_post_ids = [];
		foreach ( $this->src_posts as $src_post ) {
			$src_post_ids[] = Formatter::getNestedKeys( $this->map->src_id, $src_post );
		}
		foreach ( $this->mapped_posts as $src_id => $dest_id ) {
			if ( ! in_array( $src_id, $src_post_ids ) ) {
				++$this->status['deleted'];
				as_enqueue_async_action(
					'viewpoint_' . $this->map->post_type . '_delete_post',
					[
						$dest_id,
					],
				);
			}
		}
	}

	/**
	 * Update posts that are non-existant or outdated.
	 *
	 * @return void
	 */
	private function update_posts() {
		foreach ( $this->src_posts as $src_post ) {
			$dest_id = array_key_exists( $this->map->get( 'src_id', $src_post ), $this->mapped_posts ) ? $this->mapped_posts[ $this->map->get( 'src_id', $src_post ) ] : false;
			if ( ! $dest_id ) {
				$store_key = $this->store_post_data( $src_post );
				++$this->status['created'];
				as_enqueue_async_action(
					'viewpoint_' . $this->map->post_type . '_create_post',
					[
						$store_key,
					]
				);
				continue;
			}
			$src_date  = isset( $src_post->{$this->map->post_modified} ) ? $src_post->{$this->map->post_modified} : current_time( 'Y-m-d H:i:s', true );
			$dest_date = get_post_modified_time( 'U', true, $dest_id );
			$update    = apply_filters( 'viewpoint_update_post_check', strtotime( $src_date ) > $dest_date, $dest_id, strtotime( $src_date ), $dest_date, $src_post, $this->map );
			if ( $update ) {
				$store_key = $this->store_post_data( $src_post );
				++$this->status['updated'];
				as_enqueue_async_action(
					'viewpoint_' . $this->map->post_type . '_update_post',
					[
						$dest_id,
						$store_key,
					]
				);
				continue;
			}
			++$this->status['skipped'];
		}
	}

	/**
	 * Store post data in a transient for retrieval by Action Scheduler when task is run. Some post data is too big to store as args in Action Scheduler.
	 *
	 * @param object $src_post Source post data.
	 * @return string
	 */
	private function store_post_data( $src_post ) {
		$src_post_key = 'scheduled_post_' . uniqid();
		set_transient( $src_post_key, $this->map->getInsertPostData( $src_post ), HOUR_IN_SECONDS );
		return $src_post_key;
	}

	/**
	 * Retrieve stored post data from a transient.
	 *
	 * @param string $store_key Tranient key.
	 * @return object
	 */
	private function retrieve_post_data( $store_key ) {
		$post_data = get_transient( $store_key );
		delete_transient( $store_key );
		return $post_data;
	}
}
