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
use Overblog\DataLoader\Option;

class DataLoadTest extends TestCase
{
    /**
     * @group primary-api
     */
    public function testBuildsAReallyReallySimpleDataLoader()
    {
        /**
         * @var DataLoader $identityLoader
         */
        list($identityLoader) = self::idLoader();

        $promise1 = $identityLoader->load(1);
        $this->assertInstanceOfPromise($promise1);
        $this->assertEquals(1, DataLoader::await($promise1));
    }

    /**
     * @group primary-api
     */
    public function testSupportsLoadingMultipleKeysInOneCall()
    {
        /**
         * @var DataLoader $identityLoader
         */
        list($identityLoader) = self::idLoader();

        $promiseAll = $identityLoader->loadMany([1, 2]);
        $this->assertInstanceOfPromise($promiseAll);
        $this->assertEquals([1, 2], DataLoader::await($promiseAll));

        $promiseEmpty = $identityLoader->loadMany([]);
        $this->assertInstanceOfPromise($promiseEmpty);
        $this->assertEquals([], DataLoader::await($promiseEmpty));
    }

    /**
     * @group primary-api
     */
    public function testBatchesMultipleRequests()
    {
        /**
         * @var DataLoader $identityLoader
         * @var \ArrayObject $loadCalls
         */
        list($identityLoader, $loadCalls) = self::idLoader();

        $promise1 = $identityLoader->load(1);
        $promise2 = $identityLoader->load(2);

        list($value1, $value2) = DataLoader::await(self::$promiseAdapter->createAll([$promise1, $promise2]));
        $this->assertEquals(1, $value1);
        $this->assertEquals(2, $value2);

        $this->assertEquals([[1, 2]], $loadCalls->getArrayCopy());
    }

    /**
     * @group primary-api
     */
    public function testBatchesMultipleRequestsWithMaxBatchSizes()
    {
        /**
         * @var DataLoader $identityLoader
         * @var \ArrayObject $loadCalls
         */
        list($identityLoader, $loadCalls) = self::idLoader(new Option(['maxBatchSize' => 2]));

        $promise1 = $identityLoader->load(1);
        $promise2 = $identityLoader->load(2);
        $promise3 = $identityLoader->load(3);

        list($value1, $value2, $value3) = DataLoader::await(self::$promiseAdapter->createAll([$promise1, $promise2, $promise3]));
        $this->assertEquals(1, $value1);
        $this->assertEquals(2, $value2);
        $this->assertEquals(3, $value3);

        $this->assertEquals([[1, 2], [3]], $loadCalls->getArrayCopy());
    }

    /**
     * @group primary-api
     */
    public function testCoalescesIdenticalRequests()
    {
        /**
         * @var DataLoader $identityLoader
         * @var \ArrayObject $loadCalls
         */
        list($identityLoader, $loadCalls) = self::idLoader();

        $promise1a = $identityLoader->load(1);
        $promise1b = $identityLoader->load(1);

        $this->assertTrue($promise1a === $promise1b);

        list($value1a, $value1b) = DataLoader::await(self::$promiseAdapter->createAll([$promise1a, $promise1b]));
        $this->assertEquals(1, $value1a);
        $this->assertEquals(1, $value1b);

        $this->assertEquals([[1]], $loadCalls->getArrayCopy());
    }

    /**
     * @group primary-api
     */
    public function testCachesRepeatedRequests()
    {
        /**
         * @var DataLoader $identityLoader
         * @var \ArrayObject $loadCalls
         */
        list($identityLoader, $loadCalls) = self::idLoader();

        list($a, $b) = DataLoader::await(self::$promiseAdapter->createAll([$identityLoader->load('A'), $identityLoader->load('B')]));
        $this->assertEquals('A', $a);
        $this->assertEquals('B', $b);
        $this->assertEquals([['A', 'B']], $loadCalls->getArrayCopy());

        list($a2, $c) = DataLoader::await(self::$promiseAdapter->createAll([$identityLoader->load('A'), $identityLoader->load('C')]));
        $this->assertEquals('A', $a2);
        $this->assertEquals('C', $c);
        $this->assertEquals([['A', 'B'], ['C']], $loadCalls->getArrayCopy());

        list($a3, $b2, $c2) = DataLoader::await(self::$promiseAdapter->createAll([$identityLoader->load('A'), $identityLoader->load('B'), $identityLoader->load('C')]));
        $this->assertEquals('A', $a3);
        $this->assertEquals('B', $b2);
        $this->assertEquals('C', $c2);
        $this->assertEquals([['A', 'B'], ['C']], $loadCalls->getArrayCopy());
    }

    /**
     * @group primary-api
     */
    public function testClearsSingleValueInLoader()
    {
        /**
         * @var DataLoader $identityLoader
         * @var \ArrayObject $loadCalls
         */
        list($identityLoader, $loadCalls) = self::idLoader();

        list($a, $b) = DataLoader::await(self::$promiseAdapter->createAll([$identityLoader->load('A'), $identityLoader->load('B')]));
        $this->assertEquals('A', $a);
        $this->assertEquals('B', $b);
        $this->assertEquals([['A', 'B']], $loadCalls->getArrayCopy());

        $identityLoader->clear('A');
        list($a2, $b2) = DataLoader::await(self::$promiseAdapter->createAll([$identityLoader->load('A'), $identityLoader->load('B')]));
        $this->assertEquals('A', $a2);
        $this->assertEquals('B', $b2);
        $this->assertEquals([['A', 'B'], ['A']], $loadCalls->getArrayCopy());
    }

    /**
     * @group primary-api
     */
    public function testClearsAllValuesInLoader()
    {
        /**
         * @var DataLoader $identityLoader
         * @var \ArrayObject $loadCalls
         */
        list($identityLoader, $loadCalls) = self::idLoader();

        list($a, $b) = DataLoader::await(self::$promiseAdapter->createAll([$identityLoader->load('A'), $identityLoader->load('B')]));
        $this->assertEquals('A', $a);
        $this->assertEquals('B', $b);
        $this->assertEquals([['A', 'B']], $loadCalls->getArrayCopy());

        $identityLoader->clearAll();
        list($a2, $b2) = DataLoader::await(self::$promiseAdapter->createAll([$identityLoader->load('A'), $identityLoader->load('B')]));
        $this->assertEquals('A', $a2);
        $this->assertEquals('B', $b2);
        $this->assertEquals([['A', 'B'], ['A', 'B']], $loadCalls->getArrayCopy());
    }

    /**
     * @group primary-api
     */
    public function testAllowsPrimingTheCache()
    {
        /**
         * @var DataLoader $identityLoader
         * @var \ArrayObject $loadCalls
         */
        list($identityLoader, $loadCalls) = self::idLoader();

        $identityLoader->prime('A', 'A');
        list($a, $b) = DataLoader::await(self::$promiseAdapter->createAll([$identityLoader->load('A'), $identityLoader->load('B')]));
        $this->assertEquals('A', $a);
        $this->assertEquals('B', $b);
        $this->assertEquals([['B']], $loadCalls->getArrayCopy());
    }

    /**
     * @group primary-api
     */
    public function testDoesNotPrimeKeysThatAlreadyExist()
    {
        /**
         * @var DataLoader $identityLoader
         * @var \ArrayObject $loadCalls
         */
        list($identityLoader, $loadCalls) = self::idLoader();

        $identityLoader->prime('A', 'X');

        $a1 = DataLoader::await($identityLoader->load('A'));
        $b1 = DataLoader::await($identityLoader->load('B'));
        $this->assertEquals('X', $a1);
        $this->assertEquals('B', $b1);

        $identityLoader->prime('A', 'Y');
        $identityLoader->prime('B', 'Y');

        $a2 = DataLoader::await($identityLoader->load('A'));
        $b2 = DataLoader::await($identityLoader->load('B'));
        $this->assertEquals('X', $a2);
        $this->assertEquals('B', $b2);

        $this->assertEquals([['B']], $loadCalls->getArrayCopy());
    }

    /**
     * @group primary-api
     */
    public function testAllowsForcefullyPrimingTheCache()
    {
        /**
         * @var DataLoader $identityLoader
         * @var \ArrayObject $loadCalls
         */
        list($identityLoader, $loadCalls) = self::idLoader();

        $identityLoader->prime('A', 'X');

        $a1 = DataLoader::await($identityLoader->load('A'));
        $b1 = DataLoader::await($identityLoader->load('B'));
        $this->assertEquals('X', $a1);
        $this->assertEquals('B', $b1);

        $identityLoader->clear('A')->prime('A', 'Y');
        $identityLoader->clear('B')->prime('B', 'Y');

        $a2 = DataLoader::await($identityLoader->load('A'));
        $b2 = DataLoader::await($identityLoader->load('B'));
        $this->assertEquals('Y', $a2);
        $this->assertEquals('Y', $b2);

        $this->assertEquals([['B']], $loadCalls->getArrayCopy());
    }

    /**
     * @group represents-errors
     */
    public function testResolvesToErrorToIndicateFailure()
    {
        /**
         * @var DataLoader $evenLoader
         * @var \ArrayObject $loadCalls
         */
        list($evenLoader, $loadCalls) = self::eventLoader();

        $caughtError = DataLoader::await($evenLoader->load(1), false);
        $this->assertInstanceOf(\Exception::class, $caughtError);
        $this->assertEquals($caughtError->getMessage(), 'Odd: 1');
        $value2 = DataLoader::await($evenLoader->load(2));
        $this->assertEquals(2, $value2);

        $this->assertEquals([[1], [2]], $loadCalls->getArrayCopy());
    }

    /**
     * @group represents-errors
     */
    public function testCanRepresentFailuresAndSuccessesSimultaneously()
    {
        /**
         * @var DataLoader $evenLoader
         * @var \ArrayObject $loadCalls
         */
        list($evenLoader, $loadCalls) = self::eventLoader();

        $promise1 = $evenLoader->load(1);
        $promise2 = $evenLoader->load(2);

        $caughtError = null;
        $promise1->then(null, function ($error) use (&$caughtError) {
            $caughtError = $error;
        });
        DataLoader::await();
        $this->assertInstanceOf(\Exception::class, $caughtError);
        $this->assertEquals($caughtError->getMessage(), 'Odd: 1');

        $this->assertEquals(2, DataLoader::await($promise2));
        $this->assertEquals([[1, 2]], $loadCalls->getArrayCopy());
    }

    /**
     * @group represents-errors
     */
    public function testCachesFailedFetches()
    {
        /**
         * @var DataLoader $errorLoader
         * @var \ArrayObject $loadCalls
         */
        list($errorLoader, $loadCalls) = self::errorLoader();

        $caughtErrorA = null;
        try {
            DataLoader::await($errorLoader->load(1));
        } catch (\Exception $error) {
            $caughtErrorA = $error;
        }
        $this->assertInstanceOf(\Exception::class, $caughtErrorA);
        $this->assertEquals($caughtErrorA->getMessage(), 'Error: 1');

        $caughtErrorB = null;
        try {
            DataLoader::await($errorLoader->load(1));
        } catch (\Exception $error) {
            $caughtErrorB = $error;
        }
        $this->assertInstanceOf(\Exception::class, $caughtErrorB);
        $this->assertEquals($caughtErrorB->getMessage(), 'Error: 1');

        $this->assertEquals([[1]], $loadCalls->getArrayCopy());
    }

    /**
     * @group represents-errors
     */
    public function testHandlesPrimingTheCacheWithAnError()
    {
        /**
         * @var DataLoader $identityLoader
         * @var \ArrayObject $loadCalls
         */
        list($identityLoader, $loadCalls) = self::idLoader();

        $identityLoader->prime(1, new \Exception('Error: 1'));
        $caughtErrorA = null;
        try {
            DataLoader::await($identityLoader->load(1));
        } catch (\Exception $error) {
            $caughtErrorA = $error;
        }
        $this->assertInstanceOf(\Exception::class, $caughtErrorA);
        $this->assertEquals($caughtErrorA->getMessage(), 'Error: 1');

        $this->assertEquals([], $loadCalls->getArrayCopy());
    }

    /**
     * @group represents-errors
     */
    public function testCanClearValuesFromCacheAfterErrors()
    {
        /**
         * @var DataLoader $errorLoader
         * @var \ArrayObject $loadCalls
         */
        list($errorLoader, $loadCalls) = self::errorLoader();

        $caughtErrorA = null;
        try {
            DataLoader::await(
                $errorLoader->load(1)
                    ->then(null, function ($error) use (&$errorLoader) {
                        $errorLoader->clear(1);
                        throw $error;
                    })
            );
        } catch (\Exception $error) {
            $caughtErrorA = $error;
        }
        $this->assertInstanceOf(\Exception::class, $caughtErrorA);
        $this->assertEquals($caughtErrorA->getMessage(), 'Error: 1');

        $caughtErrorB = null;
        try {
            DataLoader::await(
                $errorLoader->load(1)
                    ->then(null, function ($error) use (&$errorLoader) {
                        $errorLoader->clear(1);
                        throw $error;
                    })
            );
        } catch (\Exception $error) {
            $caughtErrorB = $error;
        }
        $this->assertInstanceOf(\Exception::class, $caughtErrorB);
        $this->assertEquals($caughtErrorB->getMessage(), 'Error: 1');

        $this->assertEquals([[1], [1]], $loadCalls->getArrayCopy());
    }

    /**
     * @group represents-errors
     */
    public function testPropagatesErrorToAllLoads()
    {
        /**
         * @var DataLoader $failLoader
         * @var \ArrayObject $loadCalls
         */
        list($failLoader, $loadCalls) = self::idLoader(null, function () {
            return self::$promiseAdapter->createRejected(new \Exception('I am a terrible loader'));
        });

        $promise1 = $failLoader->load(1);
        $promise2 = $failLoader->load(2);

        $caughtError1 = null;
        try {
            DataLoader::await($promise1);
        } catch (\Exception $error) {
            $caughtError1 = $error;
        }
        $this->assertInstanceOf(\Exception::class, $caughtError1);
        $this->assertEquals($caughtError1->getMessage(), 'I am a terrible loader');

        $caughtError2 = null;
        try {
            DataLoader::await($promise2);
        } catch (\Exception $error) {
            $caughtError2 = $error;
        }
        $this->assertInstanceOf(\Exception::class, $caughtError2);
        $this->assertEquals($caughtError2->getMessage(), 'I am a terrible loader');

        $this->assertEquals([[1, 2]], $loadCalls->getArrayCopy());
    }

    /**
     * @group accepts-any-kind-of-key
     */
    public function testAcceptsObjectsAsKeys()
    {
        /**
         * @var DataLoader $identityLoader
         * @var \ArrayObject $loadCalls
         */
        list($identityLoader, $loadCalls) = self::idLoader();

        $keyA = new \stdClass();
        $keyB = new \stdClass();
        list($valueA, $valueB) = DataLoader::await(self::$promiseAdapter->createAll([$identityLoader->load($keyA), $identityLoader->load($keyB)]));
        $this->assertEquals($keyA, $valueA);
        $this->assertEquals($keyB, $valueB);

        $loadCallsArray = $loadCalls->getArrayCopy();
        $this->assertCount(1, $loadCallsArray);
        $this->assertCount(2, $loadCallsArray[0]);
        $this->assertEquals($keyA, $loadCallsArray[0][0]);
        $this->assertEquals($keyB, $loadCallsArray[0][1]);

        // Caching
        $identityLoader->clear($keyA);

        list($valueA2, $valueB2) = DataLoader::await(self::$promiseAdapter->createAll([$identityLoader->load($keyA), $identityLoader->load($keyB)]));
        $this->assertEquals($keyA, $valueA2);
        $this->assertEquals($keyB, $valueB2);

        $loadCallsArray = $loadCalls->getArrayCopy();
        $this->assertCount(2, $loadCallsArray);
        $this->assertCount(1, $loadCallsArray[1]);
        $this->assertEquals($keyA, $loadCallsArray[1][0]);
    }

    /**
     * Note: mirrors 'batches multiple requests' above.
     *
     * @group accepts-options
     */
    public function testMayDisableBatching()
    {
        /**
         * @var DataLoader $identityLoader
         * @var \ArrayObject $loadCalls
         */
        list($identityLoader, $loadCalls) = self::idLoader(new Option(['batch' => false]));

        $promise1 = $identityLoader->load(1);
        $promise2 = $identityLoader->load(2);

        list($value1, $value2) = DataLoader::await(self::$promiseAdapter->createAll([$promise1, $promise2]));
        $this->assertEquals(1, $value1);
        $this->assertEquals(2, $value2);

        $this->assertEquals([[1], [2]], $loadCalls->getArrayCopy());
    }

    /**
     * Note: mirror's 'caches repeated requests' above.
     *
     * @group accepts-options
     */
    public function testMayDisableCaching()
    {
        /**
         * @var DataLoader $identityLoader
         * @var \ArrayObject $loadCalls
         */
        list($identityLoader, $loadCalls) = self::idLoader(new Option(['cache' => false]));

        list($a, $b) = DataLoader::await(self::$promiseAdapter->createAll([$identityLoader->load('A'), $identityLoader->load('B')]));
        $this->assertEquals('A', $a);
        $this->assertEquals('B', $b);
        $this->assertEquals([['A', 'B']], $loadCalls->getArrayCopy());

        list($a2, $c) = DataLoader::await(self::$promiseAdapter->createAll([$identityLoader->load('A'), $identityLoader->load('C')]));
        $this->assertEquals('A', $a2);
        $this->assertEquals('C', $c);
        $this->assertEquals([['A', 'B'], ['A', 'C']], $loadCalls->getArrayCopy());

        list($a3, $b2, $c2) = DataLoader::await(self::$promiseAdapter->createAll([$identityLoader->load('A'), $identityLoader->load('B'), $identityLoader->load('C')]));
        $this->assertEquals('A', $a3);
        $this->assertEquals('B', $b2);
        $this->assertEquals('C', $c2);
        $this->assertEquals([['A', 'B'], ['A', 'C'], ['A', 'B', 'C']], $loadCalls->getArrayCopy());
    }

    /**
     * @group accepts-options
     * @group accepts-object-key-in-custom-cacheKey-function
     */
    public function testAcceptsObjectsWithAComplexKey()
    {
        /**
         * @var DataLoader $identityLoader
         * @var \ArrayObject $loadCalls
         */
        list($identityLoader, $loadCalls) = self::idLoader(new Option(['cacheKeyFn' => [$this, 'cacheKey']]));

        $key1 = (object)['id' => 123];
        $key2 = (object)['id' => 123];

        $value1 = $identityLoader->await($identityLoader->load($key1));
        $value2 = $identityLoader->await($identityLoader->load($key2));

        $this->assertEquals([[$key1]], $loadCalls->getArrayCopy());
        $this->assertEquals($value1, $key1);
        $this->assertEquals($value2, $key1);
    }

    /**
     * @group accepts-options
     * @group accepts-object-key-in-custom-cacheKey-function
     */
    public function testClearsObjectsWithComplexKey()
    {
        /**
         * @var DataLoader $identityLoader
         * @var \ArrayObject $loadCalls
         */
        list($identityLoader, $loadCalls) = self::idLoader(new Option(['cacheKeyFn' => [$this, 'cacheKey']]));

        $key1 = (object)['id' => 123];
        $key2 = (object)['id' => 123];

        $value1 = $identityLoader->await($identityLoader->load($key1));
        $identityLoader->clear($key2); // clear equivalent object key
        $value2 = $identityLoader->await($identityLoader->load($key1));

        $this->assertEquals([[$key1], [$key1]], $loadCalls->getArrayCopy());
        $this->assertEquals($value1, $key1);
        $this->assertEquals($value2, $key1);
    }

    /**
     * @group accepts-options
     * @group accepts-object-key-in-custom-cacheKey-function
     */
    public function testAcceptsObjectsWithDifferentOrderOfKeys()
    {
        /**
         * @var DataLoader $identityLoader
         * @var \ArrayObject $loadCalls
         */
        list($identityLoader, $loadCalls) = self::idLoader(new Option(['cacheKeyFn' => [$this, 'cacheKey']]));

        $keyA = (object)['a' => 123, 'b' => 321];
        $keyB = (object)['b' => 321, 'a' => 123];

        $valueA = $identityLoader->await($identityLoader->load($keyA));
        $valueB = $identityLoader->await($identityLoader->load($keyB));

        $this->assertEquals($valueA, $keyA);
        $this->assertEquals($valueA, $valueB);

        $loadCallsArray = $loadCalls->getArrayCopy();
        $this->assertCount(1, $loadCallsArray);
        $this->assertCount(1, $loadCallsArray[0]);
        $this->assertEquals($keyA, $loadCallsArray[0][0]);
    }

    /**
     * @group accepts-options
     * @group accepts-object-key-in-custom-cacheKey-function
     */
    public function testAllowsPrimingTheCacheWithAnObjectKey()
    {
        /**
         * @var DataLoader $identityLoader
         * @var \ArrayObject $loadCalls
         */
        list($identityLoader, $loadCalls) = self::idLoader(new Option(['cacheKeyFn' => [$this, 'cacheKey']]));

        $key1 = (object)['id' => 123];
        $key2 = (object)['id' => 123];

        $identityLoader->prime($key1, $key1);
        $value1 = $identityLoader->await($identityLoader->load($key1));
        $value2 = $identityLoader->await($identityLoader->load($key2));

        $this->assertEquals([], $loadCalls->getArrayCopy());
        $this->assertEquals($value1, $key1);
        $this->assertEquals($value2, $key1);
    }

    /**
     * @group accepts-options
     * @group accepts-custom-cache-map-instance
     */
    public function testAcceptsACustomCacheMapImplementation()
    {
        $aCustomMap = new SimpleMap();
        /**
         * @var DataLoader $identityLoader
         * @var \ArrayObject $loadCalls
         */
        list($identityLoader, $loadCalls) = self::idLoader(new Option(['cacheMap' => $aCustomMap]));

        list($valueA, $valueB1) = DataLoader::await(self::$promiseAdapter->createAll([$identityLoader->load('a'), $identityLoader->load('b')]));
        $this->assertEquals('a', $valueA);
        $this->assertEquals('b', $valueB1);
        $this->assertEquals([['a', 'b']], $loadCalls->getArrayCopy());
        $this->assertEquals(['a', 'b'], array_keys($aCustomMap->stash->getArrayCopy()));

        list($valueC, $valueB2) = DataLoader::await(self::$promiseAdapter->createAll([$identityLoader->load('c'), $identityLoader->load('b')]));
        $this->assertEquals('c', $valueC);
        $this->assertEquals('b', $valueB2);
        $this->assertEquals([['a', 'b'], ['c']], $loadCalls->getArrayCopy());
        $this->assertEquals(['a', 'b', 'c'], array_keys($aCustomMap->stash->getArrayCopy()));

        // Supports clear

        $identityLoader->clear('b');
        $valueB3 = DataLoader::await($identityLoader->load('b'));
        $this->assertEquals('b', $valueB3);
        $this->assertEquals([['a', 'b'], ['c'], ['b']], $loadCalls->getArrayCopy());
        $this->assertEquals(['a', 'c', 'b'], array_keys($aCustomMap->stash->getArrayCopy()));

        // Supports clear all

        $identityLoader->clearAll();
        $this->assertEquals([], $aCustomMap->stash->getArrayCopy());
    }

    /**
     * @group it-is-resilient-to-job-queue-ordering
     */
    public function testBatchesLoadsOccurringWithinPromises()
    {
        /**
         * @var DataLoader $identityLoader
         * @var \ArrayObject $loadCalls
         */
        list($identityLoader, $loadCalls) = self::idLoader();

        DataLoader::await(
            self::$promiseAdapter->createAll([
                $identityLoader->load('A'),
                self::$promiseAdapter->createFulfilled()
                    ->then(function () {
                        return self::$promiseAdapter->createFulfilled();
                    })
                    ->then(function () use ($identityLoader) {
                        $identityLoader->load('B');
                        self::$promiseAdapter->createFulfilled()
                            ->then(function () use ($identityLoader) {
                                return self::$promiseAdapter->createFulfilled();
                            })
                            ->then(function () use ($identityLoader) {
                                $identityLoader->load('C');
                                self::$promiseAdapter->createFulfilled()
                                    ->then(function () {
                                        return self::$promiseAdapter->createFulfilled();
                                    })
                                    ->then(function () use ($identityLoader) {
                                        $identityLoader->load('D');
                                    });
                            });
                    })
            ])
        );

        $this->assertEquals([['A', 'B', 'C', 'D']], $loadCalls->getArrayCopy());
    }

    /**
     * @group it-is-resilient-to-job-queue-ordering
     */
    public function testCanCallALoaderFromALoader()
    {
        /**
         * @var DataLoader $deepLoader
         * @var \ArrayObject $deepLoadCalls
         */
        list($deepLoader, $deepLoadCalls) = self::idLoader();

        $aLoadCalls = null;
        /**
         * @var DataLoader $aLoader
         * @var \ArrayObject $aLoadCalls
         */
        list($aLoader, $aLoadCalls) = self::idLoader(null, function ($keys) use (&$deepLoader) {
            return $deepLoader->load($keys);
        });

        $bLoadCalls = null;
        /**
         * @var DataLoader $bLoader
         * @var \ArrayObject $bLoadCalls
         */
        list($bLoader, $bLoadCalls) = self::idLoader(null, function ($keys) use (&$deepLoader) {
            return $deepLoader->load($keys);
        });

        list($a1, $b1, $a2, $b2) = DataLoader::await(self::$promiseAdapter->createAll([
            $aLoader->load('A1'),
            $bLoader->load('B1'),
            $aLoader->load('A2'),
            $bLoader->load('B2')
        ]));

        $this->assertEquals('A1', $a1);
        $this->assertEquals('B1', $b1);
        $this->assertEquals('A2', $a2);
        $this->assertEquals('B2', $b2);

        $this->assertEquals([['A1', 'A2']], $aLoadCalls->getArrayCopy());
        $this->assertEquals([['B1', 'B2']], $bLoadCalls->getArrayCopy());
        $this->assertEquals([[['A1', 'A2'], ['B1', 'B2']]], $deepLoadCalls->getArrayCopy());
    }

    public function testOnDestructionAllPromiseInQueueShouldBeCancelled()
    {
        /**
         * @var DataLoader $loader
         */
        list($loader) = self::idLoader();
        /** @var \Exception|null $exception */
        $exception = null;
        $loader->load('A1')->then(null, function ($reason) use (&$exception) {
            $exception = $reason;
        });
        $loader->__destruct();
        unset($loader);

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertEquals($exception->getMessage(), 'DataLoader destroyed before promise complete.');
    }

    public function testCallingAwaitFunctionWhenNoInstanceOfDataLoaderShouldNotThrowError()
    {
        DataLoader::await();
    }

    public function testAwaitAlsoAwaitsNewlyCreatedDataloaders()
    {
        $firstComplete = false;
        $secondComplete = false;

        $first = new DataLoader(function ($values) use (&$firstComplete, &$secondComplete) {
            $second = new DataLoader(function ($values) use (&$secondComplete) {
                $secondComplete = true;
                return self::$promiseAdapter->createAll(['B']);
            }, self::$promiseAdapter);

            $second->load('B');

            $firstComplete = true;
            return self::$promiseAdapter->createAll(['A']);
        }, self::$promiseAdapter);

        // This tests that an idling dataloader do not cause the others to be skipped.
        $third = new DataLoader(function () {
            // noop
        }, self::$promiseAdapter);

        DataLoader::await($first->load('A'));

        $this->assertTrue($firstComplete);
        $this->assertTrue($secondComplete);
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
     * @expectedException \Exception
     * @expectedExceptionMessage Rejected!
     * @runInSeparateProcess
     */
    public function testAwaitShouldThrowTheRejectReasonOfRejectedPromiseWithoutNeedingActiveDataLoaderInstance()
    {
        DataLoader::await(self::$promiseAdapter->createRejected(new \Exception('Rejected!')));
    }

    public function cacheKey($key)
    {
        $cacheKey = [];
        $key = (array)$key;
        ksort($key);
        foreach ($key as $k => $value) {
            $cacheKey[] = $k.':'.$value;
        }

        return implode(',', $cacheKey);
    }

    private static function errorLoader()
    {
        return self::idLoader(null, function ($keys) {
            return self::$promiseAdapter->createFulfilled(
                array_map(function ($key) {
                    return new \Exception("Error: $key");
                }, $keys)
            );
        });
    }

    private static function eventLoader()
    {
        return self::idLoader(null, function ($keys) {
            $loadCalls[] = $keys;
            return self::$promiseAdapter->createFulfilled(
                array_map(function ($key) {
                    return $key % 2 === 0 ? $key : new \Exception("Odd: $key");
                }, $keys)
            );
        });
    }

    private static function idLoader(Option $options = null, callable $batchLoadFnCallBack = null)
    {
        $loadCalls = new \ArrayObject();
        if (null === $batchLoadFnCallBack) {
            $batchLoadFnCallBack = function ($keys) {
                return self::$promiseAdapter->createFulfilled($keys);
            };
        }

        $identityLoader = new DataLoader(function ($keys) use (&$loadCalls, $batchLoadFnCallBack) {
            $loadCalls[] = $keys;

            return $batchLoadFnCallBack($keys);
        }, self::$promiseAdapter, $options);

        return [$identityLoader, $loadCalls];
    }

    private function assertInstanceOfPromise($object)
    {
        $this->assertTrue(self::$promiseAdapter->isPromise($object, true));
    }
}
