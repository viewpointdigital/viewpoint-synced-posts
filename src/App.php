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
	 * Initialize app and post type.
	 *
	 * @param string $post_type  Post type to sync.
	 * @param array  $taxonomies Taxonomies to create.
	 * @param array  $args       Register post type args.
	 * @param bool   $debug      Debug. Makes posts public and more verbose logging of process.
	 */
	public function __construct(
		public string $post_type,
		public array $taxonomies = [],
		private array $args = [],
		public bool $debug = false
	) {
		$this->initialize_args( $args, $taxonomies );
		$this->initialize_admin_page();
		add_action( 'init', [ $this, 'register' ] );
	}

	/**
	 * Initialize args for post types and taxonomies with some defaults.
	 *
	 * @param array $args       Post type args.
	 * @param array $taxonomies Taxonomies and args.
	 * @return void
	 */
	private function initialize_args( $args, $taxonomies ) {
		// Set up post type args.
		$this->args = wp_parse_args(
			$args,
			[
				'public'   => $this->debug,
				'rewrite'  => [
					'with_front' => false,
				],
				'label'    => ucwords( str_replace( [ '_', '-' ], ' ', $this->post_type ) ),
				'supports' => [ 'title', 'editor', 'custom-fields', 'thumbnail' ],
			]
		);
		// Set up taxonomies and tax args.
		$this->taxonomies = [];
		foreach ( $taxonomies as $taxonomy => $tax_args ) {
			if ( is_int( $taxonomy ) ) {
				$taxonomy = $tax_args;
				$tax_args = [];
			}
			$tax_args                      = wp_parse_args(
				$tax_args,
				[
					'object_type' => $this->post_type,
					'public'      => $this->debug,
					'rewrite'     => [
						'with_front' => false,
						'slug'       => str_replace( '_', '-', $taxonomy ),
					],
					'label'       => ucwords( str_replace( [ '_', '-' ], ' ', $taxonomy ) ),
				]
			);
			$this->taxonomies[ $taxonomy ] = $tax_args;
		}
	}

	/**
	 * Register custom post types and taxonomies.
	 *
	 * @return void
	 */
	public function register() {
		register_post_type(
			$this->post_type,
			$this->args,
		);

		foreach ( $this->taxonomies as $taxonomy => $tax_args ) {
			register_taxonomy(
				$taxonomy,
				$this->post_type,
				$tax_args
			);
		}
	}

	public function initialize_admin_page() {
		return new Admin( $this->post_type . ' ' . __( 'Sync', 'viewpoint' ) );
	}

	public function sync( Endpoint $endpoint, $map ) {
		$src_posts  = $endpoint->request( 'get' )->format( 'json', 'data' );
		$dest_posts = get_posts(
			[
				'post_type' => $this->post_type,
				'status'    => 'any',
			]
		);
		$sync       = new Sync( $src_posts, $dest_posts, $map );
	}
}
