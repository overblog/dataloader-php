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

class CacheMap
{
    private $promiseCache = [];

    public function get($key)
    {
        $key = self::serializedKey($key);

        return isset($this->promiseCache[$key]) ? $this->promiseCache[$key] : null;
    }

    public function has($key)
    {
        return isset($this->promiseCache[self::serializedKey($key)]);
    }

    public function set($key, $promise)
    {
        $this->promiseCache[self::serializedKey($key)] = $promise;

        return $this;
    }

    public function clear($key)
    {
        unset($this->promiseCache[self::serializedKey($key)]);

        return $this;
    }

    public function clearAll()
    {
        $this->promiseCache = [];

        return $this;
    }

    private static function serializedKey($key)
    {
        if (is_object($key)) {
            return spl_object_hash($key);
        } elseif (is_array($key)) {
            return json_encode($key);
        }

        return $key;
    }
}
