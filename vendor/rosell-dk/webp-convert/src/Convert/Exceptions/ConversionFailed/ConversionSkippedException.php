<?php

namespace WebPConvert\Convert\Exceptions\ConversionFailed;

use WebPConvert\Convert\Exceptions\ConversionFailedException;

class ConversionSkippedException extends ConversionFailedException
{
    public $description = 'The converter declined converting';
}
