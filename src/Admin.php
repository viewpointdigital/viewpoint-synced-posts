<?php
/**
 * Register admin settings and actions.
 *
 * @package Viewpoint/ViewpointSyncedPosts
 */

namespace Viewpoint\ViewpointSyncedPosts;

use Viewpoint\Utilities\Logger;
use Viewpoint\Utilities\Settings;

/**
 * Register admin page.
 */
class Admin {
	/**
	 * Settings page.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Init.
	 *
	 * @param string $name Namespace.
	 * @param bool   $debug Debug.
	 * @param Sync   $sync Sync.
	 */
	public function __construct( private string $name, private bool $debug = false, private Sync $sync ) {
		if ( ! function_exists( 'acf_add_options_page' ) ) {
			return;
		}

		add_action(
			'init',
			function () {
				$this->init_settings();
				$this->init_sync_triggers();
			},
			1
		);
	}

	/**
	 * Initialize plugin settings.
	 *
	 * @return void
	 */
	private function init_settings() {
		// Add ACF settings page, json load and save points.
		$this->settings = new Settings(
			slug: sanitize_title( $this->name ),
			name: ucwords( $this->name ) . ' ' . __( 'Settings' ),
		);

		// Add manual sync button.
		$this->settings->addMetabox( $this->settings->slug, $this->settings->name, [ $this, 'render_sync_button' ] );

		// Add custom sync fields to settings page.
		add_action(
			'acf/include_fields',
			[ $this, 'init_acf_fields' ]
		);

		// Clear action scheduler when settings are updated.
		add_action(
			'acf/options_page/save',
			function ( $post_id, $menu_slug ) {
				if ( sanitize_title( $this->name ) === $menu_slug ) {
					as_unschedule_all_actions( 'viewpoint_' . sanitize_title( $this->name ) );
				}
			},
			10,
			2
		);
	}

	/**
	 * Initialize sync triggers.
	 *
	 * @return void
	 */
	public function init_sync_triggers() {
		// Do this action to sync.
		add_action(
			'viewpoint_' . sanitize_title( $this->name ),
			function () {
				$syncTask      = $this->sync->start_sync_task();
				$statusMessage = $this->status_message( $syncTask->status );
				$this->settings->renderNotice( '<p>' . $statusMessage . '</p>' );
				if ( $this->debug ) {
					Logger::log( $statusMessage );
				}
			}
		);

		add_action( 'admin_init', [ $this, 'manual_sync' ] );
		add_action( 'init', [ $this, 'schedule_sync' ] );
	}

	/**
	 * Schedule sync.
	 *
	 * @return void
	 */
	public function schedule_sync() {
		if ( false === as_has_scheduled_action( 'viewpoint_' . sanitize_title( $this->name ) ) ) {
			as_schedule_recurring_action(
				strtotime( 'today' ),
				get_field( sanitize_title( $this->name ) . '_schedule', 'option' ) * MINUTE_IN_SECONDS,
				'viewpoint_' . sanitize_title( $this->name ),
				array(),
				'',
				true
			);
		}
	}

	/**
	 * Trigger manual sync.
	 *
	 * @return void
	 */
	public function manual_sync() {
		if ( ! current_user_can( 'manage_options' ) && ( ! wp_doing_ajax() ) ) {
			wp_die( esc_html__( 'You are not allowed to access this part of the site' ) );
		}

		$nonce_title = sanitize_title( $this->name ) . '-nonce';
		if (
			isset( $_GET[ $nonce_title ] )
			&&
			wp_verify_nonce( sanitize_key( $_GET[ $nonce_title ] ), sanitize_title( $this->name ) )
		) {
			do_action( 'viewpoint_' . sanitize_title( $this->name ) );
		}
	}

	/**
	 * Render the sync button and instructions.
	 *
	 * @return void
	 */
	public function render_sync_button() {
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
	}

	/**
	 * Format status message.
	 *
	 * @param array $status Status counts.
	 * @return string
	 */
	private function status_message( $status ) {
		return wp_kses_post( sprintf( '%d posts queued for creation, %d posts queued for update, %d posts queued for deletion, %d posts skipped. Check <a href="%s">Scheduled Actions</a> to monitor status.', $status['created'], $status['updated'], $status['deleted'], $status['skipped'], '/wp-admin/tools.php?page=action-scheduler' ) );
	}

	/**
	 * Initialize ACF settings field group.
	 *
	 * @return void
	 */
	public function init_acf_fields() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			array(
				'key'      => 'group_' . sanitize_title( $this->name ),
				'title'    => 'Options: ' . $this->name,
				'fields'   => array(
					array(
						'key'           => 'field_schedule_' . sanitize_title( $this->name ),
						'label'         => 'Schedule',
						'name'          => sanitize_title( $this->name ) . '_schedule',
						'type'          => 'number',
						'instructions'  => __( 'How often to run the sync in the background. Time in minutes.', 'viewpoint' ),
						'required'      => 1,
						'default_value' => 60,
						'step'          => 1,
					),
					array(
						'key'                => 'field_debug_' . sanitize_title( $this->name ),
						'label'              => 'Debug',
						'name'               => sanitize_title( $this->name ) . '_debug',
						'aria-label'         => '',
						'type'               => 'true_false',
						'instructions'       => '',
						'required'           => 0,
						'conditional_logic'  => 0,
						'wrapper'            => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'acfbs_allow_search' => 0,
						'message'            => __( 'Debugging will show hidden post types and taxonomies, skip transient cache of api responses, and log sync and api actions to Query Monitor output.', 'viewpoint' ),
						'default_value'      => 0,
						'ui'                 => 1,
						'ui_on_text'         => '',
						'ui_off_text'        => '',
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'options_page',
							'operator' => '==',
							'value'    => sanitize_title( $this->name ),
						),
					),
				),
				'position' => 'acf_after_title',
				'style'    => 'seamless',
			)
		);
	}
}