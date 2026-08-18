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

use Overblog\DataLoader\DataLoader;
use Overblog\PromiseAdapter\Adapter\ReactPromiseAdapter;
use Overblog\PromiseAdapter\PromiseAdapterInterface;

final class ReactDataLoadTest extends DataLoadTestCase
{
    protected function createPromiseAdapter(): PromiseAdapterInterface
    {
        return new ReactPromiseAdapter();
    }

    /**
     * @runInSeparateProcess
     */
    public function testAwaitShouldReturnTheValueOfFulfilledPromiseWithoutNeedingActiveDataLoaderInstance()
    {
        $expectedValue = 'Ok!';
        $value = DataLoader::await(self::$promiseAdapter->createFulfilled($expectedValue));

        $this->assertEquals($expectedValue, $value);
    }

    /**
     * @runInSeparateProcess
     */
    public function testAwaitShouldReturnTheRejectReasonOfRejectedPromiseWithoutNeedingActiveDataLoaderInstance()
    {
        $expectedException = new \Exception('Rejected!');
        $exception = DataLoader::await(self::$promiseAdapter->createRejected($expectedException), false);

        $this->assertEquals($expectedException, $exception);
    }

    /**
     * @runInSeparateProcess
     */
    public function testAwaitShouldThrowTheRejectReasonOfRejectedPromiseWithoutNeedingActiveDataLoaderInstance()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Rejected!');

        DataLoader::await(self::$promiseAdapter->createRejected(new \Exception('Rejected!')));
    }

    /**
     * @runInSeparateProcess
     */
    public function testAwaitShouldThrowThrowable()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Rejected Error!');

        DataLoader::await(self::$promiseAdapter->createRejected(new \Error('Rejected Error!')));
    }
}
