<?php

/*
 * This file is part of the DataLoaderPhp package.
 *
 * (c) Overblog <http://github.com/overblog/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Overblog\DataLoader;

use Overblog\PromiseAdapter\PromiseAdapterInterface;

class DataLoader
{
    /**
     * @var callable
     */
    private $batchLoadFn;

    /**
     * @var Option
     */
    private $options;

    /**
     * @var CacheMap
     */
    private $promiseCache;

    /**
     * @var array
     */
    private $queue = [];

    /**
     * @var self[]
     */
    private static $instances = [];

    /**
     * @var PromiseAdapterInterface
     */
    private $promiseAdapter;

    public function __construct(callable $batchLoadFn, PromiseAdapterInterface $promiseFactory, Option $options = null)
    {
        $this->batchLoadFn = $batchLoadFn;
        $this->promiseAdapter = $promiseFactory;
        $this->options = $options ?: new Option();
        $this->promiseCache = $this->options->getCacheMap();
        self::$instances[] = $this;
    }

    /**
     * Loads a key, returning a `Promise` for the value represented by that key.
     *
     * @param string $key
     *
     * @return mixed return a Promise
     */
    public function load($key)
    {
        $this->checkKey($key, __METHOD__);
        // Determine options
        $shouldBatch = $this->options->shouldBatch();
        $shouldCache = $this->options->shouldCache();
        $cacheKey = $this->getCacheKeyFromKey($key);

        // If caching and there is a cache-hit, return cached Promise.
        if ($shouldCache) {
            $cachedPromise = $this->promiseCache->get($cacheKey);
            if ($cachedPromise) {
                return $cachedPromise;
            }
        }

        // Otherwise, produce a new Promise for this value.
        $promise = $this->getPromiseAdapter()->create(
            $resolve,
            $reject,
            function () {
                // Cancel/abort any running operations like network connections, streams etc.

                throw new \RuntimeException('DataLoader destroyed before promise complete.');
            }
        );

        $this->queue[] = [
            'key' => $key,
            'resolve' => $resolve,
            'reject' => $reject,
            'promise' => $promise,
        ];

        // Determine if a dispatch of this queue should be scheduled.
        // A single dispatch should be scheduled per queue at the time when the
        // queue changes from "empty" to "full".
        if (count($this->queue) === 1) {
            if (!$shouldBatch) {
                // Otherwise dispatch the (queue of one) immediately.
                $this->dispatchQueue();
            }
        }
        // If caching, cache this promise.
        if ($shouldCache) {
            $this->promiseCache->set($cacheKey, $promise);
        }

        return $promise;
    }

    /**
     * Loads multiple keys, promising an array of values:
     *
     *     list($a, $b) = $myLoader->loadMany(['a', 'b']);
     *
     * This is equivalent to the more verbose:
     *
     *     list($a, $b) = \React\Promise\all([
     *       $myLoader->load('a'),
     *       $myLoader->load('b')
     *     ]);
     * @param array $keys
     *
     * @return mixed return a Promise
     */
    public function loadMany($keys)
    {
        if (!is_array($keys) && !$keys instanceof \Traversable) {
            throw new \InvalidArgumentException(sprintf('The "%s" method must be called with Array<key> but got: %s.', __METHOD__, gettype($keys)));
        }
        return $this->getPromiseAdapter()->createAll(array_map(
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
        $cacheKey = $this->getCacheKeyFromKey($key);
        $this->promiseCache->clear($cacheKey);

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
        $this->promiseCache->clearAll();

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

        $cacheKey = $this->getCacheKeyFromKey($key);

        // Only add the key if it does not already exist.
        if (!$this->promiseCache->has($cacheKey)) {
            // Cache a rejected promise if the value is an Error, in order to match
            // the behavior of load(key).
            $promise = $value instanceof \Exception ? $this->getPromiseAdapter()->createRejected($value) : $this->getPromiseAdapter()->createFulfilled($value);

            $this->promiseCache->set($cacheKey, $promise);
        }

        return $this;
    }

    public function __destruct()
    {
        if ($this->needProcess()) {
            foreach ($this->queue as $data) {
                try {
                    $this->getPromiseAdapter()->cancel($data['promise']);
                } catch (\Exception $e) {
                    // no need to do nothing if cancel failed
                }
            }
            $this->await();
        }
        foreach (self::$instances as $i => $instance) {
            if ($this !== $instance) {
                continue;
            }
            unset(self::$instances[$i]);
        }
    }

    protected function needProcess()
    {
        return count($this->queue) > 0;
    }

    protected function process()
    {
        if ($this->needProcess()) {
            $this->getPromiseAdapter()->await();
            $this->dispatchQueue();
        }
    }

    protected function getPromiseAdapter()
    {
        return $this->promiseAdapter;
    }

    /**
     * @param $promise
     * @param bool $unwrap controls whether or not the value of the promise is returned for a fulfilled promise or if an exception is thrown if the promise is rejected
     * @return mixed
     * @throws \Exception
     */
    public static function await($promise = null, $unwrap = true)
    {
        self::awaitInstances();

        if (null === $promise) {
            return null;
        }

        if (is_callable([$promise, 'then'])) {
            $isPromiseCompleted = false;
            $resolvedValue = null;
            $rejectedReason = null;

            $promise->then(
                function ($value) use (&$isPromiseCompleted, &$resolvedValue) {
                    $isPromiseCompleted = true;
                    $resolvedValue = $value;
                },
                function ($reason) use (&$isPromiseCompleted, &$rejectedReason) {
                    $isPromiseCompleted = true;
                    $rejectedReason = $reason;
                }
            );

            //Promise is completed?
            if ($isPromiseCompleted) {
                // rejected ?
                if ($rejectedReason instanceof \Exception) {
                    if (!$unwrap) {
                        return $rejectedReason;
                    }
                    throw $rejectedReason;
                }

                return $resolvedValue;
            }
        }

        if (empty(self::$instances)) {
            throw new \RuntimeException('Found no active DataLoader instance.');
        }

        return self::$instances[0]->getPromiseAdapter()->await($promise, $unwrap);
    }

    private static function awaitInstances()
    {
        do {
            $wait = false;
            $dataLoaders = self::$instances;

            foreach ($dataLoaders as $dataLoader) {
                if (!$dataLoader || !$dataLoader->needProcess()) {
                    $wait |= false;
                    continue;
                }
                $wait = true;
                $dataLoader->process();
            }
        } while ($wait);
    }

    /**
     * @param $key
     *
     * @return mixed
     */
    protected function getCacheKeyFromKey($key)
    {
        $cacheKeyFn = $this->options->getCacheKeyFn();
        $cacheKey = $cacheKeyFn ? $cacheKeyFn($key) : $key;

        return $cacheKey;
    }

    /**
     * @param $key
     * @param $method
     */
    protected function checkKey($key, $method)
    {
        if (null === $key) {
            throw new \InvalidArgumentException(
                sprintf('The "%s" method must be called with a value, but got: %s.', $method, gettype($key))
            );
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
        $batchPromise = $batchLoadFn($keys);

        // Assert the expected response from batchLoadFn
        if (!$batchPromise || !is_callable([$batchPromise, 'then'])) {
            $this->failedDispatch($queue, new \RuntimeException(
                'DataLoader must be constructed with a function which accepts ' .
                'Array<key> and returns Promise<Array<value>>, but the function did ' .
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
        )->then(null, function ($error) use ($queue) {
            $this->failedDispatch($queue, $error);
        });
    }

    /**
     * Do not cache individual loads if the entire batch dispatch fails,
     * but still reject each request so they do not hang.
     * @param array      $queue
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
