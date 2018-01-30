# DataLoaderPHP

DataLoaderPHP is a generic utility to be used as part of your application's data
fetching layer to provide a simplified and consistent API over various remote
data sources such as databases or web services via batching and caching.

[![Build Status](https://travis-ci.org/overblog/dataloader-php.svg?branch=master)](https://travis-ci.org/overblog/dataloader-php)
[![Coverage Status](https://coveralls.io/repos/github/overblog/dataloader-php/badge.svg?branch=master)](https://coveralls.io/github/overblog/dataloader-php?branch=master)
[![Latest Stable Version](https://poser.pugx.org/overblog/dataloader-php/version)](https://packagist.org/packages/overblog/dataloader-php)
[![License](https://poser.pugx.org/overblog/dataloader-php/license)](https://packagist.org/packages/overblog/dataloader-php)

## Requirements

This library requires PHP >= 5.5 to work.

## Getting Started

First, install DataLoaderPHP using composer.

```sh
composer require "overblog/dataloader-php"
```

To get started, create a `DataLoader` object.

## Batching

Batching is not an advanced feature, it's DataLoader's primary feature.
Create loaders by providing a batch loading function.


```php
use Overblog\DataLoader\DataLoader;

$myBatchGetUsers = function ($keys) { /* ... */ };
$promiseAdapter = new MyPromiseAdapter();

$userLoader = new DataLoader($myBatchGetUsers, $promiseAdapter);
```

A batch loading callable / callback accepts an Array of keys, and returns a Promise which
resolves to an Array of values.

Then load individual values from the loader. DataLoaderPHP will coalesce all
individual loads which occur within a single frame of execution (using `await` method) 
and then call your batch function with all requested keys.

```php
$userLoader->load(1)
  ->then(function ($user) use ($userLoader) { return $userLoader->load($user->invitedByID); })
  ->then(function ($invitedBy) { echo "User 1 was invited by $invitedBy"; });

// Elsewhere in your application
$userLoader->load(2)
  ->then(function ($user) use ($userLoader) { return $userLoader->load($user->invitedByID); })
  ->then(function ($invitedBy) { echo "User 2 was invited by $invitedBy"; });

// Synchronously waits on the promise to complete, if not using EventLoop.
$userLoader->await(); // or `DataLoader::await()`
```
A naive application may have issued four round-trips to a backend for the
required information, but with DataLoaderPHP this application will make at most
two.

DataLoaderPHP allows you to decouple unrelated parts of your application without
sacrificing the performance of batch data-loading. While the loader presents an
API that loads individual values, all concurrent requests will be coalesced and
presented to your batch loading function. This allows your application to safely
distribute data fetching requirements throughout your application and maintain
minimal outgoing data requests.

#### Batch Function

A batch loading function accepts an Array of keys, and returns a Promise which
resolves to an Array of values. There are a few constraints that must be upheld:

 * The Array of values must be the same length as the Array of keys.
 * Each index in the Array of values must correspond to the same index in the Array of keys.

For example, if your batch function was provided the Array of keys: `[ 2, 9, 6, 1 ]`,
and loading from a back-end service returned the values:

```php
[
  ['id' => 9, 'name' => 'Chicago'],
  ['id' => 1, 'name' => 'New York'],
  ['id' => 2, 'name' => 'San Francisco']  
]
```

Our back-end service returned results in a different order than we requested, likely
because it was more efficient for it to do so. Also, it omitted a result for key `6`,
which we can interpret as no value existing for that key.

To uphold the constraints of the batch function, it must return an Array of values
the same length as the Array of keys, and re-order them to ensure each index aligns
with the original keys `[ 2, 9, 6, 1 ]`:

```php
[
  ['id' => 2, 'name' => 'San Francisco'],
  ['id' => 9, 'name' => 'Chicago'],
  null,
  ['id' => 1, 'name' => 'New York']
]
```


### Caching (current PHP instance)

DataLoader provides a memoization cache for all loads which occur in a single
request to your application. After `->load()` is called once with a given key,
the resulting value is cached to eliminate redundant loads.

In addition to relieving pressure on your data storage, caching results per-request
also creates fewer objects which may relieve memory pressure on your application:

```php
$userLoader =  new DataLoader(...);
$promise1A = $userLoader->load(1);
$promise1B = $userLoader->load(1);
var_dump($promise1A === $promise1B); // bool(true)
```

#### Clearing Cache

In certain uncommon cases, clearing the request cache may be necessary.

The most common example when clearing the loader's cache is necessary is after
a mutation or update within the same request, when a cached value could be out of
date and future loads should not use any possibly cached value.

Here's a simple example using SQL UPDATE to illustrate.

```php
use Overblog\DataLoader\DataLoader;

// Request begins...
$userLoader = new DataLoader(...);

// And a value happens to be loaded (and cached).
$userLoader->load(4)->then(...);

// A mutation occurs, invalidating what might be in cache.
$sql = 'UPDATE users WHERE id=4 SET username="zuck"';
if (true === $conn->query($sql)) {
  $userLoader->clear(4);
}

// Later the value load is loaded again so the mutated data appears.
$userLoader->load(4)->then(...);

// Request completes.
```

#### Caching Errors

If a batch load fails (that is, a batch function throws or returns a rejected
Promise), then the requested values will not be cached. However if a batch
function returns an `Error` instance for an individual value, that `Error` will
be cached to avoid frequently loading the same `Error`.

In some circumstances you may wish to clear the cache for these individual Errors:

```php
$userLoader->load(1)->then(null, function ($exception) {
  if (/* determine if error is transient */) {
    $userLoader->clear(1);
  }
  throw $exception;
});
```

#### Disabling Cache

In certain uncommon cases, a DataLoader which *does not* cache may be desirable.
Calling `new DataLoader(myBatchFn, new Option(['cache' => false ]))` will ensure that every
call to `->load()` will produce a *new* Promise, and requested keys will not be
saved in memory.

However, when the memoization cache is disabled, your batch function will
receive an array of keys which may contain duplicates! Each key will be
associated with each call to `->load()`. Your batch loader should provide a value
for each instance of the requested key.

For example:

```php
$myLoader = new DataLoader(function ($keys) {
  echo json_encode($keys);
  return someBatchLoadFn($keys);
}, $promiseAdapter, new Option(['cache' => false ]));

$myLoader->load('A');
$myLoader->load('B');
$myLoader->load('A');

// [ 'A', 'B', 'A' ]
```

More complex cache behavior can be achieved by calling `->clear()` or `->clearAll()`
rather than disabling the cache completely. For example, this DataLoader will
provide unique keys to a batch function due to the memoization cache being
enabled, but will immediately clear its cache when the batch function is called
so later requests will load new values.

```php
$myLoader = new DataLoader(function($keys) use ($identityLoader) {
  $identityLoader->clearAll();
  return someBatchLoadFn($keys);
}, $promiseAdapter);
```


## API

#### class DataLoader

DataLoaderPHP creates a public API for loading data from a particular
data back-end with unique keys such as the `id` column of a SQL table or
document name in a MongoDB database, given a batch loading function.

Each `DataLoaderPHP` instance contains a unique memoized cache. Use caution when
used in long-lived applications or those which serve many users with different
access permissions and consider creating a new instance per web request.

##### `new DataLoader(callable $batchLoadFn, PromiseAdapterInterface $promiseAdapter [, Option $options])`

Create a new `DataLoaderPHP` given a batch loading instance and options.

- *$batchLoadFn*: A callable / callback which accepts an Array of keys, and returns a Promise which resolves to an Array of values.
- *$promiseAdapter*: Any object that implements `Overblog\PromiseAdapter\PromiseAdapterInterface`. (see [Overblog/Promise-Adapter](./lib/promise-adapter/docs/usage.md))
- *$options*: An optional object of options:

  - *batch*: Default `true`. Set to `false` to disable batching, instead
    immediately invoking `batchLoadFn` with a single load key.

  - *maxBatchSize*: Default `Infinity`. Limits the number of items that get
    passed in to the `batchLoadFn`.

  - *cache*: Default `true`. Set to `false` to disable caching, instead
    creating a new Promise and new key in the `batchLoadFn` for every load.

  - *cacheKeyFn*: A function to produce a cache key for a given load key.
    Defaults to `key`. Useful to provide when an objects are keys
    and two similarly shaped objects should be considered equivalent.

  - *cacheMap*: An instance of `CacheMap` to be
    used as the underlying cache for this loader. Default `new CacheMap()`.

##### `load($key)`

Loads a key, returning a `Promise` for the value represented by that key.

- *$key*: An key value to load.

##### `loadMany($keys)`

Loads multiple keys, promising an array of values:

```php
list($a, $b) = DataLoader::await($myLoader->loadMany(['a', 'b']));
```

This is equivalent to the more verbose:

```php
list($a, $b) = DataLoader::await(\React\Promise\all([
  $myLoader->load('a'),
  $myLoader->load('b')
]));
```

- *$keys*: An array of key values to load.

##### `clear($key)`

Clears the value at `$key` from the cache, if it exists. Returns itself for
method chaining.

- *$key*: An key value to clear.

##### `clearAll()`

Clears the entire cache. To be used when some event results in unknown
invalidations across this particular `DataLoaderPHP`. Returns itself for
method chaining.

##### `prime($key, $value)`

Primes the cache with the provided key and value. If the key already exists, no
change is made. (To forcefully prime the cache, clear the key first with
`$loader->clear($key)->prime($key, $value)`. Returns itself for method chaining.

##### `static await([$promise][, $unwrap])`

You can synchronously force promises to complete using DataLoaderPHP's await method.
When an await function is invoked it is expected to deliver a value to the promise or reject the promise.
Await method process all waiting promise in all dataLoaderPHP instances.

- *$promise*: Optional promise to complete.

- *$unwrap*: controls whether or not the value of the promise is returned for a fulfilled promise
  or if an exception is thrown if the promise is rejected. Default `true`.

## Using with Webonyx/GraphQL

DataLoader pairs nicely well with [Webonyx/GraphQL](https://github.com/webonyx/graphql-php). GraphQL fields are
designed to be stand-alone functions. Without a caching or batching mechanism,
it's easy for a naive GraphQL server to issue new database requests each time a
field is resolved.

Consider the following GraphQL request:

```graphql
{
  me {
    name
    bestFriend {
      name
    }
    friends(first: 5) {
      name
      bestFriend {
        name
      }
    }
  }
}
```

Naively, if `me`, `bestFriend` and `friends` each need to request the backend,
there could be at most 13 database requests!

When using DataLoader, we could define the `User` type
at most 4 database requests,
and possibly fewer if there are cache hits.

```php
<?php
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Overblog\DataLoader\DataLoader;
use Overblog\DataLoader\Promise\Adapter\Webonyx\GraphQL\SyncPromiseAdapter;
use Overblog\PromiseAdapter\Adapter\WebonyxGraphQLSyncPromiseAdapter;

/**
* @var \PDO $dbh
*/
// ...

$graphQLPromiseAdapter = new SyncPromiseAdapter();
$dataLoaderPromiseAdapter = new WebonyxGraphQLSyncPromiseAdapter($graphQLPromiseAdapter);
$userLoader = new DataLoader(function ($keys) { /*...*/ }, $dataLoaderPromiseAdapter);

GraphQL::setPromiseAdapter($graphQLPromiseAdapter);

$userType = new ObjectType([
  'name' => 'User',
  'fields' => function () use (&$userType, $userLoader, $dbh) {
     return [
            'name' => ['type' => Type::string()],
            'bestFriend' => [
                'type' => $userType,
                'resolve' => function ($user) use ($userLoader) {
                    $userLoader->load($user['bestFriendID']);
                }
            ],
            'friends' => [
                'args' => [
                    'first' => ['type' => Type::int() ],
                ],
                'type' => Type::listOf($userType),
                'resolve' => function ($user, $args) use ($userLoader, $dbh) {
                    $sth = $dbh->prepare('SELECT toID FROM friends WHERE fromID=:userID LIMIT :first');
                    $sth->bindParam(':userID', $user['id'], PDO::PARAM_INT);
                    $sth->bindParam(':first', $args['first'], PDO::PARAM_INT);
                    $friendIDs = $sth->execute();

                    return $userLoader->loadMany($friendIDs);
                }
            ]
        ];
    }
]);
```
You can also see [an example](https://github.com/mcg-web/sandbox-dataloader-graphql-php).

## Using with Symfony

See the [bundle](https://github.com/overblog/dataloader-bundle).

## Credits

Overblog/DataLoaderPHP is a port of [dataLoader NodeJS version](https://github.com/facebook/dataloader)
by [Facebook](https://github.com/facebook).

Also, large parts of the documentation have been ported from the dataLoader NodeJS version
[Docs](https://github.com/facebook/dataloader/blob/master/README.md).

## License

Overblog/DataLoaderPHP is released under the [MIT](https://github.com/overblog/dataloader-php/blob/master/LICENSE) license.
