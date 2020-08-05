<?php

namespace GuzzleHttp;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Middleware that retries requests based on the boolean result of
 * invoking the provided "decider" function.
 */
class RetryMiddleware
{
    /**
     * @var callable(RequestInterface, array): PromiseInterface
     */
    private $nextHandler;

    /**
     * @var callable
     */
    private $decider;

    /**
     * @var callable(int)
     */
    private $delay;

    /**
     * @param callable                                            $decider     Function that accepts the number of retries,
     *                                                                         a request, [response], and [exception] and
     *                                                                         returns true if the request is to be
     *                                                                         retried.
     * @param callable(RequestInterface, array): PromiseInterface $nextHandler Next handler to invoke.
     * @param null|callable(int): int                             $delay       Function that accepts the number of retries
     *                                                                         and returns the number of
     *                                                                         milliseconds to delay.
     */
    public function __construct(
        callable $decider,
        callable $nextHandler,
        callable $delay = null
    ) {
        $this->decider = $decider;
        $this->nextHandler = $nextHandler;
        $this->delay = $delay ?: __CLASS__ . '::exponentialDelay';
    }

    /**
     * Default exponential backoff delay function.
     *
     * @return int milliseconds.
     */
    public static function exponentialDelay(int $retries): int
    {
        return (int) \pow(2, $retries - 1) * 1000;
    }

    public function __invoke(RequestInterface $request, array $options): PromiseInterface
    {
        if (!isset($options['retries'])) {
            $options['retries'] = 0;
        }

        $fn = $this->nextHandler;
        return $fn($request, $options)
            ->then(
                $this->onFulfilled($request, $options),
                $this->onRejected($request, $options)
            );
    }

    /**
     * Execute fulfilled closure
     */
    private function onFulfilled(RequestInterface $request, array $options): callable
    {
        return function ($value) use ($request, $options) {
            if (!\call_user_func(
                $this->decider,
                $options['retries'],
                $request,
                $value,
                null
            )) {
                return $value;
            }
            return $this->doRetry($request, $options, $value);
        };
    }

    /**
     * Execute rejected closure
     */
    private function onRejected(RequestInterface $req, array $options): callable
    {
        return function ($reason) use ($req, $options) {
            if (!\call_user_func(
                $this->decider,
                $options['retries'],
                $req,
                null,
                $reason
            )) {
                return \GuzzleHttp\Promise\rejection_for($reason);
            }
            return $this->doRetry($req, $options);
        };
    }

    private function doRetry(RequestInterface $request, array $options, ResponseInterface $response = null): PromiseInterface
    {
        $options['delay'] = \call_user_func($this->delay, ++$options['retries'], $response);

        return $this($request, $options);
    }
}
