<?php

namespace Overblog\DataLoader\Tests;

use Overblog\DataLoader\BatchLoadFn;
use Overblog\DataLoader\DataLoader;
use Overblog\DataLoader\Option;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

class DataLoadTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @group primary-api
     */
    public function testBuildsAReallyReallySimpleDataLoader()
    {
        $identityLoader = self::getSimpleIdentityLoader();

        $promise1 = $identityLoader->load(1);
        $this->assertInstanceOfPromise($promise1);
        $this->assertEquals(1, self::awaitPromise($promise1, $identityLoader));
    }

    /**
     * @group primary-api
     */
    public function testSupportsLoadingMultipleKeysInOneCall()
    {
        $identityLoader = self::getSimpleIdentityLoader();

        $promiseAll = $identityLoader->loadMany([1, 2]);
        $this->assertInstanceOfPromise($promiseAll);
        $this->assertEquals([1, 2], self::awaitPromise($promiseAll, $identityLoader));

        $promiseEmpty = $identityLoader->loadMany([]);
        $this->assertInstanceOfPromise($promiseEmpty);
        $this->assertEquals([], self::awaitPromise($promiseEmpty, $identityLoader));
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

        list($value1, $value2) = self::awaitPromise(\React\Promise\all([$promise1, $promise2]), $identityLoader);
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

        list($value1, $value2, $value3) = self::awaitPromise(\React\Promise\all([$promise1, $promise2, $promise3]), $identityLoader);
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

        list($value1a, $value1b) = self::awaitPromise(\React\Promise\all([$promise1a, $promise1b]), $identityLoader);
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

        list($a, $b) = self::awaitPromise(\React\Promise\all([$identityLoader->load('A'), $identityLoader->load('B')]), $identityLoader);
        $this->assertEquals('A', $a);
        $this->assertEquals('B', $b);
        $this->assertEquals([['A', 'B']], $loadCalls->getArrayCopy());

        list($a2, $c) = self::awaitPromise(\React\Promise\all([$identityLoader->load('A'), $identityLoader->load('C')]), $identityLoader);
        $this->assertEquals('A', $a2);
        $this->assertEquals('C', $c);
        $this->assertEquals([['A', 'B'], ['C']], $loadCalls->getArrayCopy());

        list($a3, $b2, $c2) = self::awaitPromise(\React\Promise\all([$identityLoader->load('A'), $identityLoader->load('B'), $identityLoader->load('C')]), $identityLoader);
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

        list($a, $b) = self::awaitPromise(\React\Promise\all([$identityLoader->load('A'), $identityLoader->load('B')]), $identityLoader);
        $this->assertEquals('A', $a);
        $this->assertEquals('B', $b);
        $this->assertEquals([['A', 'B']], $loadCalls->getArrayCopy());

        $identityLoader->clear('A');
        list($a2, $b2) = self::awaitPromise(\React\Promise\all([$identityLoader->load('A'), $identityLoader->load('B')]), $identityLoader);
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

        list($a, $b) = self::awaitPromise(\React\Promise\all([$identityLoader->load('A'), $identityLoader->load('B')]), $identityLoader);
        $this->assertEquals('A', $a);
        $this->assertEquals('B', $b);
        $this->assertEquals([['A', 'B']], $loadCalls->getArrayCopy());

        $identityLoader->clearAll();
        list($a2, $b2) = self::awaitPromise(\React\Promise\all([$identityLoader->load('A'), $identityLoader->load('B')]), $identityLoader);
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
        list($a, $b) = self::awaitPromise(\React\Promise\all([$identityLoader->load('A'), $identityLoader->load('B')]), $identityLoader);
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

        $a1 = self::awaitPromise($identityLoader->load('A'), $identityLoader);
        $b1 = self::awaitPromise($identityLoader->load('B'), $identityLoader);
        $this->assertEquals('X', $a1);
        $this->assertEquals('B', $b1);

        $identityLoader->prime('A', 'Y');
        $identityLoader->prime('B', 'Y');

        $a2 = self::awaitPromise($identityLoader->load('A'), $identityLoader);
        $b2 = self::awaitPromise($identityLoader->load('B'), $identityLoader);
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

        $a1 = self::awaitPromise($identityLoader->load('A'), $identityLoader);
        $b1 = self::awaitPromise($identityLoader->load('B'), $identityLoader);
        $this->assertEquals('X', $a1);
        $this->assertEquals('B', $b1);

        $identityLoader->clear('A')->prime('A', 'Y');
        $identityLoader->clear('B')->prime('B', 'Y');

        $a2 = self::awaitPromise($identityLoader->load('A'), $identityLoader);
        $b2 = self::awaitPromise($identityLoader->load('B'), $identityLoader);
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
         * @var DataLoader $identityLoader
         * @var \ArrayObject $loadCalls
         */
        list($evenLoader, $loadCalls) = self::eventLoader();

        $caughtError = null;
        try {
            $this->awaitPromise($evenLoader->load(1), $evenLoader);
        } catch (\Exception $error) {
            $caughtError = $error;
        }
        $this->assertInstanceOf(\Exception::class, $caughtError);
        $this->assertEquals($caughtError->getMessage(), 'Odd: 1');
        $value2 = $this->awaitPromise($evenLoader->load(2), $evenLoader);
        $this->assertEquals(2, $value2);

        $this->assertEquals([[1], [2]], $loadCalls->getArrayCopy());
    }

    /**
     * @group represents-errors
     */
    public function testCanRepresentFailuresAndSuccessesSimultaneously()
    {
        /**
         * @var DataLoader $identityLoader
         * @var \ArrayObject $loadCalls
         */
        list($evenLoader, $loadCalls) = self::eventLoader();

        $promise1 = $evenLoader->load(1);
        $promise2 = $evenLoader->load(2);

        $caughtError = null;
        try {
            $this->awaitPromise($promise1, $evenLoader);
        } catch (\Exception $error) {
            $caughtError = $error;
        }
        $this->assertInstanceOf(\Exception::class, $caughtError);
        $this->assertEquals($caughtError->getMessage(), 'Odd: 1');

        $this->assertEquals(2, $this->awaitPromise($promise2, $evenLoader));
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
            $this->awaitPromise($errorLoader->load(1), $errorLoader);
        } catch (\Exception $error) {
            $caughtErrorA = $error;
        }
        $this->assertInstanceOf(\Exception::class, $caughtErrorA);
        $this->assertEquals($caughtErrorA->getMessage(), 'Error: 1');

        $caughtErrorB = null;
        try {
            $this->awaitPromise($errorLoader->load(1), $errorLoader);
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
            $this->awaitPromise($identityLoader->load(1), $identityLoader);
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
            $this->awaitPromise(
                $errorLoader->load(1)
                    ->otherwise(function ($error) use (&$errorLoader) {
                        $errorLoader->clear(1);
                        throw $error;
                    }),
                $errorLoader);
        } catch (\Exception $error) {
            $caughtErrorA = $error;
        }
        $this->assertInstanceOf(\Exception::class, $caughtErrorA);
        $this->assertEquals($caughtErrorA->getMessage(), 'Error: 1');

        $caughtErrorB = null;
        try {
            $this->awaitPromise(
                $errorLoader->load(1)
                    ->otherwise(function ($error) use (&$errorLoader) {
                        $errorLoader->clear(1);
                        throw $error;
                    }),
                $errorLoader);
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
        $loadCalls = new \ArrayObject();

        $failLoader = new DataLoader(new BatchLoadFn(function ($keys) use (&$loadCalls) {
            $loadCalls[] = $keys;
            return \React\Promise\reject(new \Exception('I am a terrible loader'));
        }));

        $promise1 = $failLoader->load(1);
        $promise2 = $failLoader->load(2);

        $caughtError1 = null;
        try {
            $this->awaitPromise($promise1, $failLoader);
        } catch (\Exception $error) {
            $caughtError1 = $error;
        }
        $this->assertInstanceOf(\Exception::class, $caughtError1);
        $this->assertEquals($caughtError1->getMessage(), 'I am a terrible loader');

        $caughtError2 = null;
        try {
            $this->awaitPromise($promise2, $failLoader);
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

        $keyA = [];
        $keyB = [];
        list($valueA, $valueB) = self::awaitPromise(\React\Promise\all([$identityLoader->load($keyA), $identityLoader->load($keyB)]), $identityLoader);
        $this->assertEquals($keyA, $valueA);
        $this->assertEquals($keyB, $valueB);
    }

    private static function getSimpleIdentityLoader()
    {
        return new DataLoader(
            new BatchLoadFn(function ($keys) {
                return \React\Promise\resolve($keys);
            })
        );
    }

    private static function errorLoader()
    {
        $loadCalls = new \ArrayObject();

        $errorLoader = new DataLoader(new BatchLoadFn(function ($keys) use (&$loadCalls) {
            $loadCalls[] = $keys;
            return \React\Promise\resolve(
                array_map(function ($key) {
                    return new \Exception("Error: $key");
                }, $keys)
            );
        }));

        return [$errorLoader, $loadCalls];
    }

    private static function eventLoader()
    {
        $loadCalls = new \ArrayObject();

        $evenLoader = new DataLoader(new BatchLoadFn(function ($keys) use (&$loadCalls) {
            $loadCalls[] = $keys;
            return \React\Promise\resolve(
                array_map(function ($key) {
                    return $key % 2 === 0 ? $key : new \Exception("Odd: $key");
                }, $keys)
            );
        }));

        return [$evenLoader, $loadCalls];
    }

    private static function idLoader(Option $options = null)
    {
        $loadCalls = new \ArrayObject();
        $batchLoadFn = new BatchLoadFn();

        $batchLoadFn->setBatchLoadFn(function ($keys) use (&$loadCalls) {
            $loadCalls[] = $keys;
            return \React\Promise\resolve($keys);
        });

        $identityLoader = new DataLoader($batchLoadFn, $options);

        return [$identityLoader, $loadCalls];
    }

    private function assertInstanceOfPromise($object)
    {
        $this->assertInstanceOf(Promise::class, $object);
    }

    private static function awaitPromise(PromiseInterface $promise, DataLoader $identityLoader)
    {
        return $identityLoader->await($promise);
    }
}
