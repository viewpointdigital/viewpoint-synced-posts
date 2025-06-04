<?php
/**
 * Define App
 *
 * @package viewpoint
 */

namespace Viewpoint\ViewpointSyncedPosts;

use Viewpoint\ViewpointApi\Endpoint;
use Viewpoint\ViewpointApi\JoinEndpoint;

/**
 * Define App.
 */
class App {
	/**
	 * Sync interface.
	 *
	 * @var Sync
	 */
	public Sync $sync;

	/**
	 * Admin interface.
	 *
	 * @var Admin
	 */
	private Admin $admin;

	/**
	 * Constructor.
	 *
	 * @param CustomPostType        $cpt Post type.
	 * @param Endpoint|JoinEndpoint $endpoint  Endpoint interface.
	 * @param Map                   $map       Field mapping.
	 * @param boolean               $debug     Debug.
	 */
	public function __construct(
		public CustomPostType $cpt,
		public Endpoint|JoinEndpoint $endpoint,
		public Map $map,
		public bool $debug = false,
	) {
		$this->sync  = new Sync(
			$this->cpt->post_type,
			$this->endpoint,
			$this->map
		);
		$this->admin = new Admin(
			$this->cpt->post_type,
			$this->cpt->args['label'] . ' ' . __( 'Sync', 'viewpoint' ),
			$this->sync,
			$this->debug
		);
	}
}
