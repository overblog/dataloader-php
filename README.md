# DataLoaderPHP

DataLoaderPHP is a generic utility to be used as part of your application's data
fetching layer to provide a simplified and consistent API over various remote
data sources such as databases or web services via batching and caching.

[![Build Status](https://travis-ci.org/overblog/dataloader-php.svg?branch=master)](https://travis-ci.org/overblog/dataloader-php)
[![Coverage Status](https://coveralls.io/repos/github/overblog/dataloader-php/badge.svg?branch=master)](https://coveralls.io/github/overblog/dataloader-php?branch=master)
[![Latest Stable Version](https://poser.pugx.org/overblog/dataloader-php/version)](https://packagist.org/packages/overblog/dataloader-php)
[![License](https://poser.pugx.org/overblog/dataloader-php/license)](https://packagist.org/packages/overblog/dataloader-php)

## Requirements

* This library require [React/Promise](https://github.com/reactphp/promise) and PHP >= 5.5 to works.
* The [React/EventLoop](https://github.com/reactphp/event-loop) component are **totally optional** (see `await` method for more details).

## Getting Started

First, install DataLoaderPHP using composer.

```sh
composer require "overblog/dataloader-php"
```

To get started, create a `DataLoader` object.

Batching is not an advanced feature, it's DataLoaderPHP's primary feature.
Create loaders by providing a batch loading instance.


```php
use Overblog\DataLoader\DataLoader;

$myBatchGetUsers = function ($keys) { /* ... */ };

$userLoader = new DataLoader($myBatchGetUsers);
```

A batch loading callable / callback accepts an Array of keys, and returns a Promise which
resolves to an Array of values.

Then load individual values from the loader. DataLoaderPHP will coalesce all
individual loads which occur within a single frame of execution (a single tick
of the event loop if install or using `await` method) and then call your batch function with all requested keys.

```php
$userLoader->load(1)
  ->then(function ($user) use ($userLoader) { $userLoader->load($user->invitedByID); })
  ->then(function ($invitedBy) { echo "User 1 was invited by $invitedBy"; }));

// Elsewhere in your application
$userLoader->load(2)
  ->then(function ($user) use ($userLoader) { $userLoader->load($user->invitedByID); })
  ->then(function ($invitedBy) { echo "User 2 was invited by $invitedBy"; }));

// Synchronously waits on the promise to complete, if not using EventLoop.
$userLoader->await();
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

### Caching (current PHP instance)

After being loaded once, the resulting value is cached, eliminating
redundant requests.

In the example above, if User `1` was last invited by User `2`, only a single
round trip will occur.

Caching results in creating fewer objects which may relieve memory pressure on
your application:

```php
$promise1A = $userLoader->load(1);
$promise1B = $userLoader->load(1);
var_dump($promise1A === $promise1B); // bool(true)
```

There are two common examples when clearing the loader's cache is necessary:

*Mutations:* after a mutation or update, a cached value may be out of date.
Future loads should not use any possibly cached value.

Here's a simple example using SQL UPDATE to illustrate.

```php
$sql = 'UPDATE users WHERE id=4 SET username="zuck"';
if (true === $conn->query($sql)) {
  $userLoader->clear(4);
}
```

*Transient Errors:* A load may fail because it simply can't be loaded
(a permanent issue) or it may fail because of a transient issue such as a down
database or network issue. For transient errors, clear the cache:

```php
$userLoader->load(1)->otherwise(function ($exception) {
  if (/* determine if error is transient */) {
    $userLoader->clear(1);
  }
  throw $exception;
});
```

## API

#### class DataLoader

DataLoaderPHP creates a public API for loading data from a particular
data back-end with unique keys such as the `id` column of a SQL table or
document name in a MongoDB database, given a batch loading function.

Each `DataLoaderPHP` instance contains a unique memoized cache. Use caution when
used in long-lived applications or those which serve many users with different
access permissions and consider creating a new instance per web request.

##### `new DataLoader(callable $batchLoadFn [, Option $options])`

Create a new `DataLoaderPHP` given a batch loading instance and options.

- *$batchLoadFn*: A callable / callback which accepts an Array of keys, and returns a Promise which resolves to an Array of values.
- *$options*: An optional object of options:

  - *batch*: Default `true`. Set to `false` to disable batching, instead
    immediately invoking `batchLoadFn` with a single load key.

  - *maxBatchSize*: Default `Infinity`. Limits the number of items that get
    passed in to the `batchLoadFn`.

  - *cache*: Default `true`. Set to `false` to disable caching, instead
    creating a new Promise and new key in the `batchLoadFn` for every load.

  - *cacheKeyFn*: A function to produce a cache key for a given load key.
    Defaults to `key => key`. Useful to provide when JavaScript objects are keys
    and two similarly shaped objects should be considered equivalent.

  - *cacheMap*: An instance of `CacheMap` to be
    used as the underlying cache for this loader. Default `new CacheMap()`.

##### `load($key)`

Loads a key, returning a `Promise` for the value represented by that key.

- *$key*: An key value to load.

##### `loadMany($keys)`

Loads multiple keys, promising an array of values:

```php
list($a, $b) = DataLoader::await($myLoader->loadMany(['a', 'b']);
```

This is equivalent to the more verbose:

```js
list($a, $b) = DataLoader::await(\React\Promise\all([
  $myLoader->load('a'),
  $myLoader->load('b')
]);
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
`$loader->clear($key)->prime($key, $value)`.) Returns itself for method chaining.

##### `static await([$promise][, $unwrap])`

You can synchronously force promises to complete using DataLoaderPHP's await method.
When an await function is invoked it is expected to deliver a value to the promise or reject the promise.
Await method process all waiting promise in all dataLoaderPHP instances.

- *$promise*: Optional promise to complete.

- *$unwrap*: controls whether or not the value of the promise is returned for a fulfilled promise
  or if an exception is thrown if the promise is rejected. Default `true`.

## Using with Webonyx/GraphQL [WIP]

A [PR](https://github.com/webonyx/graphql-php/pull/67) is open on [Webonyx/GraphQL](https://github.com/webonyx/graphql-php)
to supports DataLoaderPHP and more generally promise.
Here [an example](https://github.com/mcg-web/sandbox-dataloader-graphql-php/blob/master/with-dataloader.php).

## Credits

Overblog/DataLoaderPHP is a port of [dataLoader NodeJS version](https://github.com/facebook/dataloader)
by [Facebook](https://github.com/facebook).

Also, large parts of the documentation have been ported from the dataLoader NodeJS version
[Docs](https://github.com/facebook/dataloader/blob/master/README.md).

## License

Overblog/DataLoaderPHP is released under the [MIT](https://github.com/overblog/dataloader-php/blob/master/LICENSE) license.
