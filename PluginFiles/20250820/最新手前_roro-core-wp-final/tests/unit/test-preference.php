<?php
/**
 * Preference API tests.
 *
 * @package RoroCore\Tests
 */

declare( strict_types = 1 );

class Preference_Endpoint_Test extends WP_UnitTestCase {

	public function test_preference_crud() {
		$user_id = $this->factory()->user->create();
		wp_set_current_user( $user_id );

		// Create/update.
		$request = new WP_REST_Request( 'POST', '/roro/v1/preference' );
		$request->add_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body_params( [ 'line' => true, 'email' => false ] );
		$res = rest_do_request( $request );
		$this->assertSame( 200, $res->get_status() );
		$this->assertTrue( get_user_meta( $user_id, 'roro_notification_pref', true )['line'] );

		// Read.
		$request = new WP_REST_Request( 'GET', '/roro/v1/preference' );
		$request->add_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$res = rest_do_request( $request );
		$this->assertSame( 200, $res->get_status() );
		$this->assertTrue( $res->get_data()['line'] );
	}
}
