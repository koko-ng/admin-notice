<?php
/**
 * Utility functions mostly
 *
 * @package Admin_Notice
 */

namespace Admin_Notice;

use Admin_Notice\Exception;

/**
 * Higher-order function to set notices on errors.
 *
 * @param callable $cb The function to wrap.
 * @param int      $post_position Position of the post parameter passed to the action.
 * @since  1.0
 */
function with_error_notice( callable $cb, int $post_position = 0 ) {
	return function ( ...$rest ) use ( $cb, $post_position ) {
		try {
			return call_user_func_array( $cb, $rest );
		} catch ( \Throwable $e ) {
			$msg  = $e->getMessage();
			$post = $rest[ $post_position ];
			if ( ! $e instanceof Exception ) {
				$e = Exception::fromThrowable( $e, NoticeLevels::Error );
			}

			if ( $post instanceof \WP_Post ) {
				set_notice_status( $post->ID, $e );
			} elseif ( is_int( $post ) ) {
				set_notice_status( $post, $e );
			} else {
				set_notice_status( $post, new Exception( 'Wrong post parameter', 0, NoticeLevels::Error ) );
			}
		}
	};
}

enum NoticeLevels: String {
	case Info    = 'info';
	case Warning = 'warning';
	case Error   = 'error';
	case Success = 'success';
}
/**
 * Set the notice for a post.
 *
 * @param  Integer   $post_id Post ID.
 * @param  Exception $e Notice exception.
 * @return void
 * @since  1.0
 */
function set_notice_status( int $post_id, Exception $e ) {
	$notice    = array(
		'level'   => $e->getLevel()->value,
		'message' => $e->getMessage(),
	);
	$transient = sprintf( 'admin-notice-%d-%d', $post_id, get_current_user_id() );
	set_transient( $transient, wp_json_encode( $notice ), 3600 );
}


/**
 * Custom route to check notice transients.
 *
 * @return void
 * @since  1.0
 */
function rest_init() {
	$namespace = 'admin-notice/v1';

	register_rest_route(
		$namespace,
		'notices/(?P<id>\d+)',
		array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\get_notices',
			'args'                => array(
				'id' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return is_numeric( $param ) && intval( $param ) > 0;
					},
					'sanitize_callback' => 'absint',
					'required'          => true,
				),
			),
			'permission_callback' => function() {
				return current_user_can( 'edit_posts' ); },
		)
	);
}

add_action( 'rest_api_init', __NAMESPACE__ . '\rest_init' );

add_action( 'admin_footer-post.php', __NAMESPACE__ . '\admin_notice_script' );
add_action( 'admin_footer-post-new.php', __NAMESPACE__ . '\admin_notice_script' );

/**
 * Adds script to make ajax call - checking notifications via REST.
 *
 * @return void
 * @since 1.0
 */
function admin_notice_script() {
	// @see https://wordpress.stackexchange.com/a/398805/103640
	// @see https://github.com/WordPress/gutenberg/issues/17632#issuecomment-583772895
	?>
	<script type="text/javascript">
		/**
		 * Consults values to determine whether the editor is busy saving a post.
		 * Includes checks on whether the save button is busy.
		 *
		 * @returns {boolean} Whether the editor is on a busy save state.
		 */
		function isSavingPost() {

			// State data necessary to establish if a save is occuring.
			const isSaving = wp.data.select('core/editor').isSavingPost() || wp.data.select('core/editor').isAutosavingPost();
			const isSaveable = wp.data.select('core/editor').isEditedPostSaveable();
			const isPostSavingLocked = wp.data.select('core/editor').isPostSavingLocked();
			const hasNonPostEntityChanges = wp.data.select('core/editor').hasNonPostEntityChanges();
			const isAutoSaving = wp.data.select('core/editor').isAutosavingPost();
			const isButtonDisabled = isSaving || !isSaveable || isPostSavingLocked;

			// Reduces state into checking whether the post is saving and that the save button is disabled.
			const isBusy = !isAutoSaving && isSaving;
			const isNotInteractable = isButtonDisabled && !hasNonPostEntityChanges;

			return isBusy && isNotInteractable;
		}
		function isSavingMetaBoxes() {

			// State data necessary to establish if a save is occuring.
			const isSaveable = wp.data.select('core/editor').isEditedPostSaveable();
			const isPostSavingLocked = wp.data.select('core/editor').isPostSavingLocked();
			const hasNonPostEntityChanges = wp.data.select('core/editor').hasNonPostEntityChanges();
			const isAutoSaving = wp.data.select('core/editor').isAutosavingPost();
			const isSavingMetaBoxes = wp.data.select('core/edit-post').isSavingMetaBoxes();

			const isButtonDisabled = isSavingMetaBoxes || !isSaveable || isPostSavingLocked;

			// Reduces state into checking whether the post is saving and that the save button is disabled.
			const isBusy = !isAutoSaving && isSavingMetaBoxes;
			const isNotInteractable = isButtonDisabled && !hasNonPostEntityChanges;

			return isBusy && isNotInteractable;
		}

		const { subscribe,select } = wp.data;
		// Current saving state. isSavingPost is defined above.
		var wasSaving = isSavingPost();
		var wasSavingMetaBoxes = isSavingMetaBoxes();
		subscribe(() => {
			const hasActiveMetaBoxes = wp.data.select('core/edit-post').hasMetaBoxes();

			// New saving state
			let isSaving = isSavingPost();
			let isSavingMB = isSavingMetaBoxes();

			// It is done saving if it was saving and it no longer is.
			let isDoneSaving = wasSaving && !isSaving;
			let isDoneSavingMetaBoxes = wasSavingMetaBoxes && !isSavingMB;

			// If we have metaboxes only move to next state once they have been updated too
			if ( !hasActiveMetaBoxes || !wasSaving || (wasSaving && isDoneSavingMetaBoxes)  ) {
				wasSaving = isSaving;
			}
			wasSavingMetaBoxes = isSavingMetaBoxes();

			if (isDoneSaving && (hasActiveMetaBoxes && isDoneSavingMetaBoxes) ) {
				checkNotificationAfterPublish();
			}
		});

		function checkNotificationAfterPublish() {
			const postId = wp.data.select("core/editor").getCurrentPostId();
			const url = `/wp-json/admin-notice/v1/notices/${postId}`
			wp.apiFetch({
				url,
			}).then(
				function(response) {
					if (response.code) {
						wp.data.dispatch("core/notices").createNotice(
							response.code,
							response.message + " ", {
								id: 'simple-admin-notice',
								isDismissible: true
							}
						);
					} else {
						wp.data.dispatch("core/notices").removeNotice('simple-admin-notice');
					}
				}
			);
		};
	</script>
	<?php
}



/**
 * Send error response to REST endpoint.
 *
 * @param \WP_REST_Request $request The WP REST request.
 */
function get_notices( \WP_REST_Request $request ) {
	$id = intval( $request['id'] );

	$transient = sprintf( 'admin-notice-%d-%d', $id, get_current_user_id() );
	$error     = get_transient( $transient );
	delete_transient( $transient );

	if ( $error ) {
		$data = json_decode( $error );
		return new \WP_REST_Response(
			array(
				'code'    => $data->level,
				'message' => wp_unslash( $data->message ),
			)
		);
	} else {
		return new \WP_REST_Response( array() );
	}
}

function legacy_notice() {
	$transient = sprintf( 'admin-notice-%d-%d', get_the_ID(), get_current_user_id() );
	$error     = get_transient( $transient );
	delete_transient( $transient );

	if ( $error ) {
		$data = json_decode( $error );
      echo '<div class="notice notice-'.$data->level.' is-dismissible">
      <p>'.esc_html($data->message).'</p>
      </div>';
    }
}
add_action( 'admin_notices', __NAMESPACE__ . '\legacy_notice' );
