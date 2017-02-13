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

class AbuseTest extends TestCase
{
    /**
     * @group provides-descriptive-error-messages-for-api-abuse
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The "Overblog\DataLoader\DataLoader::load" method must be called with a value, but got: NULL.
     */
    public function testLoadFunctionRequiresAKeyNotNull()
    {
        self::idLoader()->load(null);
    }

    /**
     * @group provides-descriptive-error-messages-for-api-abuse
     */
    public function testLoadFunctionRequiresAKeyWith0()
    {
        self::idLoader()->load(0);
    }

    /**
     * @group provides-descriptive-error-messages-for-api-abuse
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The "Overblog\DataLoader\DataLoader::loadMany" method must be called with Array<key> but got: integer.
     */
    public function testLoadManyFunctionRequiresAListOfKey()
    {
        self::idLoader()->loadMany(1, 2, 3);
    }

    /**
     * @group provides-descriptive-error-messages-for-api-abuse
     */
    public function testLoadManyFunctionRequiresAListEmptyArrayAccepted()
    {
        self::idLoader()->loadMany([]);
    }

    /**
     * @group provides-descriptive-error-messages-for-api-abuse
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage DataLoader must be constructed with a function which accepts Array<key> and returns Promise<Array<value>>, but the function did not return a Promise: array.
     */
    public function testBatchFunctionMustReturnAPromiseNotAValue()
    {
        DataLoader::await(self::idLoader(function ($keys) {
            return $keys;
        })->load(1));
    }

    /**
     * @group provides-descriptive-error-messages-for-api-abuse
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage DataLoader must be constructed with a function which accepts Array<key> and returns Promise<Array<value>>, but the function did not return a Promise of an Array: NULL.
     */
    public function testBatchFunctionMustReturnAPromiseOfAnArrayNotNull()
    {
        DataLoader::await(self::idLoader(function () {
            return self::$promiseAdapter->createFulfilled(null);
        })->load(1));
    }

    /**
     * @group provides-descriptive-error-messages-for-api-abuse
     * @expectedException \RuntimeException
     * @expectedExceptionMessage DataLoader must be constructed with a function which accepts Array<key> and returns Promise<Array<value>>, but the function did not return a Promise of an Array of the same length as the Array of keys.
     */
    public function testBatchFunctionMustPromiseAnArrayOfCorrectLength()
    {
        DataLoader::await(self::idLoader(function () {
            return self::$promiseAdapter->createFulfilled([]);
        })->load(1));
    }

    /**
     * @group provides-descriptive-error-messages-for-api-abuse
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage ::await" method must be called with a Promise ("then" method).
     * @runInSeparateProcess
     */
    public function testAwaitPromiseMustHaveAThenMethod()
    {
        self::idLoader();
        DataLoader::await([]);
    }

    /**
     * @group provides-descriptive-error-messages-for-api-abuse
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Found no active DataLoader instance.
     * @runInSeparateProcess
     */
    public function testAwaitWithoutNoInstance()
    {
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
