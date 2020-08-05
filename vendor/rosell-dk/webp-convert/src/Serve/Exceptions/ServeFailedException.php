<?php

namespace WebPConvert\Serve\Exceptions;

use WebPConvert\Exceptions\WebPConvertException;

class ServeFailedException extends WebPConvertException
{
    public $description = 'Failed serving';
}
