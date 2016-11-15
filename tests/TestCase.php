<?php

/*
 * This file is part of the DataLoaderPhp package.
 *
 * (c) Overblog <http://github.com/overblog/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Overblog\DataLoader\Tests;

use McGWeb\PromiseFactory\Factory\GuzzleHttpPromiseFactory;
use McGWeb\PromiseFactory\PromiseFactoryInterface;

class TestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PromiseFactoryInterface
     */
    protected static $promiseFactory;

    public function setUp()
    {
        self::$promiseFactory = new GuzzleHttpPromiseFactory();
    }

}
