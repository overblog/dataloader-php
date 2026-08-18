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

use Overblog\DataLoader\CacheMap;
use Overblog\DataLoader\DataLoader;
use Overblog\DataLoader\Option;
use Overblog\PromiseAdapter\Adapter\GuzzleHttpPromiseAdapter;
use Overblog\PromiseAdapter\Adapter\ReactPromiseAdapter;
use Overblog\PromiseAdapter\PromiseAdapterInterface;

final class ReactDataLoadTest extends DataLoadTestCase
{
    protected function createPromiseAdapter(): PromiseAdapterInterface
    {
        return new ReactPromiseAdapter();
    }

    public function testAwaitUsesTheAdapterThatRecognizesThePromiseAndDispatchesAllLiveLoaders()
    {
        $reactAdapter = new ReactPromiseAdapter();
        $reactLoadCalls = new \ArrayObject();
        $reactLoader = new DataLoader(function ($keys) use ($reactAdapter, $reactLoadCalls) {
            $reactLoadCalls[] = $keys;

            return $reactAdapter->createFulfilled($keys);
        }, $reactAdapter);

        $guzzleAdapter = new GuzzleHttpPromiseAdapter();
        $guzzleLoadCalls = new \ArrayObject();
        $guzzleLoader = new DataLoader(function ($keys) use ($guzzleAdapter, $guzzleLoadCalls) {
            $guzzleLoadCalls[] = $keys;

            return $guzzleAdapter->createFulfilled($keys);
        }, $guzzleAdapter);

        $reactLoader->load('react');
        $guzzlePromise = $guzzleLoader->load('guzzle');
        $guzzleLoaderReference = \WeakReference::create($guzzleLoader);
        unset($guzzleLoader);

        $this->assertSame('guzzle', DataLoader::await($guzzlePromise));
        $this->assertSame([['react']], $reactLoadCalls->getArrayCopy());
        $this->assertSame([['guzzle']], $guzzleLoadCalls->getArrayCopy());

        gc_collect_cycles();

        $this->assertNull($guzzleLoaderReference->get());
    }

    public function testForeignCacheHitDoesNotReplaceThePromiseAdapter()
    {
        $cacheMap = new CacheMap();

        $guzzleAdapter = new GuzzleHttpPromiseAdapter();
        $guzzleLoadCalls = new \ArrayObject();
        $guzzleLoader = new DataLoader(function ($keys) use ($guzzleAdapter, $guzzleLoadCalls) {
            $guzzleLoadCalls[] = $keys;

            return $guzzleAdapter->createFulfilled($keys);
        }, $guzzleAdapter, new Option(['cacheMap' => $cacheMap]));

        $reactAdapter = new ReactPromiseAdapter();
        $reactLoadCalls = new \ArrayObject();
        $reactLoader = new DataLoader(function ($keys) use ($reactAdapter, $reactLoadCalls) {
            $reactLoadCalls[] = $keys;

            return $reactAdapter->createFulfilled($keys);
        }, $reactAdapter, new Option(['cacheMap' => $cacheMap]));

        $guzzlePromise = $guzzleLoader->load('shared');
        $cachedPromise = $reactLoader->load('shared');

        $this->assertSame($guzzlePromise, $cachedPromise);
        $this->assertSame('shared', DataLoader::await($cachedPromise));
        $this->assertSame([['shared']], $guzzleLoadCalls->getArrayCopy());
        $this->assertSame([], $reactLoadCalls->getArrayCopy());
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
