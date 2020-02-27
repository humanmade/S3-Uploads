<?php

namespace WebPConvert\Exceptions\InvalidInput;

use WebPConvert\Exceptions\InvalidInputException;

class TargetNotFoundException extends InvalidInputException
{
    public $description = 'The converter could not locate source file';
}
