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

use Overblog\PromiseAdapter\Adapter\ReactPromiseAdapter;
use Overblog\PromiseAdapter\PromiseAdapterInterface;

class ReactAbuseTest extends AbuseTestCase
{
    protected function createPromiseAdapter(): PromiseAdapterInterface
    {
        return new ReactPromiseAdapter();
    }
}
