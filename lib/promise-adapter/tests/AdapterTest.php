<?php

/*
 * This file is part of the DataLoaderPhp package.
 *
 * (c) Overblog <http://github.com/overblog/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Overblog\PromiseAdapter\Tests;

use Overblog\PromiseAdapter\Adapter\GuzzleHttpPromiseAdapter;
use Overblog\PromiseAdapter\Adapter\ReactPromiseAdapter;
use Overblog\PromiseAdapter\Adapter\WebonyxGraphQLSyncPromiseAdapter;
use Overblog\PromiseAdapter\PromiseAdapterInterface;

class AdapterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider AdapterDataProvider
     * @param string $promiseClass
     * @param PromiseAdapterInterface $Adapter
     * @param string $context
     */
    public function testCreate(PromiseAdapterInterface $Adapter, $context, $promiseClass)
    {
        $promise = $Adapter->create($resolve, $reject);

        $this->assertInstanceOf($promiseClass, $promise, $context);
        $this->assertTrue(is_callable($resolve), $context);
        $this->assertTrue(is_callable($reject), $context);
    }

    /**
     * @dataProvider AdapterDataProvider
     * @param PromiseAdapterInterface $Adapter
     * @param $message
     */
    public function testResolveCreatedPromise(PromiseAdapterInterface $Adapter, $message)
    {
        $promise = $Adapter->create($resolve, $reject);
        $expectResolvedValue = 'Resolve value';
        $resolve($expectResolvedValue);
        $resolvedValue = $Adapter->await($promise);

        $this->assertEquals($expectResolvedValue, $resolvedValue, $message);
    }

    /**
     * @dataProvider AdapterDataProvider
     * @param PromiseAdapterInterface $Adapter
     * @param string $context
     */
    public function testRejectCreatedPromise(PromiseAdapterInterface $Adapter, $context)
    {
        $promise = $Adapter->create($resolve, $reject);

        $expectRejectionReason = new \Exception('Error!');
        $reject($expectRejectionReason);

        $rejectionReason = $Adapter->await($promise, false);
        $this->assertEquals($expectRejectionReason, $rejectionReason, $context);
    }

    /**
     * @dataProvider AdapterDataProvider
     * @param PromiseAdapterInterface $Adapter
     * @param string $context
     * @param string $promiseClass
     */
    public function testCreateAll(PromiseAdapterInterface $Adapter, $context, $promiseClass)
    {
        $values = ['A', 'B', 'C'];
        $promise = $Adapter->createAll($values);
        $this->assertInstanceOf($promiseClass, $promise, $context);

        $resolvedValue = $Adapter->await($promise);

        $this->assertEquals($values, $resolvedValue, $context);
    }

    /**
     * @dataProvider AdapterDataProvider
     * @param PromiseAdapterInterface $Adapter
     * @param string $context
     * @param string $promiseClass
     */
    public function testCreateFulfilled(PromiseAdapterInterface $Adapter, $context, $promiseClass)
    {
        $value = 'resolved!';
        $promise = $Adapter->createFulfilled($value);
        $this->assertInstanceOf($promiseClass, $promise, $context);

        $resolvedValue = $Adapter->await($promise);
        $this->assertEquals($value, $resolvedValue, $context);
    }

    /**
     * @dataProvider AdapterDataProvider
     * @param PromiseAdapterInterface $Adapter
     * @param string $context
     * @param string $promiseClass
     */
    public function testCreatedRejected(PromiseAdapterInterface $Adapter, $context, $promiseClass)
    {
        $expectRejectionReason = new \Exception('Error!');
        $promise = $Adapter->createRejected($expectRejectionReason);
        $this->assertInstanceOf($promiseClass, $promise, $context);

        $rejectionReason = $Adapter->await($promise, false);
        $this->assertEquals($expectRejectionReason, $rejectionReason, $context);
    }

    /**
     * @dataProvider AdapterDataProvider
     * @param PromiseAdapterInterface $Adapter
     * @param string $context
     */
    public function testIsPromise(PromiseAdapterInterface $Adapter, $context)
    {
        $promise = $Adapter->create();

        $this->assertTrue($Adapter->isPromise($promise, true), $context);
        $this->assertFalse($Adapter->isPromise([]), $context);
        $this->assertFalse($Adapter->isPromise(new \stdClass()), $context);
    }

    /**
     * @dataProvider AdapterDataProvider
     * @param PromiseAdapterInterface $Adapter
     * @param string $context
     */
    public function testAwaitWithoutPromise(PromiseAdapterInterface $Adapter, $context)
    {
        $expected = 'expected value';
        $promise = $Adapter->createFulfilled($expected);
        $actual = null;

        $promise->then(function ($value) use (&$actual) {
            $actual = $value;
        });

        $Adapter->await();

        $this->assertEquals($expected, $actual, $context);
    }

    /**
     * @dataProvider AdapterDataProvider
     * @param PromiseAdapterInterface $Adapter
     *
     * @expectedException \Exception
     * @expectedExceptionMessage error!
     */
    public function testAwaitWithUnwrap(PromiseAdapterInterface $Adapter)
    {
        $expected = new \Exception('error!');
        $promise = $Adapter->createRejected($expected);

        $Adapter->await($promise, true);
    }

    /**
     * @dataProvider AdapterDataProvider
     * @param PromiseAdapterInterface $Adapter
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage ::await" method must be called with a Promise ("then" method).
     */
    public function testAwaitWithInvalidPromise(PromiseAdapterInterface $Adapter)
    {
        $Adapter->await(new \stdClass(), true);
    }

    /**
     * @dataProvider AdapterDataProvider
     * @param PromiseAdapterInterface $Adapter
     *
     * @expectedException \Exception
     * @expectedExceptionMessage Cancel promise!
     */
    public function testCancel(PromiseAdapterInterface $Adapter)
    {
        $promise = $Adapter->create($resolve, $reject, function () {
            throw new \Exception('Cancel promise!');
        });

        $Adapter->cancel($promise);
        $Adapter->await($promise, true);
    }

    /**
     * @dataProvider AdapterDataProvider
     * @param PromiseAdapterInterface $Adapter
     *
     * @expectedException \Exception
     * @expectedExceptionMessage ::cancel" method must be called with a compatible Promise.
     */
    public function testCancelInvalidPromise(PromiseAdapterInterface $Adapter)
    {
        $Adapter->create($resolve, $reject, function () {
            throw new \Exception('Cancel will never be called!');
        });

        $Adapter->cancel(new \stdClass());
    }

    public function AdapterDataProvider()
    {
        return [
            [new GuzzleHttpPromiseAdapter(), 'guzzle', 'GuzzleHttp\\Promise\\PromiseInterface'],
            [new ReactPromiseAdapter(), 'react', 'React\\Promise\\PromiseInterface'],
            [new WebonyxGraphQLSyncPromiseAdapter(), 'webonyx', 'GraphQL\\Executor\\Promise\\Promise'],
        ];
    }
}
