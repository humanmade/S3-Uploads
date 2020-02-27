<?php

namespace WebPConvert\Options;

use WebPConvert\Options\Option;
use WebPConvert\Options\Exceptions\InvalidOptionValueException;

/**
 * Abstract option class
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class ArrayOption extends Option
{

    public function check()
    {
        $this->checkType('array');
    }

    public function getValueForPrint()
    {
        if (count($this->getValue()) == 0) {
            return '(empty array)';
        } else {
            return parent::getValueForPrint();
        }
    }
}
