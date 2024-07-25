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
	 * @param array $src_posts  Source post data.
	 * @param int[] $dest_posts Destination posts.
	 * @param Map   $map        Field mapping.
	 */
	public function __construct( public $src_posts = [], public $dest_posts = [], public Map $map ) {
		$this->asyncHooks( $map );
	}

	/**
	 * Sync posts.
	 *
	 * @return void
	 */
	public function sync() {
		$this->mapped_posts = $this->setup_ids();
		$this->delete_posts();
		$this->update_posts();
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
			function ( $post_data ) {
				Post::create( $post_data );
			},
			10,
			2
		);

		add_action(
			'viewpoint_' . $map->post_type . '_update_post',
			function ( $post_id, $post_data ) {
				$success = Post::update( $post_id, $post_data );
			},
			10,
			3
		);
	}

	/**
	 * Map src ids to dest ids.
	 *
	 * @return array
	 */
	private function setup_ids() {
		$mapped_posts = [];
		$exists = [];
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
				++$this->status['created'];
				as_enqueue_async_action(
					'viewpoint_' . $this->map->post_type . '_create_post',
					[
						$this->map->getInsertPostData( $src_post ),
					]
				);
				continue;
			}
			$src_date  = $src_post->{$this->map->post_modified};
			$dest_date = get_the_modified_date( '', $dest_id );
			if ( strtotime( $src_date ) > strtotime( $dest_date ) ) {
				++$this->status['updated'];
				as_enqueue_async_action(
					'viewpoint_' . $this->map->post_type . '_update_post',
					[
						$dest_id,
						$this->map->getInsertPostData( $src_post ),
					]
				);
			}
			++$this->status['skipped'];
		}
	}
}
