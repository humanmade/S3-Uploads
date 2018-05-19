<?php

class S3_Uploads_Image_Editor_Imagick extends WP_Image_Editor_Imagick {

	protected $temp_file_to_cleanup = null;

	/**
	 * Imagick by default can't handle s3:// paths
	 * for saving images. We have instead save it to a file file,
	 * then copy it to the s3:// path as a workaround.
	 */
	protected function _save( $image, $filename = null, $mime_type = null ) {
		list( $filename, $extension, $mime_type ) = $this->get_output_format( $filename, $mime_type );

		if ( ! $filename ) {
			$filename = $this->generate_filename( null, null, $extension );
		}

		$upload_dir = wp_upload_dir();

		if ( strpos( $filename, $upload_dir['basedir'] ) === 0 ) {
			$temp_filename = tempnam( get_temp_dir(), 's3-uploads' );
		}

		$save = parent::_save( $image, $temp_filename, $mime_type );

		if ( is_wp_error( $save ) ) {
			unlink( $temp_filename );
			return $save;
		}

		$copy_result = copy( $save['path'], $filename );

		unlink( $save['path'] );
		unlink( $temp_filename );

		if ( ! $copy_result ) {
			return new WP_Error( 'unable-to-copy-to-s3', 'Unable to copy the temp image to S3' );
		}

		return array(
			'path'      => $filename,
			'file'      => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
			'width'     => $this->size['width'],
			'height'    => $this->size['height'],
			'mime-type' => $mime_type,
		);
	}

	public function load() {
		$result = parent::load();

		// `load` can call pdf_setup() which has to copy the file to a temp local copy.
		// In this event we want to clean it up once `load` has been completed.
		if ( $this->temp_file_to_cleanup ) {
			unlink( $this->temp_file_to_cleanup );
			$this->temp_file_to_cleanup = null;
		}
		return $result;
	}

	/**
	 * Sets up Imagick for PDF processing.
	 * Increases rendering DPI and only loads first page.
	 *
	 * @since 4.7.0
	 *
	 * @return string|WP_Error File to load or WP_Error on failure.
	 */
	protected function pdf_setup() {
		$temp_filename = tempnam( get_temp_dir(), 's3-uploads' );
		$this->temp_file_to_cleanup = $temp_filename;
		copy( $this->file, $temp_filename );

		try {
			// By default, PDFs are rendered in a very low resolution.
			// We want the thumbnail to be readable, so increase the rendering DPI.
			$this->image->setResolution( 128, 128 );

			// Only load the first page.
			return $temp_filename . '[0]';
		} catch ( Exception $e ) {
			return new WP_Error( 'pdf_setup_failed', $e->getMessage(), $this->file );
		}
	}
}
