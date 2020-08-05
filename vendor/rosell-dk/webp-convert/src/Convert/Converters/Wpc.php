<?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Converters\ConverterTraits\CloudConverterTrait;
use WebPConvert\Convert\Converters\ConverterTraits\CurlTrait;
use WebPConvert\Convert\Converters\ConverterTraits\EncodingAutoTrait;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\InvalidApiKeyException;
use WebPConvert\Options\BooleanOption;
use WebPConvert\Options\IntegerOption;
use WebPConvert\Options\SensitiveStringOption;

/**
 * Convert images to webp using Wpc (a cloud converter based on WebP Convert).
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Wpc extends AbstractConverter
{
    use CloudConverterTrait;
    use CurlTrait;
    use EncodingAutoTrait;

    protected function getUnsupportedDefaultOptions()
    {
        return [];
    }

    public function supportsLossless()
    {
        return ($this->options['api-version'] >= 2);
    }

    public function passOnEncodingAuto()
    {
        // We could make this configurable. But I guess passing it on is always to be preferred
        // for api >= 2.
        return ($this->options['api-version'] >= 2);
    }

    protected function createOptions()
    {
        parent::createOptions();

        $this->options2->addOptions(
            new SensitiveStringOption('api-key', ''),   /* for communicating with wpc api v.1+ */
            new SensitiveStringOption('secret', ''),    /* for communicating with wpc api v.0 */
            new SensitiveStringOption('api-url', ''),
            new SensitiveStringOption('url', ''),       /* DO NOT USE. Only here to keep the protection */
            new IntegerOption('api-version', 2, 0, 2),
            new BooleanOption('crypt-api-key-in-transfer', false)  /* new in api v.1 */
        );
    }

    private static function createRandomSaltForBlowfish()
    {
        $salt = '';
        $validCharsForSalt = array_merge(
            range('A', 'Z'),
            range('a', 'z'),
            range('0', '9'),
            ['.', '/']
        );

        for ($i = 0; $i < 22; $i++) {
            $salt .= $validCharsForSalt[array_rand($validCharsForSalt)];
        }
        return $salt;
    }

    /**
     * Get api key from options or environment variable
     *
     * @return string  api key or empty string if none is set
     */
    private function getApiKey()
    {
        if ($this->options['api-version'] == 0) {
            if (!empty($this->options['secret'])) {
                return $this->options['secret'];
            }
        } elseif ($this->options['api-version'] >= 1) {
            if (!empty($this->options['api-key'])) {
                return $this->options['api-key'];
            }
        }
        if (defined('WEBPCONVERT_WPC_API_KEY')) {
            return constant('WEBPCONVERT_WPC_API_KEY');
        }
        if (!empty(getenv('WEBPCONVERT_WPC_API_KEY'))) {
            return getenv('WEBPCONVERT_WPC_API_KEY');
        }
        return '';
    }

    /**
     * Get url from options or environment variable
     *
     * @return string  URL to WPC or empty string if none is set
     */
    private function getApiUrl()
    {
        if (!empty($this->options['api-url'])) {
            return $this->options['api-url'];
        }
        if (defined('WEBPCONVERT_WPC_API_URL')) {
            return constant('WEBPCONVERT_WPC_API_URL');
        }
        if (!empty(getenv('WEBPCONVERT_WPC_API_URL'))) {
            return getenv('WEBPCONVERT_WPC_API_URL');
        }
        return '';
    }


    /**
     * Check operationality of Wpc converter.
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met (curl)
     * @throws ConverterNotOperationalException   if key is missing or invalid, or quota has exceeded
     */
    public function checkOperationality()
    {

        $options = $this->options;

        $apiVersion = $options['api-version'];

        if ($this->getApiUrl() == '') {
            if (isset($this->options['url']) && ($this->options['url'] != '')) {
                throw new ConverterNotOperationalException(
                    'The "url" option has been renamed to "api-url" in webp-convert 2.0. ' .
                    'You must change the configuration accordingly.'
                );
            }
            throw new ConverterNotOperationalException(
                'Missing URL. You must install Webp Convert Cloud Service on a server, ' .
                'or the WebP Express plugin for Wordpress - and supply the url.'
            );
        }

        if ($apiVersion == 0) {
            if (!empty($this->getApiKey())) {
                // if secret is set, we need md5() and md5_file() functions
                if (!function_exists('md5')) {
                    throw new ConverterNotOperationalException(
                        'A secret has been set, which requires us to create a md5 hash from the secret and the file ' .
                        'contents. ' .
                        'But the required md5() PHP function is not available.'
                    );
                }
                if (!function_exists('md5_file')) {
                    throw new ConverterNotOperationalException(
                        'A secret has been set, which requires us to create a md5 hash from the secret and the file ' .
                        'contents. But the required md5_file() PHP function is not available.'
                    );
                }
            }
        } elseif ($apiVersion >= 1) {
            if ($options['crypt-api-key-in-transfer']) {
                if (!function_exists('crypt')) {
                    throw new ConverterNotOperationalException(
                        'Configured to crypt the api-key, but crypt() function is not available.'
                    );
                }

                if (!defined('CRYPT_BLOWFISH')) {
                    throw new ConverterNotOperationalException(
                        'Configured to crypt the api-key. ' .
                        'That requires Blowfish encryption, which is not available on your current setup.'
                    );
                }
            }
        }

        // Check for curl requirements
        $this->checkOperationalityForCurlTrait();
    }

    /*
    public function checkConvertability()
    {
        // check upload limits
        $this->checkConvertabilityCloudConverterTrait();

        // TODO: some from below can be moved up here
    }
    */

    private function createOptionsToSend()
    {
        $optionsToSend = $this->options;

        if ($this->isQualityDetectionRequiredButFailing()) {
            // quality was set to "auto", but we could not meassure the quality of the jpeg locally
            // Ask the cloud service to do it, rather than using what we came up with.
            $optionsToSend['quality'] = 'auto';
        } else {
            $optionsToSend['quality'] = $this->getCalculatedQuality();
        }

        // The following are unset for security reasons.
        unset($optionsToSend['converters']);
        unset($optionsToSend['secret']);
        unset($optionsToSend['api-key']);
        unset($optionsToSend['api-url']);

        $apiVersion = $optionsToSend['api-version'];

        if ($apiVersion == 1) {
            // Lossless can be "auto" in api 2, but in api 1 "auto" is not supported
            //unset($optionsToSend['lossless']);
        } elseif ($apiVersion == 2) {
            //unset($optionsToSend['png']);
            //unset($optionsToSend['jpeg']);

            // The following are unset for security reasons.
            unset($optionsToSend['cwebp-command-line-options']);
            unset($optionsToSend['command-line-options']);
        }

        return $optionsToSend;
    }

    private function createPostData()
    {
        $options = $this->options;

        $postData = [
            'file' => curl_file_create($this->source),
            'options' => json_encode($this->createOptionsToSend()),
            'servername' => (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '')
        ];

        $apiVersion = $options['api-version'];

        $apiKey = $this->getApiKey();

        if ($apiVersion == 0) {
            $postData['hash'] = md5(md5_file($this->source) . $apiKey);
        } elseif ($apiVersion == 1) {
            //$this->logLn('api key: ' . $apiKey);

            if ($options['crypt-api-key-in-transfer']) {
                $salt = self::createRandomSaltForBlowfish();
                $postData['salt'] = $salt;

                // Strip off the first 28 characters (the first 6 are always "$2y$10$". The next 22 is the salt)
                $postData['api-key-crypted'] = substr(crypt($apiKey, '$2y$10$' . $salt . '$'), 28);
            } else {
                $postData['api-key'] = $apiKey;
            }
        }
        return $postData;
    }

    protected function doActualConvert()
    {
        $ch = self::initCurl();

        //$this->logLn('api url: ' . $this->getApiUrl());

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->getApiUrl(),
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $this->createPostData(),
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $this->logLn('Curl error: ', 'bold');
            $this->logLn(curl_error($ch));
            throw new ConverterNotOperationalException('Curl error:');
        }

        // Check if we got a 404
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode == 404) {
            curl_close($ch);
            throw new ConversionFailedException(
                'WPC was not found at the specified URL - we got a 404 response.'
            );
        }

        // Check for empty response
        if (empty($response)) {
            throw new ConversionFailedException(
                'Error: Unexpected result. We got nothing back. ' .
                    'HTTP CODE: ' . $httpCode . '. ' .
                    'Content type:' . curl_getinfo($ch, CURLINFO_CONTENT_TYPE)
            );
        };

        // The WPC cloud service either returns an image or an error message
        // Images has application/octet-stream.
        // Verify that we got an image back.
        if (curl_getinfo($ch, CURLINFO_CONTENT_TYPE) != 'application/octet-stream') {
            curl_close($ch);

            if (substr($response, 0, 1) == '{') {
                $responseObj = json_decode($response, true);
                if (isset($responseObj['errorCode'])) {
                    switch ($responseObj['errorCode']) {
                        case 0:
                            throw new ConverterNotOperationalException(
                                'There are problems with the server setup: "' .
                                $responseObj['errorMessage'] . '"'
                            );
                        case 1:
                            throw new InvalidApiKeyException(
                                'Access denied. ' . $responseObj['errorMessage']
                            );
                        default:
                            throw new ConversionFailedException(
                                'Conversion failed: "' . $responseObj['errorMessage'] . '"'
                            );
                    }
                }
            }

            // WPC 0.1 returns 'failed![error messag]' when conversion fails. Handle that.
            if (substr($response, 0, 7) == 'failed!') {
                throw new ConversionFailedException(
                    'WPC failed converting image: "' . substr($response, 7) . '"'
                );
            }

            $this->logLn('Bummer, we did not receive an image');
            $this->log('What we received starts with: "');
            $this->logLn(
                str_replace("\r", '', str_replace("\n", '', htmlentities(substr($response, 0, 400)))) . '..."'
            );

            throw new ConversionFailedException('Unexpected result. We did not receive an image but something else.');
            //throw new ConverterNotOperationalException($response);
        }

        $success = file_put_contents($this->destination, $response);
        curl_close($ch);

        if (!$success) {
            throw new ConversionFailedException('Error saving file. Check file permissions');
        }
    }
}
