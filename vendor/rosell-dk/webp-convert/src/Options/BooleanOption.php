<?php

namespace WebPConvert\Options;

use WebPConvert\Options\Option;
use WebPConvert\Options\Exceptions\InvalidOptionValueException;

/**
 * Boolean option
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class BooleanOption extends Option
{

    public function check()
    {
        $this->checkType('boolean');
    }

    public function getValueForPrint()
    {
        return ($this->getValue() === true ? 'true' : 'false');
    }
}
