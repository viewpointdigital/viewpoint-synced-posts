<?php
/**
 * Define App
 *
 * @package viewpoint
 */

namespace Viewpoint\ViewpointSyncedPosts;

use Viewpoint\ViewpointApi\Endpoint;

/**
 * Define App.
 */
class App {
	/**
	 * Custom Post Type to create.
	 *
	 * @var CustomPostType
	 */
	public CustomPostType $cpt;

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
	 * @param string   $post_type Post type.
	 * @param Endpoint $endpoint  Endpoint interface.
	 * @param Map      $map       Field mapping.
	 * @param boolean  $debug     Debug.
	 */
	public function __construct(
		public string $post_type,
		public Endpoint $endpoint,
		public Map $map,
		public bool $debug = false,
	) {}

	/**
	 * Set up custom post type.
	 *
	 * @param array $taxonomies Keyed array of taxonomies and tax args for registration.
	 * @param array $args       Array of post type args for registration.
	 * @return self
	 */
	public function set_cpt(
		array $taxonomies = [],
		array $args = [],
	) {
		$this->cpt = new CustomPostType( $this->post_type, $taxonomies, $args, $this->debug );
		return $this;
	}

	/**
	 * Set up sync interface.
	 *
	 * @return self
	 */
	public function set_sync() {
		$this->sync = new Sync( $this->post_type, $this->endpoint, $this->map );
		return $this;
	}

	/**
	 * Set up admin interface.
	 *
	 * @return self
	 */
	public function set_admin() {
		$this->admin = new Admin( $this->post_type . ' ' . __( 'Sync', 'viewpoint' ) );
		$this->admin->init_migration( $this->sync );
		return $this;
	}
}
