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

use Symfony\Component\Cache\Adapter\AdapterInterface;

class Option
{
    /**
     * @var bool
     */
    private $batch;

    /**
     * @var |null
     */
    private $maxBatchSize;

    /**
     * @var bool
     */
    private $cache;

    /**
     * @var callable
     */
    private $cacheKeyFn;

    /**
     * @var CacheMap
     */
    private $cacheMap;

    public function __construct(array $params = [])
    {
        $defaultOptions = [
            'batch' => true,
            'maxBatchSize' => null,
            'cache' => true,
            'cacheKeyFn' => null,
            'cacheMap' => new CacheMap()
        ];

        $options = array_merge($defaultOptions, $params);

        foreach ($options as $name => $value) {
            $method = 'set'.ucfirst($name);
            $this->$method($value);
        }
    }

    /**
     * @return boolean
     */
    public function shouldBatch()
    {
        return $this->batch;
    }

    /**
     * @param boolean $batch
     * @return Option
     */
    public function setBatch($batch)
    {
        $this->batch = $batch;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxBatchSize()
    {
        return $this->maxBatchSize;
    }

    /**
     * @param int $maxBatchSize
     * @return Option
     */
    public function setMaxBatchSize($maxBatchSize)
    {
        $this->maxBatchSize = $maxBatchSize;
        return $this;
    }

    /**
     * @return boolean
     */
    public function shouldCache()
    {
        return $this->cache;
    }

    /**
     * @param boolean $cache
     * @return Option
     */
    public function setCache($cache)
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * @return callable
     */
    public function getCacheKeyFn()
    {
        return $this->cacheKeyFn;
    }

    /**
     * @param callable $cacheKeyFn
     * @return Option
     */
    public function setCacheKeyFn(callable $cacheKeyFn = null)
    {
        $this->cacheKeyFn = $cacheKeyFn;
        return $this;
    }

    /**
     * @return CacheMap
     */
    public function getCacheMap()
    {
        return $this->cacheMap;
    }

    /**
     * @param CacheMap $cacheMap
     * @return Option
     */
    public function setCacheMap(CacheMap $cacheMap)
    {
        $this->cacheMap = $cacheMap;
        return $this;
    }
}
