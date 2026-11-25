<!-- nav-group: Advanced -->
# Redaction and retention

A container decides what it stores and for how long. It can redact secrets, limit its number of entries, accept only some levels and have old entries deleted.

## Redaction

`redactKeys()` lists the context keys whose values are replaced with `[redacted]`. `redactPatterns()` lists regular expressions that are redacted inside string values. Redaction runs before the entry is stored, so the secret never reaches the database.

```php
<?php

use Keaton\LogContainers\LogContainer;

class ImportLog extends LogContainer
{
    public static function key(): string
    {
        return 'imports';
    }

    public static function name(): string
    {
        return 'Imports';
    }

    public function retentionDays(): ?int
    {
        return 14;
    }

    public function redactKeys(): array
    {
        return ['api_key'];
    }
}
```

```php
<?php

use Keaton\LogContainers\Models\LogEntry;

log_container(ImportLog::class)->error('Auth failed', [
    'api_key' => 'secret-123',
    'endpoint' => '/v1/products',
]);

$entry = LogEntry::forContainer('imports')->first();

echo 'api_key: ', $entry->context['api_key'], PHP_EOL;
echo 'endpoint: ', $entry->context['endpoint'], PHP_EOL;
```

```text
api_key: [redacted]
endpoint: /v1/products
```

## Trimming and retention

`maxEntries()` sets the most entries a container keeps. Counting the table on every write would be slow, so only a few writes in a hundred check the limit and delete the oldest entries. The `trim.odds` config value sets how many. In this example the odds are set to 100, so every write checks, and the limit is three.

```php
<?php

use Keaton\LogContainers\Models\LogEntry;

config()->set('log-containers.trim.odds', 100);
config()->set('log-containers.containers', [
    OrderLog::class => ['max_entries' => 3],
]);

$log = log_container(OrderLog::class);

foreach (range(1, 10) as $n) {
    $log->info("Event {$n}");
}

echo LogEntry::forContainer('orders')->count(), ' entries kept (max 3)', PHP_EOL;
echo LogEntry::forContainer('orders')->orderBy('id')->value('message'), ' is now the oldest', PHP_EOL;
```

```text
3 entries kept (max 3)
Event 8 is now the oldest
```

`retentionDays()` sets how long entries are kept. The `log-containers:prune` command deletes the ones that are older. You'll find it on the [Reference](08-reference.md) page.

## Limiting the levels

A container accepts every level by default. You can limit the levels in the class with `allowedLevels()`, or in the config file with a `levels` list or a `level` preset. The presets are `everything`, `standard` and `problems`. An entry with a level that isn't allowed is not stored. Here the `problems` preset keeps the error and drops the debug entry.

```php
<?php

use Keaton\LogContainers\Models\LogEntry;

config()->set('log-containers.containers', [
    OrderLog::class => ['level' => 'problems'],
]);

$log = log_container(OrderLog::class);
$log->debug('Checked inventory');
$log->error('Payment declined');

echo LogEntry::forContainer('orders')->count(), ' of 2 kept', PHP_EOL;
echo LogEntry::forContainer('orders')->value('level'), ' is the one that passed the gate', PHP_EOL;
```

```text
1 of 2 kept
error is the one that passed the gate
```

You can also change or drop single entries with a hook. Hooks are on the [Reference](08-reference.md) page.

**Next:** [Reference](08-reference.md).
