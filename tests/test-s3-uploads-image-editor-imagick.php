<?php

class Test_S3_Uploads_Image_Editor_Imagick extends WP_UnitTestCase {

	public function setUp() {
		$this->image_path = dirname( __FILE__ ) . '/data/sunflower.jpg';

		require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
		require_once ABSPATH . WPINC . '/class-wp-image-editor-imagick.php';

		if ( ! WP_Image_Editor_Imagick::test() ) {
			$this->markTestSkipped( 'WP_Image_Editor_Imagick test failed' );
		}
	}
	public function test_s3_upload_image_editor_is_present() {
		$editors = apply_filters( 'wp_image_editors', array( 'WP_Image_Editor_Imagick', 'WP_Image_Editor_GD' ) );

		$this->assertFalse( in_array( 'WP_Image_Editor_Imagick', $editors ), 'Imagick editor should be removed from the image editors array.' );
	}

	public function test_save_image() {

		$upload_dir = wp_upload_dir();
		$path = $upload_dir['basedir'] . '/sunflower.jpg';
		copy( $this->image_path, $path );

		$image_editor = new S3_Uploads\Image_Editor_Imagick( $path );

		$image_editor->load();
		$image_editor->resize( 100, 100, true );
		$status = $image_editor->save( $upload_dir['basedir'] . '/sunflower-100x100.jpg' );

		$this->assertNotInstanceOf( 'WP_Error', $status );

		$this->assertEquals( $upload_dir['basedir'] . '/sunflower-100x100.jpg', $status['path'] );
		$this->assertEquals( 'sunflower-100x100.jpg', $status['file'] );
		$this->assertEquals( 100, $status['width'] );
		$this->assertEquals( 100, $status['height'] );

		$image = getimagesize( $status['path'] );

		$this->assertEquals( array(
			100,
			100,
			2,
			'width="100" height="100"',
			'bits' => 8,
			'channels' => 3,
			'mime' => 'image/jpeg',
		), $image );
	}
}
