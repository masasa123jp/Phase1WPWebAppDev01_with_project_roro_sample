<?php
/**
 * 施設検索 API テスト
 */

class Test_Facility_Search_Endpoint extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		// ダミー施設挿入
		global $wpdb;
		$table = $wpdb->prefix . 'roro_facilities';
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				name VARCHAR(255) NOT NULL,
				address VARCHAR(255) NOT NULL,
				lat DOUBLE NOT NULL,
				lng DOUBLE NOT NULL,
				PRIMARY KEY (id)
			) {$wpdb->get_charset_collate()}"
        );

		$wpdb->insert(
			$table,
			[
				'name'    => 'Test Café',
				'address' => 'Tokyo',
				'lat'     => 35.6895,
				'lng'     => 139.6917,
			]
		);

		\RoroCore\Api\Endpoint_Facility_Search::register();
	}

	public function test_search_returns_result(): void {
		$request = new WP_REST_Request(
			'GET',
			'/roro/v1/facilities'
		);
		$request->set_query_params(
			[
				'lat'    => 35.6895,
				'lng'    => 139.6917,
				'radius' => 5000,
			]
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertNotEmpty( $data );
		$this->assertEquals( 'Test Café', $data[0]['name'] );
	}
}
