<?php

namespace WebPConvert\Helpers;

use WebPConvert\Helpers\FileExists;

/**
 * Discover multiple paths of a binary
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.3.0
 */
class BinaryDiscovery
{

    // Common system paths
    private static $commonSystemPaths = [
        '/usr/bin',
        '/usr/local/bin',
        '/usr/gnu/bin',
        '/usr/syno/bin'
    ];

    /**
     * Discover binaries by looking in common system paths.
     *
     * We try a small set of common system paths, such as "/usr/bin".
     *
     * @param  string $binary  the binary to look for (ie "cwebp")
     *
     * @return array binaries found in common system locations
     */
    public static function discoverInCommonSystemPaths($binary)
    {
        $binaries = [];
        foreach (self::$commonSystemPaths as $dir) {
            // PS: FileExists might throw if exec() is unavailable. We let it.
            // - this class assumes exec is available
            if (FileExists::fileExistsTryHarder($dir . '/' . $binary)) {
                $binaries[] = $dir . '/' . $binary;
            }
        }
        return $binaries;
    }

    /**
     * Discover installed binaries using ie "whereis -b cwebp"
     *
     * @return array  Array of cwebp paths discovered (possibly empty)
     */
    private static function discoverBinariesUsingWhereIs($binary)
    {
        // This method was added due to #226.
        exec('whereis -b ' . $binary . ' 2>&1', $output, $returnCode);
        if (($returnCode == 0) && (isset($output[0]))) {
            $result = $output[0];
            // Ie: "cwebp: /usr/bin/cwebp /usr/local/bin/cwebp"
            if (preg_match('#^' . $binary . ':\s(.*)$#', $result, $matches)) {
                return explode(' ', $matches[1]);
            }
        }
        return [];
    }

    /**
     * Discover installed binaries using "which -a cwebp"
     *
     * @param  string $binary  the binary to look for (ie "cwebp")
     *
     * @return array  Array of paths discovered (possibly empty)
     */
    private static function discoverBinariesUsingWhich($binary)
    {
        // As suggested by @cantoute here:
        // https://wordpress.org/support/topic/sh-1-usr-local-bin-cwebp-not-found/
        exec('which -a ' . $binary . ' 2>&1', $output, $returnCode);
        if ($returnCode == 0) {
            return $output;
        }
        return [];
    }

    /**
     * Discover binaries using "which -a" or, if that fails "whereis -b"
     *
     * These commands only searces within $PATH. So it only finds installed binaries (which is good,
     * as it would be unsafe to deal with binaries found scattered around)
     *
     * @param  string $binary  the binary to look for (ie "cwebp")
     *
     * @return array binaries found in common system locations
     */
    public static function discoverInstalledBinaries($binary)
    {
        $paths = self::discoverBinariesUsingWhich($binary);
        if (count($paths) > 0) {
            return $paths;
        }

        $paths = self::discoverBinariesUsingWhereIs($binary);
        if (count($paths) > 0) {
            return $paths;
        }
        return [];
    }
}
