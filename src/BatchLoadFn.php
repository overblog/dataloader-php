<?php

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
        if (!is_callable($batchLoadFn)) {
            throw new \RuntimeException('A batchLoadFn should be define.');
        }

        return $batchLoadFn($keys);
    }
}
