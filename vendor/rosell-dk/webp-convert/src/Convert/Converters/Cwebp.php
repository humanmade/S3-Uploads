<?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Converters\BaseTraits\WarningLoggerTrait;
use WebPConvert\Convert\Converters\ConverterTraits\EncodingAutoTrait;
use WebPConvert\Convert\Converters\ConverterTraits\ExecTrait;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
use WebPConvert\Helpers\BinaryDiscovery;
use WebPConvert\Options\BooleanOption;
use WebPConvert\Options\SensitiveStringOption;
use WebPConvert\Options\StringOption;

/**
 * Convert images to webp by calling cwebp binary.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Cwebp extends AbstractConverter
{

    use EncodingAutoTrait;
    use ExecTrait;

    protected function getUnsupportedDefaultOptions()
    {
        return [];
    }

    protected function createOptions()
    {
        parent::createOptions();

        $this->options2->addOptions(
            new StringOption('command-line-options', ''),
            new SensitiveStringOption('rel-path-to-precompiled-binaries', './Binaries'),
            new BooleanOption('try-cwebp', true),
            new BooleanOption('try-common-system-paths', true),
            new BooleanOption('try-discovering-cwebp', true),
            new BooleanOption('try-supplied-binary-for-os', true)
        );
    }

    // OS-specific binaries included in this library, along with hashes
    // If other binaries are going to be added, notice that the first argument is what PHP_OS returns.
    // (possible values, see here: https://stackoverflow.com/questions/738823/possible-values-for-php-os)
    // Got the precompiled binaries here: https://developers.google.com/speed/webp/docs/precompiled
    // Note when changing binaries:
    // 1: Do NOT use "." in filename. It causes unzipping to fail on some hosts
    // 2: Set permission to 775. 755 causes unzipping to fail on some hosts
    private static $suppliedBinariesInfo = [
        'WINNT' => [
            ['cwebp-103-windows-x64.exe', 'b3aaab03ca587e887f11f6ae612293d034ee04f4f7f6bc7a175321bb47a10169'],
        ],
        'Darwin' => [
            ['cwebp-103-mac-10_14', '7332ed5f0d4091e2379b1eaa32a764f8c0d51b7926996a1dc8b4ef4e3c441a12'],
        ],
        'SunOS' => [
            // Got this from ewww Wordpress plugin, which unfortunately still uses the old 0.6.0 versions
            // Can you help me get a 1.0.3 version?
            ['cwebp-060-solaris', '1febaffbb18e52dc2c524cda9eefd00c6db95bc388732868999c0f48deb73b4f']
        ],
        'FreeBSD' => [
            // Got this from ewww Wordpress plugin, which unfortunately still uses the old 0.6.0 versions
            // Can you help me get a 1.0.3 version?
            ['cwebp-060-fbsd', 'e5cbea11c97fadffe221fdf57c093c19af2737e4bbd2cb3cd5e908de64286573']
        ],
        'Linux' => [
            // Dynamically linked executable.
            // It seems it is slightly faster than the statically linked
            ['cwebp-103-linux-x86-64', 'a663215a46d347f63e1ca641c18527a1ae7a2c9a0ae85ca966a97477ea13dfe0'],

            // Statically linked executable
            // It may be that it on some systems works, where the dynamically linked does not (see #196)
            ['cwebp-103-linux-x86-64-static', 'ab96f01b49336da8b976c498528080ff614112d5985da69943b48e0cb1c5228a'],

            // Old executable for systems in case both of the above fails
            ['cwebp-061-linux-x86-64', '916623e5e9183237c851374d969aebdb96e0edc0692ab7937b95ea67dc3b2568'],
        ]
    ];

    /**
     *  Check all hashes of the precompiled binaries.
     *
     *  This isn't used when converting, but can be used as a startup check.
     */
    public function checkAllHashes()
    {
        foreach (self::$suppliedBinariesInfo as $os => $arr) {
            foreach ($arr as $i => list($filename, $hash)) {
                if ($hash != hash_file("sha256", __DIR__ . '/Binaries/' . $filename)) {
                    throw new \Exception('Hash for ' . $filename . ' is incorrect!');
                }
            }
        }
    }

    public function checkOperationality()
    {
        $this->checkOperationalityExecTrait();

        $options = $this->options;
        if (!$options['try-supplied-binary-for-os'] &&
            !$options['try-common-system-paths'] &&
            !$options['try-cwebp'] &&
            !$options['try-discovering-cwebp']
        ) {
            throw new ConverterNotOperationalException(
                'Configured to neither try pure cwebp command, ' .
                'nor look for cweb binaries in common system locations and ' .
                'nor to use one of the supplied precompiled binaries. ' .
                'But these are the only ways this converter can convert images. No conversion can be made!'
            );
        }
    }

    private function executeBinary($binary, $commandOptions, $useNice)
    {
        //$version = $this->detectVersion($binary);

        $command = ($useNice ? 'nice ' : '') . $binary . ' ' . $commandOptions . ' 2>&1';

        //$logger->logLn('command options:' . $commandOptions);
        $this->logLn('Trying to convert by executing the following command:');
        $this->logLn($command);
        exec($command, $output, $returnCode);
        $this->logExecOutput($output);
        /*
        if ($returnCode == 255) {
            if (isset($output[0])) {
                // Could be an error like 'Error! Cannot open output file' or 'Error! ...preset... '
                $this->logLn(print_r($output[0], true));
            }
        }*/
        //$logger->logLn(self::msgForExitCode($returnCode));
        return intval($returnCode);
    }

    /**
     *  Use "escapeshellarg()" on all arguments in a commandline string of options
     *
     *  For example, passing '-sharpness 5 -crop 10 10 40 40 -low_memory' will result in:
     *  [
     *    "-sharpness '5'"
     *    "-crop '10' '10' '40' '40'"
     *    "-low_memory"
     *  ]
     * @param  string $commandLineOptions  string which can contain multiple commandline options
     * @return array  Array of command options
     */
    private static function escapeShellArgOnCommandLineOptions($commandLineOptions)
    {
        if (!ctype_print($commandLineOptions)) {
            throw new ConversionFailedException(
                'Non-printable characters are not allowed in the extra command line options'
            );
        }

        if (preg_match('#[^a-zA-Z0-9_\s\-]#', $commandLineOptions)) {
            throw new ConversionFailedException('The extra command line options contains inacceptable characters');
        }

        $cmdOptions = [];
        $arr = explode(' -', ' ' . $commandLineOptions);
        foreach ($arr as $cmdOption) {
            $pos = strpos($cmdOption, ' ');
            $cName = '';
            if (!$pos) {
                $cName = $cmdOption;
                if ($cName == '') {
                    continue;
                }
                $cmdOptions[] = '-' . $cName;
            } else {
                $cName = substr($cmdOption, 0, $pos);
                $cValues = substr($cmdOption, $pos + 1);
                $cValuesArr = explode(' ', $cValues);
                foreach ($cValuesArr as &$cArg) {
                    $cArg = escapeshellarg($cArg);
                }
                $cValues = implode(' ', $cValuesArr);
                $cmdOptions[] = '-' . $cName . ' ' . $cValues;
            }
        }
        return $cmdOptions;
    }

    /**
     * Build command line options for a given version of cwebp.
     *
     * The "-near_lossless" param is not supported on older versions of cwebp, so skip on those.
     *
     * @param  string $version  Version of cwebp (ie "1.0.3")
     * @return string
     */
    private function createCommandLineOptions($version)
    {

        $this->logLn('Creating command line options for version: ' . $version);

        // we only need two decimal places for version.
        // convert to number to make it easier to compare
        $version = preg_match('#^\d+\.\d+#', $version, $matches);
        $versionNum = 0;
        if (isset($matches[0])) {
            $versionNum = floatval($matches[0]);
        } else {
            $this->logLn(
                'Could not extract version number from the following version string: ' . $version,
                'bold'
            );
        }

        //$this->logLn('version:' . strval($versionNum));

        $options = $this->options;

        $cmdOptions = [];

        // Metadata (all, exif, icc, xmp or none (default))
        // Comma-separated list of existing metadata to copy from input to output
        if ($versionNum >= 0.3) {
            $cmdOptions[] = '-metadata ' . $options['metadata'];
        }

        // preset. Appears first in the list as recommended in the docs
        if (!is_null($options['preset'])) {
            if ($options['preset'] != 'none') {
                $cmdOptions[] = '-preset ' . $options['preset'];
            }
        }

        // Size
        $addedSizeOption = false;
        if (!is_null($options['size-in-percentage'])) {
            $sizeSource = filesize($this->source);
            if ($sizeSource !== false) {
                $targetSize = floor($sizeSource * $options['size-in-percentage'] / 100);
                $cmdOptions[] = '-size ' . $targetSize;
                $addedSizeOption = true;
            }
        }

        // quality
        if (!$addedSizeOption) {
            $cmdOptions[] = '-q ' . $this->getCalculatedQuality();
        }

        // alpha-quality
        if ($this->options['alpha-quality'] !== 100) {
            $cmdOptions[] = '-alpha_q ' . escapeshellarg($this->options['alpha-quality']);
        }

        // Losless PNG conversion
        if ($options['encoding'] == 'lossless') {
            // No need to add -lossless when near-lossless is used (on version >= 0.5)
            if (($options['near-lossless'] === 100) || ($versionNum < 0.5)) {
                $cmdOptions[] = '-lossless';
            }
        }

        // Near-lossles
        if ($options['near-lossless'] !== 100) {
            if ($versionNum < 0.5) {
                $this->logLn(
                    'The near-lossless option is not supported on this (rather old) version of cwebp' .
                        '- skipping it.',
                    'italic'
                );
            } else {
                // We only let near_lossless have effect when encoding is set to "lossless"
                // otherwise encoding=auto would not work as expected

                if ($options['encoding'] == 'lossless') {
                    $cmdOptions[] = '-near_lossless ' . $options['near-lossless'];
                } else {
                    $this->logLn(
                        'The near-lossless option ignored for lossy'
                    );
                }
            }
        }

        if ($options['auto-filter'] === true) {
            $cmdOptions[] = '-af';
        }

        // Built-in method option
        $cmdOptions[] = '-m ' . strval($options['method']);

        // Built-in low memory option
        if ($options['low-memory']) {
            $cmdOptions[] = '-low_memory';
        }

        // command-line-options
        if ($options['command-line-options']) {
            /*
            In some years, we can use the splat instead (requires PHP 5.6)
            array_push(
                $cmdOptions,
                ...self::escapeShellArgOnCommandLineOptions($options['command-line-options'])
            );
            */
            foreach (self::escapeShellArgOnCommandLineOptions($options['command-line-options']) as $cmdLineOption) {
                array_push($cmdOptions, $cmdLineOption);
            }
        }

        // Source file
        $cmdOptions[] = escapeshellarg($this->source);

        // Output
        $cmdOptions[] = '-o ' . escapeshellarg($this->destination);

        // Redirect stderr to same place as stdout
        // https://www.brianstorti.com/understanding-shell-script-idiom-redirect/
        $cmdOptions[] = '2>&1';

        $commandOptions = implode(' ', $cmdOptions);
        //$this->logLn('command line options:' . $commandOptions);

        return $commandOptions;
    }

    /**
     *  Get path for supplied binary for current OS - and validate hash.
     *
     *  @return  array  Array of supplied binaries (which actually exists, and where hash validates)
     */
    private function getSuppliedBinaryPathForOS()
    {
        $this->log('Checking if we have a supplied precompiled binary for your OS (' . PHP_OS . ')... ');

        // Try supplied binary (if available for OS, and hash is correct)
        $options = $this->options;
        if (!isset(self::$suppliedBinariesInfo[PHP_OS])) {
            $this->logLn('No we dont - not for that OS');
            return [];
        }

        $result = [];
        $files = self::$suppliedBinariesInfo[PHP_OS];
        if (count($files) == 1) {
            $this->logLn('We do.');
        } else {
            $this->logLn('We do. We in fact have ' . count($files));
        }

        foreach ($files as $i => list($file, $hash)) {
            //$file = $info[0];
            //$hash = $info[1];

            $binaryFile = __DIR__ . '/' . $options['rel-path-to-precompiled-binaries'] . '/' . $file;

            // Replace "/./" with "/" in path (we could alternatively use realpath)
            //$binaryFile = preg_replace('#\/\.\/#', '/', $binaryFile);
            // The file should exist, but may have been removed manually.
            /*
            if (!file_exists($binaryFile)) {
                $this->logLn('Supplied binary not found! It ought to be here:' . $binaryFile, 'italic');
                return false;
            }*/

            $realPathResult = realpath($binaryFile);
            if ($realPathResult === false) {
                $this->logLn('Supplied binary not found! It ought to be here:' . $binaryFile, 'italic');
                continue;
            }
            $binaryFile = $realPathResult;

            // File exists, now generate its hash
            // hash_file() is normally available, but it is not always
            // - https://stackoverflow.com/questions/17382712/php-5-3-20-undefined-function-hash
            // If available, validate that hash is correct.

            if (function_exists('hash_file')) {
                $binaryHash = hash_file('sha256', $binaryFile);

                if ($binaryHash != $hash) {
                    $this->logLn(
                        'Binary checksum of supplied binary is invalid! ' .
                        'Did you transfer with FTP, but not in binary mode? ' .
                        'File:' . $binaryFile . '. ' .
                        'Expected checksum: ' . $hash . '. ' .
                        'Actual checksum:' . $binaryHash . '.',
                        'bold'
                    );
                    continue;
                }
            }
            $result[] = $binaryFile;
        }
        return $result;
    }

    private function who()
    {
        exec('whoami 2>&1', $whoOutput, $whoReturnCode);
        if (($whoReturnCode == 0) && (isset($whoOutput[0]))) {
            return 'user: "' . $whoOutput[0] . '"';
        } else {
            return 'the user that the command was run with';
        }
    }

    /**
     *
     * @return  string|int  Version string (ie "1.0.2") OR return code, in case of failure
     */
    private function detectVersion($binary)
    {
        $command = $binary . ' -version 2>&1';
        $this->log('- Executing: ' . $command);
        exec($command, $output, $returnCode);

        if ($returnCode == 0) {
            if (isset($output[0])) {
                $this->logLn('. Result: version: *' . $output[0] . '*');
                return $output[0];
            }
        } else {
            $this->log('. Result: ');
            if ($returnCode == 127) {
                $this->logLn('*Exec failed* (the cwebp binary was not found at path: ' . $binary . ')');
            } else {
                if ($returnCode == 126) {
                    $this->logLn(
                        '*Exec failed*. ' .
                        'Permission denied (' . $this->who() . ' does not have permission to execute that binary)'
                    );
                } else {
                    $this->logLn(
                        '*Exec failed* (return code: ' . $returnCode . ')'
                    );
                    $this->logExecOutput($output);
                }
            }
            return $returnCode;
        }
        return ''; // Will not happen. Just so phpstan doesn't complain
    }

    /**
     *  Check versions for an array of binaries.
     *
     *  @return  array  the "detected" key holds working binaries and their version numbers, the
     *                  the "failed" key holds failed binaries and their error codes.
     */
    private function detectVersions($binaries)
    {
        $binariesWithVersions = [];
        $binariesWithFailCodes = [];

        foreach ($binaries as $binary) {
            $versionStringOrFailCode = $this->detectVersion($binary);
        //    $this->logLn($binary . ': ' . $versionString);
            if (gettype($versionStringOrFailCode) == 'string') {
                $binariesWithVersions[$binary] = $versionStringOrFailCode;
            } else {
                $binariesWithFailCodes[$binary] = $versionStringOrFailCode;
            }
        }
        return ['detected' => $binariesWithVersions, 'failed' => $binariesWithFailCodes];
    }

    private function logBinariesFound($binaries)
    {
        if (count($binaries) == 0) {
            $this->logLn('Found 0 binaries');
        } else {
            $this->logLn('Found ' . count($binaries) . ' binaries: ');
            foreach ($binaries as $binary) {
                $this->logLn('- ' . $binary);
            }
        }
    }

    private function logDiscoverAction($optionName, $description)
    {
        if ($this->options[$optionName]) {
            $this->logLn(
                'Discovering binaries ' . $description . ' ' .
                '(to skip this step, disable the "' . $optionName . '" option)'
            );
        } else {
            $this->logLn(
                'Skipped discovering binaries ' . $description . ' ' .
                '(enable "' . $optionName . '" if you do not want to skip that step)'
            );
        }
    }

    private function discoverCwebpBinaries()
    {
        $this->logLn(
            'Looking for cwebp binaries.'
        );
        $binaries = [];

        if (defined('WEBPCONVERT_CWEBP_PATH')) {
            $this->logLn('WEBPCONVERT_CWEBP_PATH was defined, so using that path and ignoring any other');
            return [constant('WEBPCONVERT_CWEBP_PATH')];
        }
        if (!empty(getenv('WEBPCONVERT_CWEBP_PATH'))) {
            $this->logLn(
                'WEBPCONVERT_CWEBP_PATH environment variable was set, so using that path and ignoring any other'
            );
            return [getenv('WEBPCONVERT_CWEBP_PATH')];
        }

        if ($this->options['try-cwebp']) {
            $this->logLn(
                'Discovering if a plain cwebp call works (to skip this step, disable the "try-cwebp" option)'
            );
            $result = $this->detectVersion('cwebp');
            if (gettype($result) == 'string') {
                $this->logLn('We could get the version, so yes, a plain cwebp call works');
                $binaries[] = 'cwebp';
            } else {
                $this->logLn('Nope a plain cwebp call does not work');
            }
        } else {
            $this->logLn(
                'Skipped discovering if a plain cwebp call works' .
                ' (enable the "try-cwebp" option if you do not want to skip that step)'
            );
        }

        // try-discovering-cwebp
        $this->logDiscoverAction('try-discovering-cwebp', 'using "which -a cwebp" command.');
        if ($this->options['try-discovering-cwebp']) {
            $moreBinaries = BinaryDiscovery::discoverInstalledBinaries('cwebp');
            $this->logBinariesFound($moreBinaries);
            $binaries = array_merge($binaries, $moreBinaries);
        }

        // 'try-common-system-paths'
        $this->logDiscoverAction('try-common-system-paths', 'by peeking in common system paths');
        if ($this->options['try-common-system-paths']) {
            $moreBinaries = BinaryDiscovery::discoverInCommonSystemPaths('cwebp');
            $this->logBinariesFound($moreBinaries);
            $binaries = array_merge($binaries, $moreBinaries);
        }

        // try-supplied-binary-for-os
        $this->logDiscoverAction('try-supplied-binary-for-os', 'which are distributed with the webp-convert library');
        if ($this->options['try-supplied-binary-for-os']) {
            $moreBinaries = $this->getSuppliedBinaryPathForOS();
            $this->logBinariesFound($moreBinaries);
            $binaries = array_merge($binaries, $moreBinaries);
        }

        return array_values(array_unique($binaries));
    }

    /**
     * Try executing a cwebp binary (or command, like: "cwebp")
     *
     * @param  string  $binary
     * @param  string  $version  Version of cwebp (ie "1.0.3")
     * @param  boolean $useNice  Whether to use "nice" command or not
     *
     * @return boolean  success or not.
     */
    private function tryCwebpBinary($binary, $version, $useNice)
    {

        //$this->logLn('Trying binary: ' . $binary);
        $commandOptions = $this->createCommandLineOptions($version);

        $returnCode = $this->executeBinary($binary, $commandOptions, $useNice);
        if ($returnCode == 0) {
            // It has happened that even with return code 0, there was no file at destination.
            if (!file_exists($this->destination)) {
                $this->logLn('executing cweb returned success code - but no file was found at destination!');
                return false;
            } else {
                $this->logLn('Success');
                return true;
            }
        } else {
            $this->logLn(
                'Exec failed (return code: ' . $returnCode . ')'
            );
            return false;
        }
    }

    /**
     *  Helper for composing an error message when no converters are working.
     *
     *  @param  array  $versions  The array which we get from calling ::detectVersions($binaries)
     *  @return string  An informative and to the point error message.
     */
    private function composeMeaningfullErrorMessageNoVersionsWorking($versions)
    {

        // PS: array_values() is used to reindex
        $uniqueFailCodes = array_values(array_unique(array_values($versions['failed'])));
        $justOne = (count($versions['failed']) == 1);

        if (count($uniqueFailCodes) == 1) {
            if ($uniqueFailCodes[0] == 127) {
                return 'No cwebp binaries located. Check the conversion log for details.';
            }
        }
        // If there are more failures than 127, the 127 failures are unintesting.
        // It is to be expected that some of the common system paths does not contain a cwebp.
        $uniqueFailCodesBesides127 = array_values(array_diff($uniqueFailCodes, [127]));

        if (count($uniqueFailCodesBesides127) == 1) {
            if ($uniqueFailCodesBesides127[0] == 126) {
                return 'No cwebp binaries could be executed (permission denied for ' . $this->who() . ').';
            }
        }

        $errorMsg = '';
        if ($justOne) {
            $errorMsg .= 'The cwebp file found cannot be can be executed ';
        } else {
            $errorMsg .= 'None of the cwebp files can be executed ';
        }
        if (count($uniqueFailCodesBesides127) == 1) {
            $errorMsg .= '(failure code: ' . $uniqueFailCodesBesides127[0] . ')';
        } else {
            $errorMsg .= '(failure codes: ' . implode(', ', $uniqueFailCodesBesides127) . ')';
        }
        return $errorMsg;
    }

    protected function doActualConvert()
    {
        $binaries = $this->discoverCwebpBinaries();
        if (count($binaries) == 0) {
            $this->logLn('No cwebp binaries found!');

            $discoverOptions = [
                'try-supplied-binary-for-os',
                'try-common-system-paths',
                'try-cwebp',
                'try-discovering-cwebp'
            ];
            $disabledDiscoverOptions = [];
            foreach ($discoverOptions as $discoverOption) {
                if (!$this->options[$discoverOption]) {
                    $disabledDiscoverOptions[] = $discoverOption;
                }
            }
            if (count($disabledDiscoverOptions) == 0) {
                throw new SystemRequirementsNotMetException(
                    'No cwebp binaries found.'
                );
            } else {
                throw new SystemRequirementsNotMetException(
                    'No cwebp binaries found. Try enabling the "' .
                    implode('" option or the "', $disabledDiscoverOptions) . '" option.'
                );
            }
        }
        $this->logLn(
            'Detecting versions of the cwebp binaries found'
        );
        $versions = $this->detectVersions($binaries);

        $binaryVersions = $versions['detected'];
        if (count($binaryVersions) == 0) {
            // No working cwebp binaries found.

            throw new SystemRequirementsNotMetException(
                $this->composeMeaningfullErrorMessageNoVersionsWorking($versions)
            );
        }

        // Sort binaries so those with highest numbers comes first
        arsort($binaryVersions);
        $this->logLn(
            'Binaries ordered by version number.'
        );
        foreach ($binaryVersions as $binary => $version) {
            $this->logLn('- ' . $binary . ': (version: ' . $version . ')');
        }

        // Execute!
        $this->logLn(
            'Trying the first of these. If that should fail (it should not), the next will be tried and so on.'
        );
        $useNice = (($this->options['use-nice']) && self::hasNiceSupport());
        $success = false;
        foreach ($binaryVersions as $binary => $version) {
            if ($this->tryCwebpBinary($binary, $version, $useNice)) {
                $success = true;
                break;
            }
        }

        // cwebp sets file permissions to 664 but instead ..
        // .. $this->source file permissions should be used

        if ($success) {
            $fileStatistics = stat($this->source);
            if ($fileStatistics !== false) {
                // Apply same permissions as source file, but strip off the executable bits
                $permissions = $fileStatistics['mode'] & 0000666;
                chmod($this->destination, $permissions);
            }
        } else {
            throw new SystemRequirementsNotMetException('Failed converting. Check the conversion log for details.');
        }
    }
}
