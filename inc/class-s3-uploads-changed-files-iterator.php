<?php

class S3_Uploads_ChangedFilesIterator extends Aws\S3\Sync\ChangedFilesIterator {

	public $dry_run = false;
	public function accept() {

		$current = $this->current();

		$key = $this->sourceConverter->convert((string) $current);
		if (!($data = $this->getTargetData($key))) {
			return true;
		}

		if ( ! empty( $this->dry_run ) && defined( 'WP_CLI' ) ) {
			if ( $current->getMTime() > $data[1] ) {
				WP_CLI::line( "(dry-run) Uploading {$current->getPathname()} ({$data[0]} bytes)" );
			}
			return false;			
		}
		// Ensure it hasn't been modified since the mtime
		return $current->getMTime() > $data[1];
	}
}