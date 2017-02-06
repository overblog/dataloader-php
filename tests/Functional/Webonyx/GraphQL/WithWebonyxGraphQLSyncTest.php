<?php

/*
 * This file is part of the DataLoaderPhp package.
 *
 * (c) Overblog <http://github.com/overblog/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Overblog\DataLoader\Test\Functional\Webonyx\GraphQL;

use GraphQL\Executor\Promise\PromiseAdapter;
use Overblog\DataLoader\Promise\Adapter\Webonyx\GraphQL\SyncPromiseAdapter;
use Overblog\PromiseAdapter\Adapter\WebonyxGraphQLSyncPromiseAdapter;

class WithWebonyxGraphQLSyncTest extends TestCase
{
    protected function createGraphQLPromiseAdapter()
    {
        return new SyncPromiseAdapter();
    }

    protected function createDataLoaderPromiseAdapter(PromiseAdapter $graphQLPromiseAdapter)
    {
        return new WebonyxGraphQLSyncPromiseAdapter($graphQLPromiseAdapter);
    }
}
