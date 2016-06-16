<?php
namespace BU\Tests;

/**
 * @group basic
 */
class Test_Basic extends \WP_UnitTestCase {
	public $old_user_id = 0;
	public $user_id     = 0;
	public $factory;


	public function setUp() {
		parent::setUp();

		$this->factory = new \WP_UnitTest_Factory;

		// Set current user ID for new posts.
		$this->user_id = $this->factory->user->create();
		$this->old_user_id = get_current_user_id();

		wp_set_current_user( $this->user_id );
	}

	public function tearDown() {
		wp_set_current_user( $this->old_user_id );
		parent::tearDown();
	}


	public function test_img_detection() {

		$post_arr = array(
			'ID'			=> '999',
			'post_content' 	=> '<strong><a href="/eng/files/2016/04/Anil-Virkar.jpg"><img src="/eng/files/2016/04/Anil-Virkar-240x300.jpg" alt="Anil Virkar" class="alignleft size-medium wp-image-5785" height="197" width="157"></a>3:00 PM in Room 205, 8 St. Mary’s Street</strong>',
		);

		$post = (object) $post_arr;

		$result = \BU\Migrate\MediaFix::get_relative_imgs( $post );

		$this->assertSame( $result[0][1], '/eng/files/2016/04/Anil-Virkar-240x300.jpg' );

	}
}
