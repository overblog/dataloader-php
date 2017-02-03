# Promise adapter usage

## Optional requirements

Optional to use Guzzle:

```sh
composer require "guzzlehttp/promises"
```

Optional to use ReactPhp:

```sh
composer require "react/promise"
```

## Supported Adapter

*Guzzle*: `Overblog\PromiseAdapter\Adapter\GuzzleHttpPromiseAdapter`

*ReactPhp*: `Overblog\PromiseAdapter\Adapter\ReactPromiseAdapter`

To use a custom Promise lib you can implement `Overblog\PromiseAdapter\PromiseAdapterInterface`
