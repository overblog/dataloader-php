<?php

namespace Overblog\DataLoader;

use React\Promise\Promise;

class DataLoader
{
    /**
     * @var BatchLoadFn
     */
    private $batchLoadFn;

    /**
     * @var Option
     */
    private $options;

    /**
     * @var Promise[]
     */
    private $promiseCache;

    /**
     * @var Promise[]
     */
    private $queue = [];

    /**
     * @var null|\React\EventLoop\LoopInterface
     */
    private $eventLoop;

    /**
     * @var Promise
     */
    private $resolvedPromise;

    public function __construct(BatchLoadFn $batchLoadFn, Option $options = null)
    {
        $this->batchLoadFn = $batchLoadFn;
        $this->options = $options ?: new Option();
        $this->eventLoop = class_exists('React\\EventLoop\\Factory') ? \React\EventLoop\Factory::create() : null;
        $this->resultCache = $this->options->getCacheMap();
    }

    /**
     * Loads a key, returning a `Promise` for the value represented by that key.
     *
     * @param string $key
     *
     * @return Promise
     */
    public function load($key)
    {
        $this->checkKey($key, __METHOD__);
        // Determine options
        $shouldBatch = $this->options->shouldBatch();
        $shouldCache = $this->options->shouldCache();
        $cacheKeyFn = $this->options->getCacheKeyFn();
        $cacheKey = $cacheKeyFn ? $cacheKeyFn($key) : $key;

        // If caching and there is a cache-hit, return cached Promise.
        if ($shouldCache) {
            $cachedPromise = isset($this->promiseCache[$cacheKey]) ? $this->promiseCache[$cacheKey] : null;
            if ($cachedPromise) {
                return $cachedPromise;
            }
        }

        // Otherwise, produce a new Promise for this value.
        $promise = new Promise(function ($resolve, $reject) use ($key, $shouldBatch) {
            $this->queue[] = [
                'key' => $key,
                'resolve' => $resolve,
                'reject' => $reject,
            ];

            // Determine if a dispatch of this queue should be scheduled.
            // A single dispatch should be scheduled per queue at the time when the
            // queue changes from "empty" to "full".
            if (count($this->queue) === 1) {
                if ($shouldBatch) {
                    // If batching, schedule a task to dispatch the queue.
                    $this->enqueuePostPromiseJob(function () {
                        $this->dispatchQueue();
                    });
                } else {
                    // Otherwise dispatch the (queue of one) immediately.
                    $this->dispatchQueue();
                }
            }
        });
        // If caching, cache this promise.
        if ($shouldCache) {
            $this->promiseCache[$cacheKey] = $promise;
        }

        return $promise;
    }

    /**
     * Loads multiple keys, promising an array of values:
     *
     *     [$a, $b] = $myLoader->loadMany(['a', 'b']);
     *
     * This is equivalent to the more verbose:
     *
     *     [$a, $b] = \React\Promise\all([
     *       $myLoader->load('a'),
     *       $myLoader->load('b')
     *     ]);
     * @param array $keys
     *
     * @return Promise
     */
    public function loadMany($keys)
    {
        if (!is_array($keys) && !$keys instanceof \Traversable) {
            throw new \InvalidArgumentException(sprintf('The %s function must be called with Array<key> but got: %s.', __METHOD__, gettype($keys)));
        }
        return \React\Promise\all(array_map(
            function ($key) {
                return $this->load($key);
            },
            $keys
        ));
    }

    /**
     * Clears the value at `key` from the cache, if it exists.
     *
     * @param $key
     * @return $this
     */
    public function clear($key)
    {
        $this->checkKey($key, __METHOD__);

        $cacheKeyFn = $this->options->getCacheKeyFn();
        $cacheKey = $cacheKeyFn ? $cacheKeyFn($key) : $key;
        unset($this->promiseCache[$cacheKey]);

        return $this;
    }

    /**
     * Clears the entire cache. To be used when some event results in unknown
     * invalidations across this particular `DataLoader`.
     *
     * @return $this
     */
    public function clearAll()
    {
        $this->promiseCache = [];
        return $this;
    }

    /**
     * Adds the provided key and value to the cache. If the key already exists, no
     * change is made. Returns itself for method chaining.
     * @param $key
     * @param $value
     * @return $this
     */
    public function prime($key, $value)
    {
        $this->checkKey($key, __METHOD__);

        $cacheKeyFn = $this->options->getCacheKeyFn();
        $cacheKey = $cacheKeyFn ? $cacheKeyFn($key) : $key;

        // Only add the key if it does not already exist.
        if (!isset($this->promiseCache[$cacheKey])) {
            // Cache a rejected promise if the value is an Error, in order to match
            // the behavior of load(key).
            $promise = $value instanceof \Exception ? \React\Promise\reject($value) : \React\Promise\resolve($value);

            $this->promiseCache[$cacheKey] = $promise;
        }

        return $this;
    }

    public function process()
    {
        if (count($this->queue) > 0) {
            $this->dispatchQueue();
        }
    }

    private function checkKey($key, $method)
    {
        if (empty($key) || !is_scalar($key)) {
            throw new \InvalidArgumentException(
                sprintf('The %s function must be called with a scalar value, but got: %s.', $method, gettype($key))
            );
        }
    }

    /**
     * @param $fn
     */
    private function enqueuePostPromiseJob($fn)
    {
        if (!$this->resolvedPromise) {
            $this->resolvedPromise = \React\Promise\resolve();
        }

        if ($this->eventLoop) {
            $this->resolvedPromise->then(function () use ($fn) { $this->eventLoop->nextTick($fn); });
        } else {

        }
    }

    /**
     * Given the current state of a Loader instance, perform a batch load
     * from its current queue.
     */
    private function dispatchQueue()
    {
        // Take the current loader queue, replacing it with an empty queue.
        $queue = $this->queue;
        $this->queue = [];
        $queueLength = count($queue);
        // If a maxBatchSize was provided and the queue is longer, then segment the
        // queue into multiple batches, otherwise treat the queue as a single batch.
        $maxBatchSize = $this->options->getMaxBatchSize();
        if ($maxBatchSize && $maxBatchSize > 0 && $maxBatchSize < $queueLength) {
            for ($i = 0; $i < $queueLength / $maxBatchSize; $i++) {
                $offset = $i * $maxBatchSize;
                $length = ($i + 1) * $maxBatchSize - $offset;

                $this->dispatchQueueBatch(array_slice($queue, $offset, $length));
            }
        } else {
            $this->dispatchQueueBatch($queue);
        }
    }

    private function dispatchQueueBatch(array $queue)
    {
        // Collect all keys to be loaded in this dispatch
        $keys = array_column($queue, 'key');

        // Call the provided batchLoadFn for this loader with the loader queue's keys.
        $batchLoadFn = $this->batchLoadFn;
        /** @var Promise $batchPromise */
        $batchPromise = $batchLoadFn($keys);

        // Assert the expected response from batchLoadFn
        if (!$batchPromise || !is_callable([$batchPromise, 'then'])) {
           $this->failedDispatch($queue, new \RuntimeException(
                'DataLoader must be constructed with a function which accepts '.
                'Array<key> and returns Promise<Array<value>>, but the function did '.
                sprintf('not return a Promise: %s.', gettype($batchPromise))
            ));

            return;
        }

        // Await the resolution of the call to batchLoadFn.
        $batchPromise->then(
            function ($values) use ($keys, $queue) {
                // Assert the expected resolution from batchLoadFn.
                if (!is_array($values) && !$values instanceof \Traversable) {
                    throw new \RuntimeException(
                        'DataLoader must be constructed with a function which accepts ' .
                        'Array<key> and returns Promise<Array<value>>, but the function did ' .
                        sprintf('not return a Promise of an Array: %s.', gettype($values))
                    );
                }
                if (count($values) !== count($keys)) {
                    throw new \RuntimeException(
                        'DataLoader must be constructed with a function which accepts ' .
                        'Array<key> and returns Promise<Array<value>>, but the function did ' .
                        'not return a Promise of an Array of the same length as the Array of keys.'
                    );
                }

                // Step through the values, resolving or rejecting each Promise in the
                // loaded queue.
                foreach ($queue as $index => $data) {
                    $value = $values[$index];
                    if ($value instanceof \Exception) {
                        $data['reject']($value);
                    } else {
                        $data['resolve']($value);
                    }
                };
            }
        )->otherwise(function ($error) use ($queue) {
            $this->failedDispatch($queue, $error);
        });
    }

    /**
     * Do not cache individual loads if the entire batch dispatch fails,
     * but still reject each request so they do not hang.
     * @param Promise[] $queue
     * @param \Exception $error
     */
    private function failedDispatch($queue, \Exception $error)
    {
        foreach ($queue as $index => $data) {
            $this->clear($data['key']);
            $data['reject']($error);
        }
    }
}
