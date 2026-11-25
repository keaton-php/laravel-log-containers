<!-- nav-group: Basic usage -->
# Sessions and scopes

`doing()` names the work. `during()` names what the work was part of. You can nest both, so a feature can be logged as steps inside a bigger block.

## What the work was part of

The same task can run in different situations. A card can be charged during a checkout, and again during a nightly retry. `during()` puts that situation on every entry in the block, in the `during` column, so you can tell the two apart later.

```php
<?php

use Keaton\LogContainers\LogContainer;
use Keaton\LogContainers\Models\LogEntry;

$charge = function (LogContainer $log): void {
    $log->doing('charging card', function (LogContainer $log): void {
        $log->info('Card charged');
    });
};

OrderLog::during('checkout', $charge);
OrderLog::during('nightly retry', $charge);

foreach (LogEntry::forContainer('orders')->orderBy('id')->get() as $entry) {
    echo "{$entry->during} > {$entry->doing}: {$entry->message}", PHP_EOL;
}

echo LogEntry::forContainer('orders')->where('during', 'nightly retry')->count(), ' from the nightly retry', PHP_EOL;
```

```text
checkout > charging card: Card charged
nightly retry > charging card: Card charged
1 from the nightly retry
```

`during()` runs its closure and returns its value, like `doing()`, and it logs an exception and throws it again in the same way. It doesn't create a session. To add fields to every entry in a block, call `context()` first, like `OrderLog::context(['ip' => $ip])->during('checkout', $charge)`.

## Nesting

A `doing()` block can contain other `doing()` blocks, and `during()` blocks too, as deep as you like.

```php
<?php

use Keaton\LogContainers\LogContainer;
use Keaton\LogContainers\Models\LogSession;

OrderLog::doing('fulfilling order 42', function (LogContainer $log): void {
    $log->doing('reserving stock', function (LogContainer $log): void {
        $log->info('2 items reserved');
    });

    $log->doing('charging card', function (LogContainer $log): void {
        $log->during('fraud check', function (LogContainer $log): void {
            $log->info('Address matches');
        });
        $log->doing('contacting gateway', function (LogContainer $log): void {
            $log->success('Card charged');
        });
    });

    $log->success('Order fulfilled');
});

$print = function (LogSession $session, int $depth = 0) use (&$print): void {
    echo str_repeat('  ', $depth), "{$session->name}: {$session->status}, {$session->entry_count} logged", PHP_EOL;
    foreach ($session->children as $step) {
        $print($step, $depth + 1);
    }
};

$print(LogSession::query()->whereNull('parent_id')->first());
```

```text
fulfilling order 42: completed, 1 logged
  reserving stock: completed, 1 logged
  charging card: completed, 1 logged
    contacting gateway: completed, 1 logged
```

Each `doing()` block is a session of its own, so every step is timed and has its own status. It's a child of the block around it, so you can walk a run with `children` and `parent`. A `during()` block only labels its entries, so `fraud check` isn't a session.

When a block ends, the label, session and tags of the block around it are restored. That's why `Order fulfilled` belongs to the outer session. When a block throws, it fails, and so does every block around it that doesn't catch the exception. The parent can belong to another container, so a job's session can be the parent of an order's.

## Logging from deeper code

The code you wrap often calls other classes, and sometimes you want to keep logging from inside them. You don't have to do anything special, and you don't have to pass the container down through every call. Use the static version, like `OrderLog::info()`, and your entries land in the block that's running. `log_container(OrderLog::class)` does the same.

```php
<?php

class ReserveStock
{
    public function handle(int $orderId): void
    {
        OrderLog::info('Stock reserved', ['order_id' => $orderId]);
    }
}
```

```php
<?php

use Keaton\LogContainers\Models\LogEntry;

OrderLog::doing('fulfilling order 42', function (): void {
    (new ReserveStock)->handle(42);
});

(new ReserveStock)->handle(43);

foreach (LogEntry::forContainer('orders')->orderBy('id')->get() as $entry) {
    echo "Order {$entry->context['order_id']}: ", $entry->doing ?? 'not in a block', PHP_EOL;
}
```

```text
Order 42: fulfilling order 42
Order 43: not in a block
```

The deeper call starts with the block's labels, session, context and tags. What it sets itself, like a tag, applies to that call only. It doesn't join a block of another container.

## Without a block

You don't need a closure to write an entry. Without one, `doing()` and `during()` just set the label and return the container, so you can chain them. No session is created.

```php
<?php

use Keaton\LogContainers\Models\LogEntry;
use Keaton\LogContainers\Models\LogSession;

OrderLog::during('support')->doing('refunding order 42')->info('Refund issued');

$entry = LogEntry::forContainer('orders')->first();

echo "{$entry->during} > {$entry->doing}: {$entry->message}", PHP_EOL;
echo LogSession::query()->count(), ' sessions', PHP_EOL;
```

```text
support > refunding order 42: Refund issued
0 sessions
```

**Next:** [Tags and correlation](05-tags-and-correlation.md).
