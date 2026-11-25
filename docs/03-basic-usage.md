<!-- nav-group: Basic usage -->
# Basic usage

A container is a class that names one kind of log. You wrap a block of code in it with `doing()`, and you log from inside the block.

## Defining a container

A container extends `LogContainer`. It needs a `key()`, which is stored on every entry it writes, and a `name()` for people to read.

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

## Wrapping a block

Pass `doing()` a label and a closure. The closure runs right away, and the code inside it doesn't have to change. In these examples `reserveStock()`, `chargeCard()` and `sendReceipt()` stand for your own code.

```php
<?php

use Keaton\LogContainers\Models\LogSession;

$order = ['id' => 42, 'total' => 80];

OrderLog::doing('fulfilling order 42', function () use ($order): void {
    reserveStock($order);
    chargeCard($order);
    sendReceipt($order);
});

$session = LogSession::query()->first();

echo "{$session->name}: {$session->status}", PHP_EOL;
echo $session->duration_ms === null ? 'not timed' : 'timed', PHP_EOL;
```

```text
fulfilling order 42: completed
timed
```

The block didn't log anything itself, and it still left a row behind. That row is a session. It stores the label, whether the block finished, and how long it took in `duration_ms`.

## Logging from inside the block

`doing()` passes the container into the closure. Every entry you write on it is linked to the session and has the label in its `doing` column.

```php
<?php

use Keaton\LogContainers\LogContainer;
use Keaton\LogContainers\Models\LogEntry;

$order = ['id' => 42, 'total' => 80];

OrderLog::doing('fulfilling order 42', function (LogContainer $log) use ($order): void {
    reserveStock($order);
    $log->info('Stock reserved');

    chargeCard($order);
    $log->success('Card charged', ['total' => $order['total']]);
});

foreach (LogEntry::forContainer('orders')->orderBy('id')->get() as $entry) {
    echo "{$entry->doing}: [{$entry->level}] {$entry->message}", PHP_EOL;
}

echo LogEntry::forContainer('orders')->where('context->total', 80)->count(), ' entry with a total of 80', PHP_EOL;
```

```text
fulfilling order 42: [info] Stock reserved
fulfilling order 42: [success] Card charged
1 entry with a total of 80
```

A container has the eight levels of the `Log` facade, plus `success()` and `exception()`, which stores a `Throwable` along with the message. Each call stores one row and returns it as a `LogEntry`. The level is a column and the context is a JSON column, so you can query both.

## Returning a value

`doing()` returns whatever the closure returns. That means you can wrap the body of a controller action or a job and return from inside the block.

```php
<?php

use Keaton\LogContainers\LogContainer;

$total = OrderLog::doing('pricing order 42', function (LogContainer $log): int {
    $total = 80 + 12;
    $log->info('Added shipping', ['shipping' => 12]);

    return $total;
});

echo $total, PHP_EOL;
```

```text
92
```

## When the block throws

A session fails when its block throws. The exception is stored as an entry and thrown again, unchanged, so your own error handling still runs. Calling `error()` doesn't fail a session.

```php
<?php

use Keaton\LogContainers\LogContainer;
use Keaton\LogContainers\Models\LogEntry;
use Keaton\LogContainers\Models\LogSession;

try {
    OrderLog::doing('charging order 42', function (LogContainer $log): void {
        $log->info('Contacting gateway');

        throw new RuntimeException('Gateway timed out');
    });
} catch (RuntimeException $e) {
    echo 'rethrown: ', $e->getMessage(), PHP_EOL;
}

echo LogSession::query()->first()->status, PHP_EOL;
echo LogEntry::forContainer('orders')->where('level', 'exception')->value('exception_message'), PHP_EOL;
```

```text
rethrown: Gateway timed out
failed
Gateway timed out
```

**Next:** [Sessions and scopes](04-sessions-and-scopes.md).
