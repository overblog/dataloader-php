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

        $promiseAll = $identityLoader->loadMany([ 1, 2 ]);
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

        list($a3, $b2, $c2) = self::awaitPromise(\React\Promise\all([$identityLoader->load('A'),  $identityLoader->load('B'), $identityLoader->load('C')]), $identityLoader);
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

    private static function getSimpleIdentityLoader()
    {
        return new DataLoader(
            new BatchLoadFn(function ($keys) {
                return \React\Promise\resolve($keys);
            })
        );
    }

    private static function idLoader(Option $options = null)
    {
        $loadCalls = new \ArrayObject();
        $identityLoader = new DataLoader(
            new BatchLoadFn(function ($keys) use (&$loadCalls) {
                $loadCalls[] = $keys;
                return \React\Promise\resolve($keys);
            }),
            $options
        );

        return [$identityLoader, $loadCalls];
    }

    private function assertInstanceOfPromise($object)
    {
        $this->assertInstanceOf(Promise::class, $object);
    }

    private static function awaitPromise(PromiseInterface $promise, DataLoader $identityLoader)
    {
        $resolvedValue = null;

        $promise->then(function ($values) use (&$resolvedValue) {
            $resolvedValue = $values;
        }, function ($e) {
            throw $e;
        });
        $identityLoader->process();

        return $resolvedValue;
    }

}
