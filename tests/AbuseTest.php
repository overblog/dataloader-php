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

use InvalidArgumentException;
use Overblog\DataLoader\DataLoader;
use React\Promise\Promise;
use RuntimeException;

class AbuseTest extends TestCase
{
    /**
     * @group provides-descriptive-error-messages-for-api-abuse
     */
    public function testLoadFunctionRequiresAKeyWith0()
    {
        self::assertInstanceOf(Promise::class, self::idLoader()->load(0));
    }

    /**
     * @group provides-descriptive-error-messages-for-api-abuse
     */
    public function testLoadManyFunctionRequiresAListEmptyArrayAccepted()
    {
        self::assertInstanceOf(Promise::class, self::idLoader()->loadMany([]));
    }

    /**
     * @group provides-descriptive-error-messages-for-api-abuse
     */
    public function testBatchFunctionMustReturnAPromiseNotAValue()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DataLoader must be constructed with a function which accepts Array<key> and returns Promise<Array<value>>, but the function did not return a Promise: array.');

        DataLoader::await(self::idLoader(function ($keys) {
            return $keys;
        })->load(1));
    }

    /**
     * @group provides-descriptive-error-messages-for-api-abuse
     */
    public function testBatchFunctionMustReturnAPromiseOfAnArrayNotNull()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DataLoader must be constructed with a function which accepts Array<key> and returns Promise<Array<value>>, but the function did not return a Promise of an Array: NULL.');

        DataLoader::await(self::idLoader(function () {
            return self::$promiseAdapter->createFulfilled(null);
        })->load(1));
    }

    /**
     * @group provides-descriptive-error-messages-for-api-abuse
     */
    public function testBatchFunctionMustPromiseAnArrayOfCorrectLength()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DataLoader must be constructed with a function which accepts Array<key> and returns Promise<Array<value>>, but the function did not return a Promise of an Array of the same length as the Array of keys.');

        DataLoader::await(self::idLoader(function () {
            return self::$promiseAdapter->createFulfilled([]);
        })->load(1));
    }

    /**
     * @group provides-descriptive-error-messages-for-api-abuse
     * @runInSeparateProcess
     */
    public function testAwaitPromiseMustHaveAThenMethod()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('::await" method must be called with a Promise ("then" method).');

        self::idLoader();
        DataLoader::await([]);
    }

    /**
     * @group provides-descriptive-error-messages-for-api-abuse
     * @runInSeparateProcess
     */
    public function testAwaitWithoutNoInstance()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Found no active DataLoader instance.");

        DataLoader::await(self::$promiseAdapter->create());
    }

    /**
     * @param callable $batchLoadFn
     * @return DataLoader
     */
    private static function idLoader(callable $batchLoadFn = null)
    {
        if (null === $batchLoadFn) {
            $batchLoadFn = function ($keys) {
                return self::$promiseAdapter->createAll($keys);
            };
        }

        return new DataLoader($batchLoadFn, self::$promiseAdapter);
    }
}
