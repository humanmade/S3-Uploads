<?php

namespace WebPConvert\Convert\Converters\BaseTraits;

use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFileException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFolderException;

/**
 * Trait for handling options
 *
 * This trait is currently only used in the AbstractConverter class. It has been extracted into a
 * trait in order to bundle the methods concerning options.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
trait DestinationPreparationTrait
{

    abstract public function getDestination();
    abstract public function logLn($msg, $style = '');

    /**
     * Create writable folder in provided path (if it does not exist already)
     *
     * @throws CreateDestinationFolderException  if folder cannot be removed
     * @return void
     */
    private function createWritableDestinationFolder()
    {
        $destination = $this->getDestination();

        $folder = dirname($destination);
        if (!file_exists($folder)) {
            $this->logLn('Destination folder does not exist. Creating folder: ' . $folder);
            // TODO: what if this is outside open basedir?
            // see http://php.net/manual/en/ini.core.php#ini.open-basedir

            // Trying to create the given folder (recursively)
            if (!mkdir($folder, 0777, true)) {
                throw new CreateDestinationFolderException(
                    'Failed creating folder. Check the permissions!',
                    'Failed creating folder: ' . $folder . '. Check permissions!'
                );
            }
        }
    }

    /**
     * Check that we can write file at destination.
     *
     * It is assumed that the folder already exists (that ::createWritableDestinationFolder() was called first)
     *
     * @throws CreateDestinationFileException  if file cannot be created at destination
     * @return void
     */
    private function checkDestinationWritable()
    {
        $destination = $this->getDestination();
        $dirName = dirname($destination);

        if (@is_writable($dirName) && @is_executable($dirName)) {
            // all is well
            return;
        }

        // The above might fail on Windows, even though dir is writable
        // So, to be absolute sure that we cannot write, we make an actual write test (writing a dummy file)
        // No harm in doing that for non-Windows systems either.
        if (file_put_contents($destination, 'dummy') !== false) {
            // all is well, after all
            unlink($destination);
            return;
        }

        throw new CreateDestinationFileException(
            'Cannot create file: ' . basename($destination) . ' in dir:' . dirname($destination)
        );
    }

    /**
     * Remove existing destination.
     *
     * @throws CreateDestinationFileException  if file cannot be removed
     * @return void
     */
    private function removeExistingDestinationIfExists()
    {
        $destination = $this->getDestination();
        if (file_exists($destination)) {
            // A file already exists in this folder...
            // We delete it, to make way for a new webp
            if (!unlink($destination)) {
                throw new CreateDestinationFileException(
                    'Existing file cannot be removed: ' . basename($destination)
                );
            }
        }
    }
}
