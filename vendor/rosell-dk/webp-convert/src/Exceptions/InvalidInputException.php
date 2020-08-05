<?php

namespace WebPConvert\Exceptions;

use WebPConvert\Exceptions\WebPConvertException;

class InvalidInputException extends WebPConvertException
{
    public $description = 'Invalid input';
}
