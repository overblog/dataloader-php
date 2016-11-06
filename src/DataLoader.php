<?php

namespace Overblog\DataLoader;

use GuzzleHttp\Promise\Promise;
use Symfony\Component\Cache\Adapter\AdapterInterface;

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
     * @var AdapterInterface
     */
    private $promiseCache;

    /**
     * @var array
     */
    private $queue = [];

    public function __construct(BatchLoadFn $batchLoadFn, Option $options = null)
    {
        $this->batchLoadFn = $batchLoadFn;
        $this->options = $options ?: new Option();
        $this->promiseCache = $options->getCacheMap();
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
        if (empty($key) || !is_string($key)) {
            throw new \InvalidArgumentException(
                sprintf('The %s function must be called with a value, but got: %s.', __METHOD__, gettype($key))
            );
        }

        // Determine options
        $shouldBatch = $this->options->shouldBatch();
        $shouldCache = $this->options->shouldCache();
        $cacheKeyFn = $this->options->getCacheKeyFn();
        $cacheKey = $cacheKeyFn ? $cacheKeyFn($key) : $key;

        // If caching and there is a cache-hit, return cached Promise.
        if ($shouldCache) {
            $cachedPromise = $this->promiseCache->getItem($cacheKey)->get();
            if ($cachedPromise) {
                return $cachedPromise;
            }
        }

        // Otherwise, produce a new Promise for this value.
        $promise = new Promise();

        $promise->then(function () {

        });

        // If caching, cache this promise.
        if ($shouldCache) {
            $item = $this->promiseCache->getItem($cacheKey);
            $item->set($promise);
            $this->promiseCache->save($item);
        }

        return $promise;
    }
}
