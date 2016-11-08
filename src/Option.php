<?php

namespace Overblog\DataLoader;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

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
     * @var AdapterInterface
     */
    private $cacheMap;

    public function __construct(array $params = [])
    {
        $defaultOptions = [
            'batch' => true,
            'maxBatchSize' => null,
            'cache' => true,
            'cacheKeyFn' => null,
            'cacheMap' => new ArrayAdapter(0, false)
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
     * @return AdapterInterface
     */
    public function getCacheMap()
    {
        return $this->cacheMap;
    }

    /**
     * @param AdapterInterface $cacheMap
     * @return Option
     */
    public function setCacheMap(AdapterInterface $cacheMap = null)
    {
        $this->cacheMap = $cacheMap;
        return $this;
    }
}
