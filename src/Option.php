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

        foreach ($options as $property => $value) {
            $this->$property = $value;
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
     * @return int
     */
    public function getMaxBatchSize()
    {
        return $this->maxBatchSize;
    }

    /**
     * @return boolean
     */
    public function shouldCache()
    {
        return $this->cache;
    }

    /**
     * @return callable
     */
    public function getCacheKeyFn()
    {
        return $this->cacheKeyFn;
    }

    /**
     * @return CacheMap
     */
    public function getCacheMap()
    {
        return $this->cacheMap;
    }
}
