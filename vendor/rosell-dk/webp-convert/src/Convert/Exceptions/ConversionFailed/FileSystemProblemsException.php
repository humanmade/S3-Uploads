<?php

namespace WebPConvert\Convert\Exceptions\ConversionFailed;

use WebPConvert\Convert\Exceptions\ConversionFailedException;

class FileSystemProblemsException extends ConversionFailedException
{
    public $description = 'Filesystem problems';
}
