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
	public function __construct( public string $name ) {
		if ( ! function_exists( 'acf_add_options_page' ) ) {
			return;
		}

		$this->init_settings();
		$this->init_migration();
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
				$page = ! empty( $_GET['page'] ) ? wp_unslash( $_GET['page'] ) : '';
				$url  = admin_url( 'options-general.php?page=' . $page );
				$url  = wp_nonce_url( $url, sanitize_title( $this->name ), sanitize_title( $this->name ) . '-nonce' );
				ob_start();
				?>
				<p><?php esc_html_e( 'Running the migration will overwrite data in the database. Please review field settings, save settings, and ensure you have a database backup available before running the migration.' ); ?></p>
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
	 * @return void
	 */
	private function init_migration() {
		add_action(
			'admin_init',
			function () {
				if ( ! current_user_can( 'manage_options' ) && ( ! wp_doing_ajax() ) ) {
					wp_die( esc_html__( 'You are not allowed to access this part of the site' ) );
				}

				$nonce = isset( $_GET[ sanitize_title( $this->name ) . '-nonce' ] ) ? wp_unslash( $_GET[ sanitize_title( $this->name ) . '-nonce' ] ) : false;
				if ( ! wp_verify_nonce( $nonce, sanitize_title( $this->name ) ) ) {
					return;
				}
			},
			0
		);
	}
}