<?php

class S3_Uploads_ChangedFilesIterator extends Aws\S3\Sync\ChangedFilesIterator {

	public function accept() {

		$current = $this->current();

		$key = $this->sourceConverter->convert((string) $current);
		if (!($data = $this->getTargetData($key))) {
			return true;
		}

		// Ensure it hasn't been modified since the mtime
		return $current->getMTime() > $data[1];
	}
}