<?php

/*
 * This file is part of the DataLoaderPhp package.
 *
 * (c) Overblog <http://github.com/overblog/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Overblog\DataLoader\Test;

use Overblog\DataLoader\CacheMap;

class SimpleMap extends CacheMap
{
    /**
     * @var \ArrayObject
     */
    public $stash;

    public function __construct()
    {
        $this->clearAll();
    }

    public function get($key)
    {
        return isset($this->stash[$key]) ? $this->stash[$key] : null;
    }

    public function set($key, $value)
    {
        $this->stash[$key] = $value;

        return $this;
    }

    public function clear($key)
    {
        unset($this->stash[$key]);

        return $this;
    }

    public function clearAll()
    {
        $this->stash = new  \ArrayObject();

        return $this;
    }
}
