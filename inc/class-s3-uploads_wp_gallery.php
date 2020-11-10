<?php

class S3_Uploads_WP_Gallery {


	public function __construct() {
		add_filter( 'manage_media_columns', array( $this, 'next_manage_media_columns' ) );
		add_filter( 'manage_media_custom_column', array( $this, 'next_manage_media_custom_column' ), 10, 2 );
		add_action( 'admin_head', array( $this, 'my_action_javascript' ) );

	}

	public function next_manage_media_columns( $cols ) {
		$cols['s3_actions'] = __( 'S3 Actions', 's3-upload' );
		return $cols;
	}

	public function next_manage_media_custom_column( $column_name, $post_id ) {
		if ( $column_name === 's3_actions' ) {
			$this->next_get_buttons( $post_id );
		}
	}

	public function next_get_buttons( $post_id ) {
		$s3_all_image_uploaded = get_post_meta( $post_id, '_s3_all_image_uploaded', true );
		$s3_is_image           = get_post_meta( $post_id, '_s3_is_image' );
		if ( empty( $s3_is_image ) ) {
			$wp_attached_file = get_post_meta( $post_id, '_wp_attached_file', true );
			$s3_is_image      = $this->is_image( $wp_attached_file );
			update_post_meta( $post_id, '_s3_is_image', $s3_is_image );
		} else {
			$s3_is_image = $s3_is_image[0];
		}
		if ( ! empty( $s3_all_image_uploaded ) && $s3_is_image ) {
			echo '<p class="next-reoptimize" style="color:green" data-post_id="' . $post_id . '">This Image Fully Optimize</button>';
		} else {
			if ( $s3_is_image ) {
				echo '<button class="next-upload" data-post_id="' . $post_id . '">Upload & Optimize</button>';
			} else {
				if ( ! $s3_all_image_uploaded ) {
					echo '<button class="next-upload" data-post_id="' . $post_id . '">Upload</button>';
				}
			}
		}
	}

	function my_action_javascript() {
		?>
		<script type="text/javascript" >
			jQuery(document).ready(function($) {
				$('.next-upload').click(function(e){
					e.preventDefault();
					var data_post_id = $(this).data('post_id');
					var data = {
						action: 'next_upload',
						post_id: data_post_id
					};
					$.post(ajaxurl, data, function(response) {

					});
				});
			});
		</script>
		<?php
	}
	public function is_image( $file_name ) {
		$image_extensions = array( 'jpg', 'jpeg', 'jpe', 'png', 'gif' );
		$ext              = pathinfo( $file_name, PATHINFO_EXTENSION );
		if ( in_array( $ext, $image_extensions ) ) {
			return true;
		}
		return false;
	}

}
