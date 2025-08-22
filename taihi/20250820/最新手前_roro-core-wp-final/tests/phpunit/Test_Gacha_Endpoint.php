<?php
/**
 * ガチャ API テスト
 */

class Test_Gacha_Endpoint extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		wp_set_current_user( $this->factory()->user->create() );
		\RoroCore\Api\Endpoint_Gacha::register();
	}

	public function test_gacha_returns_item(): void {
		$request  = new WP_REST_Request( 'POST', '/roro/v1/gacha' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'result', $data );
		$this->assertNotEmpty( $data['result'] );
	}
}
