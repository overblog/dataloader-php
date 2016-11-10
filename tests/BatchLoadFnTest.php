<?php

/*
 * This file is part of the DataLoaderPhp package.
 *
 * (c) Overblog <http://github.com/overblog/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Overblog\DataLoader\Tests;

use Overblog\DataLoader\BatchLoadFn;

class BatchLoadFnTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage A valid batchLoadFn should be define.
     */
    public function testNoBatchLoadFunctionGiven()
    {
        $batchLoadFn = new BatchLoadFn();

        $batchLoadFn([]);
    }
}
