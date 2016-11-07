<?php

namespace Overblog\DataLoader;

class BatchLoadFn
{
    private $batchLoadFn;

    public function __construct(callable $batchLoadFn)
    {
        $this->batchLoadFn = $batchLoadFn;
    }

    public function __invoke(array $keys)
    {
        $batchLoadFn = $this->batchLoadFn;
        return $batchLoadFn($keys);
    }
}
