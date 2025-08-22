<?php
/**
 * Review 投稿 API テスト
 */

class Test_Review_Submit_Endpoint extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		global $wpdb;
		$table = $wpdb->prefix . 'roro_reviews';
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$table} (
				id BIGINT AUTO_INCREMENT PRIMARY KEY,
				user_id BIGINT NOT NULL,
				facility_id BIGINT NOT NULL,
				rating DECIMAL(2,1),
				comment TEXT,
				created_at DATETIME
			) {$wpdb->get_charset_collate()}"
		);

		wp_set_current_user( $this->factory()->user->create() );
		\RoroCore\Api\Endpoint_Review_Submit::register();
	}

	public function test_review_insert(): void {
		$request = new WP_REST_Request( 'POST', '/roro/v1/reviews' );
		$request->add_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body_params(
			[
				'facility_id' => 123,
				'rating'      => 4.5,
				'comment'     => 'Great!',
			]
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );
	}
}
