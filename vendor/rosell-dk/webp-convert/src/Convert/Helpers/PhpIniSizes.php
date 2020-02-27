<?php

namespace WebPConvert\Convert\Helpers;

/**
 * Get/parse shorthandsize strings from php.ini as bytes.
 *
 * Parse strings like "1k" into bytes (1024).
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class PhpIniSizes
{

    /**
     * Parse a shordhandsize string as the ones returned by ini_get()
     *
     * Parse a shorthandsize string having the syntax allowed in php.ini and returned by ini_get().
     * Ie "1K" => 1024.
     * Strings without units are also accepted.
     * The shorthandbytes syntax is described here: https://www.php.net/manual/en/faq.using.php#faq.using.shorthandbytes
     *
     * @param  string  $shortHandSize  A size string of the type returned by ini_get()
     * @return float|false  The parsed size (beware: it is float, do not check high numbers for equality),
     *                      or false if parse error
     */
    public static function parseShortHandSize($shortHandSize)
    {

        $result = preg_match("#^\\s*(\\d+(?:\\.\\d+)?)([bkmgtpezy]?)\\s*$#i", $shortHandSize, $matches);
        if ($result !== 1) {
            return false;
        }

        // Truncate, because that is what php does.
        $digitsValue = floor($matches[1]);

        if ((count($matches) >= 3) && ($matches[2] != '')) {
            $unit = $matches[2];

            // Find the position of the unit in the ordered string which is the power
            // of magnitude to multiply a kilobyte by.
            $position = stripos('bkmgtpezy', $unit);

            return floatval($digitsValue * pow(1024, $position));
        } else {
            return $digitsValue;
        }
    }

    /*
    * Get the size of an php.ini option.
    *
    * Calls ini_get() and parses the size to a number.
    * If the configuration option is null, does not exist, or cannot be parsed as a shorthandsize, false is returned
    *
    * @param  string  $varname  The configuration option name.
    * @return float|false  The parsed size or false if the configuration option does not exist
    */
    public static function getIniBytes($iniVarName)
    {
        $iniVarValue = ini_get($iniVarName);
        if (($iniVarValue == '') || $iniVarValue === false) {
            return false;
        }
        return self::parseShortHandSize($iniVarValue);
    }
}
