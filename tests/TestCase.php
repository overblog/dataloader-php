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
use Overblog\PromiseAdapter\PromiseAdapterInterface;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @var PromiseAdapterInterface
     */
    protected static $promiseAdapter;

    public function setUp(): void
    {
        self::$promiseAdapter = $this->createPromiseAdapter();
    }

    protected function tearDown(): void
    {
        $instances = new \ReflectionProperty(DataLoader::class, 'instances');
        $instances->setValue(null);

        $activeInstances = new \ReflectionProperty(DataLoader::class, 'activeInstances');
        $activeInstances->setValue([]);

        $promiseAdapters = new \ReflectionProperty(DataLoader::class, 'promiseAdapters');
        $promiseAdapters->setValue(null);

        parent::tearDown();
    }

    abstract protected function createPromiseAdapter(): PromiseAdapterInterface;
}
