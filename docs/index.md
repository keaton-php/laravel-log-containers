<!-- nav-group: Start here -->
# Introduction

Laravel Log Containers lets you wrap a block of code in a log container. Everything that happens inside the block is logged together: what ran, how long it took, whether it finished, and the exception if it didn't. The code inside the block doesn't change.

## Before and after

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

The block is stored as a session, which is a database row with a name, a status and a duration. Every entry is a row too, linked to its session. `OrderLog` is a container, a small class that names one kind of log. [Basic usage](03-basic-usage.md) shows how to write one.

## Features

- `doing()` wraps a block, times it and records whether it finished. If the block throws, the exception is stored and thrown again.
- `during()` names what was going on around the work, like a checkout or a nightly import.
- Blocks nest, so a feature can be split into steps that are each timed on their own and linked to the block around them.
- Code that's called from inside a block joins it with a plain `OrderLog::info()`, so you don't pass the container around.
- Every entry and every session is a row, so you can query them with Eloquent.
- `tagWith()` links entries to an Eloquent model, so you can list everything that happened to a record.
- Every entry stores an execution id, which ties together the entries of one request, job or command.
- A watch is a class that fires an event when an entry you care about is written.
- A container can redact secrets from the context, limit how many entries it keeps and delete old ones.
- `LogContainers::fake()` and a few assertions for your tests.

## When to use it

Use this package for work you want to look at later, like a checkout, an import, a webhook or a job that sometimes fails.

For ordinary logging, `Log::info()` is still the right tool. A container can also write its entries to the Laravel log.

**Next:** [Installation](02-installation.md).
