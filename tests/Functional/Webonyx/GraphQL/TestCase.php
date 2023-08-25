<?php

/*
 * This file is part of the DataLoaderPhp package.
 *
 * (c) Overblog <http://github.com/overblog/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Overblog\DataLoader\Test\Functional\Webonyx\GraphQL;

use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\GraphQL;
use Overblog\DataLoader\DataLoader;
use Overblog\PromiseAdapter\PromiseAdapterInterface;
use PHPUnit\Framework\Attributes\DataProvider;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    private static $fixtures = null;

    public static function getFixtures()
    {
        if (null === self::$fixtures) {
            $fixturesFiles = self::listFiles(__DIR__.'/fixtures');
            self::$fixtures = [];
            foreach ($fixturesFiles as $file) {
                $pathInfo = pathinfo($file);
                $group = basename($pathInfo['dirname']);
                $key = $pathInfo['filename'];

                $content = file_get_contents($file);

                if ('json' === $pathInfo['extension']) {
                    $content = json_decode($content, true);
                }
                self::$fixtures[$group][$key] = $content;
                unset($group, $key, $content, $pathInfo);
            }
            unset($fixturesFiles);
        }

        return self::$fixtures;
    }

    private static function listFiles($dir, &$results = [])
    {
        $files = scandir($dir);

        foreach ($files as $key => $value) {
            $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
            if (!is_dir($path)) {
                $results[] = $path;
            } elseif (!in_array($value, ['.', '..'])) {
                self::listFiles($path, $results);
            }
        }

        return $results;
    }

    /**
     * @param array  $expectedMetrics
     * @param string $query
     * @param array  $expectedResponse
     */
    #[DataProvider('getFixtures')]
    public function testExecute(array $expectedMetrics, $query, array $expectedResponse)
    {
        $metrics = [
            'calls' => 0,
            'callsIds' => [],
        ];

        $graphQLPromiseAdapter = $this->createGraphQLPromiseAdapter();
        $dataLoaderPromiseAdapter = $this->createDataLoaderPromiseAdapter($graphQLPromiseAdapter);
        $dataLoader = $this->createDataLoader($dataLoaderPromiseAdapter, $metrics['callsIds'], $metrics['calls']);
        $schema = Schema::build($dataLoader);

        $result = GraphQL::promiseToExecute($graphQLPromiseAdapter, $schema, $query);
        if ($graphQLPromiseAdapter instanceof SyncPromiseAdapter) {
            $result = $graphQLPromiseAdapter->wait($result)->toArray();
        } else {
            $result = $result->then(static function (ExecutionResult $r) : array {
                return $r->toArray();
            });
        }
        if ($result instanceof Promise) {
            $result = DataLoader::await($result);
        }

        $this->assertEquals($expectedResponse, $result);
        $this->assertEquals($expectedMetrics, $metrics);

        $dataLoader->clearAll();
        unset($dataLoader);
    }

    abstract protected function createGraphQLPromiseAdapter();

    abstract protected function createDataLoaderPromiseAdapter(PromiseAdapter $graphQLPromiseAdapter);

    protected function createDataLoader(PromiseAdapterInterface $dataLoaderPromiseAdapter, &$callsIds, &$calls)
    {
        $batchLoadFn = function ($ids) use (&$calls, &$callsIds, $dataLoaderPromiseAdapter) {
            $callsIds[] = $ids;
            ++$calls;
            $allCharacters = StarWarsData::humans() + StarWarsData::droids();
            $characters = array_intersect_key($allCharacters, array_flip($ids));

            return $dataLoaderPromiseAdapter->createAll(array_values($characters));
        };
        return new DataLoader($batchLoadFn, $dataLoaderPromiseAdapter);
    }
}
