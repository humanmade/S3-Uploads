<?php

class Test_S3_Uploads extends WP_UnitTestCase {

	function test_get_s3_bucket_location() {

		$uploads = new S3_Uploads( 'hmn-uploads', S3_UPLOADS_KEY, S3_UPLOADS_SECRET, S3_UPLOADS_REGION );

		$region = $uploads->get_s3_bucket_region();
		$this->assertEquals( 'us-east-1', $region );
	}

	function test_get_s3_bucket() {
		$uploads = new S3_Uploads( 'hmn-uploads/something', S3_UPLOADS_KEY, S3_UPLOADS_SECRET, S3_UPLOADS_REGION );

		$this->assertEquals( 'hmn-uploads', $uploads->get_s3_bucket() );
	}
}