<?php

class S3_Uploads_UploadSyncBuilder extends Aws\S3\Sync\UploadSyncBuilder {

	public function __construct( $is_dry_run = false ) {
		$this->dry_run = $is_dry_run;
	}

	/**
	 * Builds a UploadSync or DownloadSync object
	 *
	 * @return AbstractSync
	 */
	public function build() {
		$this->validateRequirements();
		$this->sourceConverter = $this->sourceConverter ?: $this->getDefaultSourceConverter();
		$this->targetConverter = $this->targetConverter ?: $this->getDefaultTargetConverter();

		// Only wrap the source iterator in a changed files iterator if we are not forcing the transfers
		if ( ! $this->forcing ) {
			$this->sourceIterator->rewind();
			$this->sourceIterator = new S3_Uploads_ChangedFilesIterator(
				new \NoRewindIterator( $this->sourceIterator ),
				$this->getTargetIterator(),
				$this->sourceConverter,
				$this->targetConverter
			);
			$this->sourceIterator->dry_run = $this->dry_run;
			$this->sourceIterator->rewind();
		}

		$sync = $this->specificBuild();

		if ( $this->params ) {
			$this->addCustomParamListener( $sync );
		}

		if ( $this->debug ) {
			$this->addDebugListener( $sync, is_bool( $this->debug ) ? STDOUT : $this->debug );
		}

		return $sync;
	}
}
