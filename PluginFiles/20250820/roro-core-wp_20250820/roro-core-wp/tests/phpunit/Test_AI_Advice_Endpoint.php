<?php
/**
 * AI Advice API テスト
 */

class Test_AI_Advice_Endpoint extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		wp_set_current_user( $this->factory()->user->create() );
		\RoroCore\Api\Endpoint_AI_Advice::register();
	}

	public function test_ai_endpoint_requires_key(): void {
		$request = new WP_REST_Request( 'POST', '/roro/v1/ai/advice' );
		$request->add_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body_params( [ 'question' => 'How to feed a puppy?' ] );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 500, $response->get_status() );
	}
}
