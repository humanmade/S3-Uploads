<?php

namespace WebPConvert\Helpers;

class Sanitize
{

    /**
     *  The NUL character is a demon, because it can be used to bypass other tests
     *  See https://st-g.de/2011/04/doing-filename-checks-securely-in-PHP.
     *
     *  @param  string  $string  string remove NUL characters in
     */
    public static function removeNUL($string)
    {
        return str_replace(chr(0), '', $string);
    }

    public static function removeStreamWrappers($string)
    {
        return preg_replace('#^\\w+://#', '', $string);
    }

    public static function path($string)
    {
        $string = self::removeNUL($string);
        $string = self::removeStreamWrappers($string);
        return $string;
    }
}
