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

use Overblog\PromiseAdapter\PromiseAdapterInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

/**
 * @implements PromiseAdapterInterface<PromiseInterface>
 */
class ReactPromiseAdapter implements PromiseAdapterInterface
{
    /**
     * {@inheritdoc}
     *
     * @return PromiseInterface
     */
    public function create(&$resolve = null, &$reject = null, ?callable $canceller = null)
    {
        $deferred = new Deferred($canceller);

        $reject = [$deferred, 'reject'];
        $resolve = [$deferred, 'resolve'];

        return $deferred->promise();
    }

    /**
     * {@inheritdoc}
     *
     * @return PromiseInterface a fulfilled promise
     */
    public function createFulfilled($promiseOrValue = null)
    {
        return \React\Promise\resolve($promiseOrValue);
    }

    /**
     * {@inheritdoc}
     *
     * @return PromiseInterface a rejected promise
     */
    public function createRejected($reason)
    {
        return \React\Promise\reject($reason);
    }

    /**
     * {@inheritdoc}
     *
     * @return PromiseInterface
     */
    public function createAll($promisesOrValues)
    {
        return \React\Promise\all($promisesOrValues);
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
        if (null === $promise) {
            return null;
        }
        $wait = true;
        $resolvedValue = null;
        $exception = null;
        if (!static::isPromise($promise)) {
            throw new \InvalidArgumentException(sprintf('The "%s" method must be called with a Promise ("then" method).', __METHOD__));
        }
        $promise->then(function ($values) use (&$resolvedValue, &$wait) {
            $resolvedValue = $values;
            $wait = false;
        }, function ($reason) use (&$exception, &$wait) {
            $exception = $reason;
            $wait = false;
        });

        if ($exception instanceof \Throwable) {
            if (!$unwrap) {
                return $exception;
            }
            throw $exception;
        }

        return $resolvedValue;
    }

    /**
     * Cancel a promise
     *
     * @param PromiseInterface $promise
     */
    public function cancel($promise)
    {
        if (!$promise instanceof PromiseInterface || !is_callable([$promise, 'cancel'])) {
            throw new \InvalidArgumentException(sprintf('The "%s" method must be called with a compatible Promise.', __METHOD__));
        }
        $promise->cancel();
    }
}
