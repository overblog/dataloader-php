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
    protected $promiseCache = [];

    public function get($key, $context = null)
    {
        $key = self::serializedKey($key);
        foreach ($this->promiseCache as $cache) {
            if ($cache['context'] === $context) {
                return isset($cache['values'][$key]) ? $cache['values'][$key] : null;
            }
        }

        return null;
    }

    public function has($key, $context = null)
    {
        foreach ($this->promiseCache as $cache) {
            if ($cache['context'] === $context) {
                return isset($cache['values'][self::serializedKey($key)]);
            }
        }

        return false;
    }

    public function set($key, $promise, $context = null)
    {
        foreach ($this->promiseCache as $k => $cache) {
            if ($cache['context'] === $context) {
                $this->promiseCache[$k]['values'][self::serializedKey($key)] = $promise;

                return $this;
            }
        }

        $this->promiseCache[] = [
            'context' => $context,
            'values' => [
                self::serializedKey($key) => $promise,
            ],
        ];

        return $this;
    }

    public function clear($key, $context = null)
    {
        foreach ($this->promiseCache as $k => $cache) {
            if ($cache['context'] === $context) {
                unset($this->promiseCache[$k]['values'][self::serializedKey($key)]);
                if (count($this->promiseCache[$k]['values']) === 0) {
                    unset($this->promiseCache[$k]);
                }

                break;
            }
        }

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
