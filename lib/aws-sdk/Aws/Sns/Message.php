<?php
namespace Aws\Sns;

use Psr\Http\Message\RequestInterface;

/**
 * Represents an SNS message received over http(s).
 */
class Message implements \ArrayAccess, \IteratorAggregate
{
    private static $requiredKeys = [
        'Message',
        'MessageId',
        'Timestamp',
        'TopicArn',
        'Type',
        'Signature',
        ['SigningCertURL', 'SigningCertUrl'],
        'SignatureVersion',
    ];

    private static $subscribeKeys = [
        ['SubscribeURL', 'SubscribeUrl'],
        'Token'
    ];

    /** @var array The message data */
    private $data;

    /**
     * Creates a Message object from the raw POST data
     *
     * @return Message
     * @throws \RuntimeException If the POST data is absent, or not a valid JSON document
     */
    public static function fromRawPostData()
    {
        // Make sure the SNS-provided header exists.
        if (!isset($_SERVER['HTTP_X_AMZ_SNS_MESSAGE_TYPE'])) {
            throw new \RuntimeException('SNS message type header not provided.');
        }

        // Read the raw POST data and JSON-decode it into a message.
        return self::fromJsonString(file_get_contents('php://input'));
    }

    /**
     * Creates a Message object from a PSR-7 Request or ServerRequest object.
     *
     * @param RequestInterface $request
     * @return Message
     */
    public static function fromPsrRequest(RequestInterface $request)
    {
        return self::fromJsonString($request->getBody());
    }

    /**
     * Creates a Message object from a JSON-decodable string.
     *
     * @param string $requestBody
     * @return Message
     */
    private static function fromJsonString($requestBody)
    {
        $data = json_decode($requestBody, true);
        if (JSON_ERROR_NONE !== json_last_error() || !is_array($data)) {
            throw new \RuntimeException('Invalid POST data.');
        }

        return new Message($data);
    }

    /**
     * Creates a Message object from an array of raw message data.
     *
     * @param array $data The message data.
     *
     * @throws \InvalidArgumentException If a valid type is not provided or
     *                                   there are other required keys missing.
     */
    public function __construct(array $data)
    {
        // Ensure that all the required keys for the message's type are present.
        $this->validateRequiredKeys($data, self::$requiredKeys);
        if ($data['Type'] === 'SubscriptionConfirmation'
            || $data['Type'] === 'UnsubscribeConfirmation'
        ) {
            $this->validateRequiredKeys($data, self::$subscribeKeys);
        }

        $this->data = $data;
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->data);
    }

    public function offsetExists($key)
    {
        return isset($this->data[$key]);
    }

    public function offsetGet($key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }

    public function offsetSet($key, $value)
    {
        $this->data[$key] = $value;
    }

    public function offsetUnset($key)
    {
        unset($this->data[$key]);
    }

    /**
     * Get all the message data as a plain array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->data;
    }

    private function validateRequiredKeys(array $data, array $keys)
    {
        foreach ($keys as $key) {
            $keyIsArray = is_array($key);
            if (!$keyIsArray) {
                $found = isset($data[$key]);
            } else {
                $found = false;
                foreach ($key as $keyOption) {
                    if (isset($data[$keyOption])) {
                        $found = true;
                        break;
                    }
                }
            }

            if (!$found) {
                if ($keyIsArray) {
                    $key = $key[0];
                }
                throw new \InvalidArgumentException(
                    "\"{$key}\" is required to verify the SNS Message."
                );
            }
        }
    }
}
