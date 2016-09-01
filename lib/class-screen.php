<?php
namespace Admin_Page_Locking;

/**
 * Admin Page Locking main class.
 */
class Screen {

	/**
	 * The page slug for the current screen.
	 *
	 * @var string
	 */
	protected $page;

	/**
	 * Number of seconds a lock is valid.
	 *
	 * @var integer
	 */
	protected $lock_period = 30;

	/**
	 * Max number of seconds for all locks in a session.
	 *
	 * @var integer
	 */
	protected $max_lock_period = 600;

	/**
	 * Messages displayed to the user.
	 *
	 * @var array
	 */
	protected $messages;

	public function __construct( $page ) {
		$this->page = $page;
		$this->lock_period 	= apply_filters( 'admin_page_locking_lock_period', $this->lock_period );
		$this->max_lock_period = apply_filters( 'admin_page_locking_max_lock_period', $this->max_lock_period );

		$this->messages = apply_filters(
			'admin_page_locking_messages',
			[
				'error-lock' => __( 'Sorry, this screen is in use by %s and is currently locked. Please try again later.', 'admin-page-locking' ),
				'error-lock-max' => __( 'Sorry, you have reached the maximum idle limit and will now be redirected to the Dashboard.', 'admin-page-locking' ),
				'nonce' => __( 'It looks like you may have been on this page for too long. Please refresh your browser.', 'admin-page-locking' ),
				'more-time' => __( 'You are approaching the maximum time limit for this screen. Would you like more time? Answering "No" will return you to the dashboard and any unsaved changes will be lost.', 'admin-page-locking' ),
			],
			$this->page
		);

		add_action( 'admin_print_scripts-' . $page, [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'wp_ajax_admin-page-locking-update-' . $this->page, [ $this, 'ajax_update_lock' ] );
		add_action( 'wp_ajax_admin-page-locking-release-' . $this->page, [ $this, 'ajax_release_lock' ] );
		add_action( 'admin_notices', [ $this, 'maybe_add_alert' ] );
	}

	public function admin_enqueue_scripts() {
		add_thickbox();
		wp_enqueue_script( 'admin-page-locking-js', URL . '/static/admin-page-locking.js', [ 'jquery', 'thickbox' ], '0.1.0', true );

		wp_localize_script( 'admin-page-locking-js', 'adminPageLockingData', [
			'adminUrl'          => esc_url_raw( admin_url() ),
			'ajaxUrl'           => esc_url_raw( wp_nonce_url( admin_url( 'admin-ajax.php' ), 'apl_lock_nonce' ) ),
			'actionUpdateLock'  => 'admin-page-locking-update-' . $this->page,
			'actionReleaseLock' => 'admin-page-locking-release-' . $this->page,
			'errorLock'         => sprintf( $this->messages['error-lock'], __( 'another user', 'admin-page-locking' ) ),
			'errorLockMax'      => $this->messages['error-lock-max'],
			'moreTime'          => $this->messages['more-time'],
			'lockPeriod'        => $this->lock_period,
			'lockPeriodMax'     => $this->max_lock_period,
		] );
	}

	protected function get_lock_key() {
		return 'apl-' . md5( $this->page );
	}

	public function ajax_update_lock() {
		if ( ! check_ajax_referer( 'apl_lock_nonce' ) ) {
			wp_send_json_error( [ 'message' => $this->messages['nonce'] ] );
		}

		$this->lock();
		wp_send_json_success();
	}

	public function ajax_release_lock() {
		if ( ! check_ajax_referer( 'apl_lock_nonce' ) ) {
			wp_send_json_error( [ 'message' => $this->messages['nonce'] ] );
		}

		$this->unlock();
		wp_send_json_success();
	}


	public function lock( $user_id = 0 ) {
		if( ! $user_id ) {
			$user = wp_get_current_user();
			$user_id = $user->ID;
		}

		// Add a one to avoid most race condition issues between lock expiry and ajax call
		$expiry = $this->lock_period + 1;
		set_transient( $this->get_lock_key(), $user->ID, $expiry );
	}

	public function unlock() {
		delete_transient( $this->get_lock_key() );
	}

	public function is_locked() {
		$user = wp_get_current_user();

		$lock = get_transient( $this->get_lock_key() );

		// If lock doesn't exist, or check if current user same as lock user
		if ( ! $lock || absint( $lock ) === absint( $user->ID ) ) {
			return false;
		} else {
			// return user_id of locking user
			return absint( $lock );
		}
	}

	public function maybe_add_alert() {
		global $hook_suffix;

		if ( $this->page === $hook_suffix ) :
			$locked = $this->is_locked();
			if ( $locked ) :
				$locking_user = get_userdata( $locked );
				?>
				<div class="updated inline error">
					<p>
						<?php
						printf(
							esc_html( $this->messages['error-lock'] ),
							sprintf( '<a href="%s">%s</a>', esc_url( 'mailto:' . $locking_user->user_email ), esc_html( $locking_user->display_name ) )
						);
						?>
					</p>
				</div>
				<input type="hidden" id="apl-user" name="apl-user" value="1" />
				<?php
			endif;
		endif;
	}

	/**
	 * Outputs the HTML for the notice to say that someone else is editing or has
	 * taken over editing of this screen.
	 *
	 * The following code has been reused and modified from WordPress.org, and is
	 * released under GPLv2. Copyright 2011-2016 WordPress.org.
	 */
	function _admin_notice_post_locked() {
		$user = null;
		if ( $user_id = $this->is_locked( $post->ID ) ) {
			$user = get_userdata( $user_id );
		}

		$locked = (bool) $user_id;

		$sendback = admin_url();
		$hidden = $locked ? '' : ' hidden';

		?>
		<div id="post-lock-dialog" class="notification-dialog-wrap<?php echo $hidden; ?>">
			<div class="notification-dialog-background"></div>
			<div class="notification-dialog">
				<?php
				if ( $locked ) {
					/**
					 * Filter whether to allow the post lock to be overridden.
					 *
					 * Returning a falsey value to the filter will disable the ability
					 * to override the post lock.
					 *
					 * @param bool $override Whether to allow overriding post locks. Default true.
					 * @param string $page The current page.
					 * @param WP_User $user User object.
					 */
					$override = apply_filters( 'override_post_lock', true, $this->page, $user );
					$tab_last = $override ? '' : ' wp-tab-last';
					?>
					<div class="post-locked-message">
						<div class="post-locked-avatar"><?php echo get_avatar( $user->ID, 64 ); ?></div>
						<p class="currently-editing wp-tab-first" tabindex="0">
							<?php
							esc_html_e( 'This content is currently locked.', 'admin-page-locking' );
							if ( $override ) {
								printf( ' ' . esc_html__( 'If you take over, %s will be blocked from continuing to edit and their changes may be lost.', 'admin-page-locking' ), esc_html( $user->display_name ) );
							}
							?>
						</p>
						<p>
							<a class="button" href="<?php echo esc_url( $sendback ); ?>"><?php echo esc_html( $sendback_text ); ?></a>

							<?php
							// Allow plugins to prevent some users overriding the post lock
							if ( $override ) :
								$override_uri = wp_nonce_url( add_query_arg( 'take-over', 1 ), 'lock-page-' . $this->page ) )
								?>
								<a class="button button-primary wp-tab-last" href="<?php echo esc_url( $override_uri ); ?>"><?php esc_html_e( 'Take over', 'admin-page-locking' ); ?></a>
								<?php
							endif;
							?>
						</p>
					</div>
					<?php
				} else {
					?>
					<div class="post-taken-over">
						<div class="post-locked-avatar"></div>
						<p class="wp-tab-first" tabindex="0">
							<span class="currently-editing"></span><br />
							<span class="locked-saving hidden"><img src="<?php echo esc_url( admin_url( 'images/spinner-2x.gif' ) ); ?>" width="16" height="16" alt="" /> <?php _e( 'Saving revision&hellip;' ); ?></span>
							<span class="locked-saved hidden"><?php _e('Your latest changes were saved as a revision.'); ?></span>
						</p>
						<p><a class="button button-primary wp-tab-last" href="<?php echo esc_url( $sendback ); ?>"><?php echo esc_html( $sendback_text ); ?></a></p>
					</div>
					<?php
				}
				?>
			</div>
		</div>
		<?php
	}
}
