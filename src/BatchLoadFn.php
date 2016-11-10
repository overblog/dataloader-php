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

class BatchLoadFn
{
    private $batchLoadFn;

    public function __construct(callable $batchLoadFn = null)
    {
        $this->batchLoadFn = $batchLoadFn;
    }

    public function getBatchLoadFn()
    {
        return $this->batchLoadFn;
    }

    public function setBatchLoadFn(callable $batchLoadFn)
    {
        $this->batchLoadFn = $batchLoadFn;
        return $this;
    }

    public function __invoke(array $keys)
    {
        $batchLoadFn = $this->getBatchLoadFn();
        if (null === $batchLoadFn) {
            throw new \LogicException('A valid batchLoadFn should be define.');
        }

        return $batchLoadFn($keys);
    }
}
