<?php

namespace Overblog\DataLoader;

class CacheMap
{
    private $promiseCache = [];

    public function get($key)
    {
        $index = $this->getPromiseCacheIndexByKey($key);
        if (null === $index) {
            return null;
        }

        return $this->promiseCache[$index]['promise'];
    }

    public function has($key)
    {
        $index = $this->getPromiseCacheIndexByKey($key);
        if (null === $index) {
            return false;
        }

        return true;
    }

    public function set($key, $promise)
    {
        $this->promiseCache[] = [
            'key' => $key,
            'promise' => $promise,
        ];

        return $this;
    }

    public function clear($key)
    {
        $index = $this->getPromiseCacheIndexByKey($key);
        unset($this->promiseCache[$index]);

        return $this;
    }

    public function clearAll()
    {
        $this->promiseCache = [];

        return $this;
    }

    private function getPromiseCacheIndexByKey($cacheKey)
    {
        foreach ($this->promiseCache as $index => $data) {
            if ($data['key'] === $cacheKey) {
                return $index;
            }
        }
        return null;
    }
}
