<?php
/**
 * Connector to log Stream S3 Uploads events.
 *
 * @package S3_Uploads\Audit_Logging\Stream
 */

namespace S3_Uploads\Audit_Logging\Stream;

class Connector extends \WP_Stream\Connector {
	/**
	 * "Set" action slug.
	 */
	const ACTION_SET = 'set';

	/**
	 * "ACL" context slug.
	 */
	const CONTEXT_ACL = 'acl';

	/**
	 * Connector slug.
	 *
	 * @var string
	 */
	public $name = 's3-uploads';

	/**
	 * Actions registered for this connector.
	 *
	 * These are actions (WordPress hooks) that the connector will listen to.
	 * Whenever an action from this list is triggered, Stream will run a callback
	 * function defined in the connector class.
	 *
	 * The callback function names follow the format: `callback_{action_name}`.
	 *
	 * @var array
	 */
	public $actions = [
		's3_uploads_set_attachment_files_acl',
	];

	/**
	 * Return translated connector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'S3 Uploads', 's3-uploads' );
	}

	/**
	 * Return translated context labels.
	 *
	 * @return array
	 */
	public function get_context_labels() {
		return [
			self::CONTEXT_ACL => __( 'ACL', 's3-uploads' ),
		];
	}

	/**
	 * Return translated action labels.
	 *
	 * @return array
	 */
	public function get_action_labels() {
		return [
			self::ACTION_SET => __( 'Set', 's3-uploads' ),
		];
	}

	/**
	 * Track `s3_uploads_set_attachment_files_acl` action and log Stream ACL sets.
	 *
	 * @param int $attachment_id Attachment whose ACL has been changed.
	 * @param string $acl The new ACL that's been set.
	 *
	 * @return void
	 */
	public function callback_s3_uploads_set_attachment_files_acl( int $attachment_id, string $acl ): void {
		$this->log(
			__( 'ACL of files for attachment "%1$d" was set to "%2$s"', 's3-uploads' ),
			// This array will be compacted and saved as Stream meta.
			[
				'attachment_id' => $attachment_id,
				'acl' => $acl,
			],
			$attachment_id,
			self::CONTEXT_ACL,
			self::ACTION_SET,
		);
	}
}
