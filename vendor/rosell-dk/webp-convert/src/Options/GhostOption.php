<?php

namespace WebPConvert\Options;

use WebPConvert\Options\Option;
use WebPConvert\Options\Exceptions\InvalidOptionValueException;

/**
 * Ghost option
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class GhostOption extends Option
{

    public function getValueForPrint()
    {
        return '(not defined for this converter)';
    }
}
