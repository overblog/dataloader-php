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

use GraphQL\Deferred;
use GraphQL\Executor\Promise\Adapter\SyncPromise;
use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Executor\Promise\Promise;
use Overblog\PromiseAdapter\PromiseAdapterInterface;

class WebonyxGraphQLSyncPromiseAdapter implements PromiseAdapterInterface
{
    /** @var callable[] */
    private $cancellers = [];

    /**
     * @var SyncPromiseAdapter
     */
    private $webonyxPromiseAdapter;

    public function __construct(SyncPromiseAdapter $webonyxPromiseAdapter = null)
    {
        $webonyxPromiseAdapter = $webonyxPromiseAdapter?:new SyncPromiseAdapter();
        $this->setWebonyxPromiseAdapter($webonyxPromiseAdapter);
    }

    /**
     * @return SyncPromiseAdapter
     */
    public function getWebonyxPromiseAdapter()
    {
        return $this->webonyxPromiseAdapter;
    }

    /**
     * @param SyncPromiseAdapter $webonyxPromiseAdapter
     */
    public function setWebonyxPromiseAdapter(SyncPromiseAdapter $webonyxPromiseAdapter)
    {
        $this->webonyxPromiseAdapter = $webonyxPromiseAdapter;
    }

    /**
     * {@inheritdoc}
     */
    public function create(&$resolve = null, &$reject = null, callable $canceller = null)
    {
        $promise = $this->webonyxPromiseAdapter->create(function ($res, $rej) use (&$resolve, &$reject) {
            $resolve = $res;
            $reject = $rej;
        });
        $this->cancellers[spl_object_hash($promise)] = $canceller;

        return $promise;
    }

    /**
     * {@inheritdoc}
     */
    public function createFulfilled($promiseOrValue = null)
    {
        return $this->getWebonyxPromiseAdapter()->createFulfilled($promiseOrValue);
    }

    /**
     * {@inheritdoc}
     */
    public function createRejected($reason)
    {
        return $this->getWebonyxPromiseAdapter()->createRejected($reason);
    }

    /**
     * {@inheritdoc}
     */
    public function createAll($promisesOrValues)
    {
        return $this->getWebonyxPromiseAdapter()->all($promisesOrValues);
    }

    /**
     * {@inheritdoc}
     */
    public function isPromise($value, $strict = false)
    {
        if ($value instanceof Promise) {
            $value = $value->adoptedPromise;
        }
        $isStrictPromise = $value instanceof SyncPromise;
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
            Deferred::runQueue();
            SyncPromise::runQueue();
            $this->cancellers = [];
            return null;
        }
        $promiseAdapter = $this->getWebonyxPromiseAdapter();

        $resolvedValue = null;
        $exception = null;
        if (!$this->isPromise($promise)) {
            throw new \InvalidArgumentException(sprintf('The "%s" method must be called with a Promise ("then" method).', __METHOD__));
        }

        try {
            $resolvedValue = $promiseAdapter->wait($promise);
        } catch (\Exception $reason) {
            $exception = $reason;
        }
        if ($exception instanceof \Exception) {
            if (!$unwrap) {
                return $exception;
            }
            throw $exception;
        }

        $hash = spl_object_hash($promise);
        unset($this->cancellers[$hash]);
        return $resolvedValue;
    }

    /**
     * {@inheritdoc}
     */
    public function cancel($promise)
    {
        $hash = spl_object_hash($promise);
        if (!$this->isPromise($promise) || !isset($this->cancellers[$hash])) {
            throw new \InvalidArgumentException(sprintf('The "%s" method must be called with a compatible Promise.', __METHOD__));
        }
        $canceller = $this->cancellers[$hash];
        unset($this->cancellers[$hash]);
        $adoptedPromise = $promise;
        if ($promise instanceof Promise) {
            $adoptedPromise = $promise->adoptedPromise;
        }
        try {
            $canceller([$adoptedPromise, 'resolve'], [$adoptedPromise, 'reject']);
        } catch (\Exception $reason) {
            $adoptedPromise->reject($reason);
        }
    }
}
