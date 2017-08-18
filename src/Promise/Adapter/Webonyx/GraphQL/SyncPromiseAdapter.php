<?php

/*
 * This file is part of the DataLoaderPhp package.
 *
 * (c) Overblog <http://github.com/overblog/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Overblog\DataLoader\Promise\Adapter\Webonyx\GraphQL;

use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter as BaseSyncPromiseAdapter;
use GraphQL\Executor\Promise\Promise;
use Overblog\DataLoader\DataLoader;

class SyncPromiseAdapter extends BaseSyncPromiseAdapter
{
    protected function beforeWait(Promise $promise)
    {
        DataLoader::await();
    }

    protected function onWait(Promise $promise)
    {
        DataLoader::await();
    }
}
