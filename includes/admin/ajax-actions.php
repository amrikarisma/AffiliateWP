<?php

// retrieves a list of users via live search
function affwp_search_users() {
	if ( empty( $_REQUEST['term'] ) ) {
		wp_die( -1 );
	}

	if ( ! current_user_can( 'manage_affiliates' ) ) {
		wp_die( -1 );
	}

	$search_query = htmlentities2( trim( $_REQUEST['term'] ) );

	/**
	 * Fires immediately prior to an AffiliateWP user search query.
	 *
	 * @param string $search_query The user search query.
	 */
	do_action( 'affwp_pre_search_users', $search_query );

	$args = array(
		'search_columns' => array( 'user_login', 'display_name', 'user_email' )
	);

	if ( isset( $_REQUEST['status'] ) ) {
		$status = mb_strtolower( htmlentities2( trim( $_REQUEST['status'] ) ) );

		switch ( $status ) {
			case 'none':
				$affiliate_users = affiliate_wp()->affiliates->get_affiliates(
					array(
						'number' => -1,
						'fields' => 'user_id',
					)
				);
				$args = array( 'exclude' => $affiliate_users );
				break;
			case 'any':
				$affiliate_users = affiliate_wp()->affiliates->get_affiliates(
					array(
						'number' => -1,
						'fields' => 'user_id',
					)
				);
				$args = array( 'include' => $affiliate_users );
				break;
			default:
				$affiliate_users = affiliate_wp()->affiliates->get_affiliates(
					array(
						'number' => -1,
						'status' => $status,
						'fields' => 'user_id',
					)
				);
				$args = array( 'include' => $affiliate_users );
		}
	}

	// Add search string to args.
	$args['search'] = '*' . mb_strtolower( htmlentities2( trim( $_REQUEST['term'] ) ) ) . '*';

	// Get users matching search.
	$found_users = get_users( $args );

	$user_list = array();

	if ( $found_users ) {
		foreach( $found_users as $user ) {
			$label = empty( $user->user_email ) ? $user->user_login : "{$user->user_login} ({$user->user_email})";

			$user_list[] = array(
				'label'   => $label,
				'value'   => $user->user_login,
				'user_id' => $user->ID
			);
		}
	}

	wp_die( json_encode( $user_list ) );
}
add_action( 'wp_ajax_affwp_search_users', 'affwp_search_users' );

/**
 * Handles Ajax for processing a single batch request.
 *
 * @since 2.0
 */
function affwp_process_batch_request() {
	// Nonce check.

	if ( ! isset( $_REQUEST['batch_id'] ) ) {
		wp_send_json_error();
	} else {
		$batch_id = sanitize_key( $_REQUEST['batch_id'] );
	}

	if ( $batch_id && false === $batch = affiliate_wp()->utils->batch->get( $batch_id ) ) {
		wp_send_json_error();
	}

	// At this point, there's a valid batch process, so instantiate it.
	$process = new $batch['class'];

	// Build the response.
	$response = array();

	// Pass any data to the instance.
	if ( isset( $_REQUEST['data'] ) ) {
		$response = $_REQUEST['data'];

		// Pass the data to the process instance.
		if ( method_exists( $batch['class'], 'init' ) ) {
			$process->init( $response );
		}
	}

	$response['nonce']  = wp_create_nonce( "{$batch_id}_step_nonce" );
	$response['action'] = "{$batch_id}_process_step";

	wp_send_json_success( $response );
}
add_action( 'wp_ajax_process_batch_request', 'affwp_process_batch_request' );
