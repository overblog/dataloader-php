<?php

/*
 * This file is part of the DataLoaderPhp package.
 *
 * (c) Overblog <http://github.com/overblog/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Overblog\PromiseAdapter\Adapter;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Overblog\PromiseAdapter\PromiseAdapterInterface;

class GuzzleHttpPromiseAdapter implements PromiseAdapterInterface
{
    /**
     * {@inheritdoc}
     *
     * @return Promise
     */
    public function create(&$resolve = null, &$reject = null, callable $canceller = null)
    {
        $queue = \GuzzleHttp\Promise\queue();
        $promise = new Promise([$queue, 'run'], $canceller);

        $reject = [$promise, 'reject'];
        $resolve = [$promise, 'resolve'];

        return $promise;
    }

    /**
     * {@inheritdoc}
     *
     * @return FulfilledPromise a full filed Promise
     */
    public function createFulfilled($promiseOrValue = null)
    {
        $promise = \GuzzleHttp\Promise\promise_for($promiseOrValue);

        return $promise;
    }

    /**
     * {@inheritdoc}
     *
     * @return RejectedPromise a rejected promise
     */
    public function createRejected($reason)
    {
        $promise = \GuzzleHttp\Promise\rejection_for($reason);

        return $promise;
    }

    /**
     * {@inheritdoc}
     *
     * @return Promise
     */
    public function createAll($promisesOrValues)
    {
        $promise = empty($promisesOrValues) ? $this->createFulfilled($promisesOrValues) : \GuzzleHttp\Promise\all($promisesOrValues);

        return $promise;
    }

    /**
     * {@inheritdoc}
     */
    public function isPromise($value, $strict = false)
    {
        $isStrictPromise = $value instanceof PromiseInterface;

        if ($strict) {
            return $isStrictPromise;
        }

        return $isStrictPromise || is_callable([$value, 'then']);
    }

    /**
     * {@inheritdoc}
     */
    public function await($promise = null, $unwrap = false)
    {
        $resolvedValue = null;

        if (null !== $promise) {
            $exception = null;
            if (!static::isPromise($promise)) {
                throw new \InvalidArgumentException(sprintf('The "%s" method must be called with a Promise ("then" method).', __METHOD__));
            }

            /** @var Promise $promise */
            $promise->then(function ($values) use (&$resolvedValue) {
                $resolvedValue = $values;
            }, function ($reason) use (&$exception) {
                $exception = $reason;
            });
            \GuzzleHttp\Promise\queue()->run();

            if ($exception instanceof \Exception) {
                if (!$unwrap) {
                    return $exception;
                }
                throw $exception;
            }
        } else {
            \GuzzleHttp\Promise\queue()->run();
        }

        return $resolvedValue;
    }

    /**
     * Cancel a promise
     *
     * @param PromiseInterface $promise
     * {@inheritdoc}
     */
    public function cancel($promise)
    {
        if (!static::isPromise($promise, true)) {
            throw new \InvalidArgumentException(sprintf('The "%s" method must be called with a compatible Promise.', __METHOD__));
        }
        $promise->cancel();
    }
}
