<?php

namespace WebPConvert\Options;

use WebPConvert\Options\StringOption;
use WebPConvert\Options\Exceptions\InvalidOptionValueException;

/**
 * Metadata option. A Comma-separated list ('all', 'none', 'exif', 'icc', 'xmp')
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class MetadataOption extends StringOption
{

    public function __construct($id, $defaultValue)
    {
        parent::__construct($id, $defaultValue);
    }

    public function check()
    {
        parent::check();

        $value = $this->getValue();

        if (($value == 'all') || ($value == 'none')) {
            return;
        }

        foreach (explode(',', $value) as $item) {
            if (!in_array($value, ['exif', 'icc', 'xmp'])) {
                throw new InvalidOptionValueException(
                    '"metadata" option must be "all", "none" or a comma-separated list of "exif", "icc" or "xmp". ' .
                    'It was however set to: "' . $value . '"'
                );
            }
        }

        //$this->checkType('string');
    }
}
