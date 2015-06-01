<?php

class S3_Uploads_Image_Editor_Imagick extends WP_UnitTestCase {

	public function test_s3_upload_image_editor_is_present() {
		$editors = apply_filters( 'wp_image_editors', array( 'WP_Image_Editor_Imagick', 'WP_Image_Editor_GD' ) );

		$this->assertFalse( in_array( 'WP_Image_Editor_Imagick', $editors ), 'Imagick editor should be removed from the image editors array.' );
	}
}