# Laravel Log Containers

[![Latest Version on Packagist](https://img.shields.io/packagist/v/keaton-php/laravel-log-containers.svg?style=flat-square)](https://packagist.org/packages/keaton-php/laravel-log-containers)
[![Tests](https://img.shields.io/github/actions/workflow/status/keaton-php/laravel-log-containers/run-tests.yml?branch=1.x&label=tests&style=flat-square)](https://github.com/keaton-php/laravel-log-containers/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/keaton-php/laravel-log-containers.svg?style=flat-square)](https://packagist.org/packages/keaton-php/laravel-log-containers)

Wrap a block of code in a log container, and everything that happens inside it is logged together. You get what ran, how long it took, whether it finished, and the exception if it didn't. The code inside the block doesn't change.

Say this is the code that fulfills an order:

```php
<?php

$order = ['id' => 42, 'total' => 80];

reserveStock($order);
chargeCard($order);
sendReceipt($order);
```

Here's the same code wrapped in a container. The container is passed into the closure, so you can log from inside the block as you go:

```php
<?php

use Keaton\LogContainers\LogContainer;
use Keaton\LogContainers\Models\LogSession;

$order = ['id' => 42, 'total' => 80];

OrderLog::doing('fulfilling order 42', function (LogContainer $log) use ($order): void {
    reserveStock($order);
    $log->info('Stock reserved');

    chargeCard($order);
    $log->info('Card charged', ['total' => $order['total']]);

    sendReceipt($order);
    $log->success('Receipt sent');
});

$session = LogSession::query()->first();

echo "{$session->name}: {$session->status}, {$session->entry_count} entries", PHP_EOL;

foreach ($session->entries as $entry) {
    echo '  ', str_pad($entry->level, 9), $entry->message, PHP_EOL;
}
```

```text
fulfilling order 42: completed, 3 entries
  info     Stock reserved
  info     Card charged
  success  Receipt sent
```

The block is stored as a session, which is a database row with a name, a status and a duration. Every entry is a row too, linked to its session, so you can query your logs with Eloquent and don't have to search a file.

If the block throws, the session is marked `failed` and the exception is stored with its class, message and trace. Then it's thrown again, so your own error handling still runs.

`OrderLog` is a container, a small class that names one kind of log:

```php
<?php

use Keaton\LogContainers\LogContainer;

class OrderLog extends LogContainer
{
    public static function key(): string
    {
        return 'orders';
    }

    public static function name(): string
    {
        return 'Orders';
    }
}
```

## Doing and during

`doing()` names the work and times it. `during()` names what was going on around it, like a checkout, a nightly import or a webhook. Both put their label on every entry written inside them, and you can nest them.

```php
<?php

use Keaton\LogContainers\LogContainer;
use Keaton\LogContainers\Models\LogEntry;

OrderLog::during('checkout', function (LogContainer $log): void {
    $log->doing('validating cart', function (LogContainer $log): void {
        $log->info('Cart is valid', ['items' => 3]);
    });

    $log->doing('charging card', function (LogContainer $log): void {
        $log->info('Contacting gateway');
        $log->warning('Gateway was slow', ['ms' => 2400]);
    });
});

foreach (LogEntry::forContainer('orders')->orderBy('id')->get() as $entry) {
    echo "{$entry->during} > {$entry->doing}: {$entry->message}", PHP_EOL;
}
```

```text
checkout > validating cart: Cart is valid
checkout > charging card: Contacting gateway
checkout > charging card: Gateway was slow
```

So the setup is to name the situation with `during()`, wrap the work and its steps in `doing()`, and log on the container you're handed. Each `doing()` block gets its own timed session. The labels are columns, so you can list entries by step, by situation or by session with a query.

## What else it does

- Code that's called from inside a block joins it with a plain `OrderLog::info()`, and a block inside a block is stored as its child session, so you can follow a run from the top down.
- `tagWith($order)` links entries to an Eloquent model, so you can list everything that happened to a record.
- Every entry stores an execution id, which ties together the entries of one request, job or command across containers.
- A container can redact secrets from the context, limit how many entries it keeps and delete old ones.
- A watch is a small class that fires an event when an entry you care about is written.
- You don't need a block. `OrderLog::info('Order placed', ['order_id' => 42])` stores one entry.
- `LogContainers::fake()` and a few assertions for your tests.

The package is meant for work you want to look at later, like a checkout, an import or a webhook. For everything else, `Log::info()` is still the right tool.

## Installation

You can install the package via composer:

```bash
composer require keaton-php/laravel-log-containers
```

The package requires PHP 8.2 or higher and Laravel 11, 12 or 13.

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag=log-containers-migrations
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag=log-containers-config
```

## Documentation

You'll find the documentation on [kevineaton.dev/docs/laravel-log-containers](https://kevineaton.dev/docs/laravel-log-containers/). The same pages are in the [docs](docs) folder of this repository:

- [Introduction](docs/index.md)
- [Installation](docs/02-installation.md)
- [Basic usage](docs/03-basic-usage.md)
- [Sessions and scopes](docs/04-sessions-and-scopes.md)
- [Tags and correlation](docs/05-tags-and-correlation.md)
- [Watches](docs/06-watches.md)
- [Redaction and retention](docs/07-redaction-and-retention.md)
- [Reference](docs/08-reference.md)

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Kevin Eaton](https://github.com/keaton-php)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
