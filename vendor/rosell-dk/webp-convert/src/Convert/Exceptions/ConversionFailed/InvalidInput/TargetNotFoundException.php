<?php

namespace WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput;

use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInputException;

class TargetNotFoundException extends InvalidInputException
{
    public $description = 'The converter could not locate source file';
}
