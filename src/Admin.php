<?php
/**
 * Register admin settings and actions.
 *
 * @package Viewpoint/ViewpointSyncedPosts
 */

namespace Viewpoint\ViewpointSyncedPosts;

/**
 * Register admin page.
 */
class Admin {
	/**
	 * Init.
	 *
	 * @param string $name Namespace.
	 */
	public function __construct( private string $name ) {
		if ( ! function_exists( 'acf_add_options_page' ) ) {
			return;
		}

		$this->init_settings();
	}

	/**
	 * Initialize plugin settings.
	 *
	 * @return void
	 */
	private function init_settings() {
		// Add ACF settings page.
		add_action(
			'acf/init',
			function () {
				acf_add_options_page(
					[
						'page_title'  => ucwords( $this->name ),
						'menu_title'  => ucwords( $this->name ),
						'menu_slug'   => sanitize_title( $this->name ),
						'parent_slug' => 'options-general.php',
					]
				);
			}
		);

		// Add action button to settings page.
		add_action(
			'settings_page_' . sanitize_title( $this->name ),
			function () {
				$page = ! empty( $_GET['page'] ) ? wp_unslash( sanitize_key( $_GET['page'] ) ) : '';
				$url  = admin_url( 'options-general.php?page=' . $page );
				$url  = wp_nonce_url( $url, sanitize_title( $this->name ), sanitize_title( $this->name ) . '-nonce' );
				ob_start();
				?>
				<p><?php esc_html_e( 'Running the sync may overwrite data in the database. Please ensure you have a database backup available before running the sync.' ); ?></p>
				<a href="<?php echo esc_url( $url ); ?>" class="button button-primary button-large">
					<?php
					printf(
						/* translators: %s: Button label. */
						esc_html__( 'Run %s', 'viewpoint' ),
						esc_html( ucwords( $this->name ) )
					);
					?>
				</a>
				<?php
				echo wp_kses_post( ob_get_clean() );
			},
			15
		);
	}

	/**
	 * Initialize a migration.
	 *
	 * @param Sync $sync Sync.
	 * @return void
	 */
	public function init_sync_triggers( $sync ) {
		add_action(
			'admin_init',
			function () use ( $sync ) {
				if ( ! current_user_can( 'manage_options' ) && ( ! wp_doing_ajax() ) ) {
					wp_die( esc_html__( 'You are not allowed to access this part of the site' ) );
				}

				$nonce_title = sanitize_title( $this->name ) . '-nonce';
				if (
					isset( $_GET[ $nonce_title ] )
					&&
					wp_verify_nonce( sanitize_key( $_GET[ $nonce_title ] ), sanitize_title( $this->name ) )
				) {
					$syncTask = $sync->start_sync_task();
					$this->render_status( $syncTask->status );
				}
			},
			0
		);
	}

	/**
	 * Display the admin status notice.
	 *
	 * @param array $status Status counts.
	 * @return void
	 */
	private function render_status( $status ) {
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( sprintf( '%d posts successfully created, %d posts successfully  updated, %d posts successfully deleted, %d posts skipped.', $status['created'], $status['updated'], $status['deleted'], $status['skipped'] ) ); ?></p>
		</div>
		<?php
	}
}