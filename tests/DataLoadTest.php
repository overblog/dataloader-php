<?php

namespace Overblog\DataLoader\Tests;

use Overblog\DataLoader\BatchLoadFn;
use Overblog\DataLoader\DataLoader;
use Overblog\DataLoader\Option;
use React\Promise\Promise;

class DataLoadTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @group primary-api
     */
    public function testBuildsAReallyReallySimpleDataLoader()
    {
        $identityLoader = new DataLoader(
            new BatchLoadFn(function ($keys) {
                return \React\Promise\resolve($keys);
            })
        );

        $promise1 = $identityLoader->load(1);
        $this->assertInstanceOf('React\\Promise\\Promise', $promise1);

        $value1 = null;

        $promise1->then(function ($value) use (&$value1) {
            $value1 = $value;
        }, function ($e) {
            throw $e;
        });
        $identityLoader->process();

        $this->assertEquals(1, $value1);
    }

    public static function idLoader(Option $options)
    {
        $loadCalls = [];
        $identityLoader = new DataLoader(
            new BatchLoadFn(function ($keys) use (&$loadCalls) {
                $loadCalls[] = $keys;
                return \React\Promise\resolve($keys);
            }),
            $options
        );

        return [$identityLoader, $loadCalls];
    }
}
