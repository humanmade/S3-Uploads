<?php

namespace WebPConvert\Convert\Exceptions\ConversionFailed;

use WebPConvert\Convert\Exceptions\ConversionFailedException;

class InvalidInputException extends ConversionFailedException
{
    public $description = 'Invalid input';
}
