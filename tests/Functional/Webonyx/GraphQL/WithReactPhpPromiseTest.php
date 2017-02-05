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

use GraphQL\Executor\Promise\Adapter\ReactPromiseAdapter;
use GraphQL\Executor\Promise\PromiseAdapter;

class WithReactPhpPromiseTest extends TestCase
{
    protected function createGraphQLPromiseAdapter()
    {
        return new ReactPromiseAdapter();
    }

    protected function createDataLoaderPromiseAdapter(PromiseAdapter $graphQLPromiseAdapter)
    {
        return new \Overblog\PromiseAdapter\Adapter\ReactPromiseAdapter();
    }
}
