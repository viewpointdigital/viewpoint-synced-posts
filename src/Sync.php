<?php
/**
 * Sync
 * 
 * @package Viewpoint\ViewpointSyncedPosts
 */

namespace Viewpoint\ViewpointSyncedPosts;

use Viewpoint\ViewpointApi\Endpoint;

class Sync {
	public function __construct( private string $post_type, private Endpoint $endpoint, private Map $map ) {
	}

	public function startSyncTask() {
		return new SyncTask( $this->setSrcPosts(), $this->setDestPosts(), $this->map );
	}

	private function setSrcPosts() {
		return $this->endpoint->request( 'get' )->format( 'json', 'data' );
	}

	private function setDestPosts() {
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
