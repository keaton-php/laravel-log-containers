<!-- nav-group: Advanced -->
# Watches

A watch is a class that decides which entries are worth noticing. When a matching entry is written, the package fires a `LogWatchTriggered` event.

## Writing a watch

Extend `LogWatch` and implement `watchFor()`, which returns `true` when an entry matches. You can also override `name()`, `severity()` and `message()`, which returns the text of the alert.

```php
<?php

use Keaton\LogContainers\Models\LogEntry;
use Keaton\LogContainers\Watches\LogWatch;

class RepeatedFailureWatch extends LogWatch
{
    public function name(): string
    {
        return 'Repeated order failure';
    }

    public function severity(): string
    {
        return self::SEVERITY_CRITICAL;
    }

    public function watchFor(LogEntry $entry): bool
    {
        return $entry->level === 'error'
            && ($entry->context['attempt'] ?? 1) >= 3;
    }

    public function message(LogEntry $entry): string
    {
        return "Order {$entry->context['order_id']} failed on attempt {$entry->context['attempt']}";
    }
}
```

## Registering a watch

Watches are registered in `config/log-containers.php`, each with the keys of the containers it watches. The example sets the config inline so that it can run on its own.

Two entries are written here, and only the second one matches.

```php
<?php

use Illuminate\Support\Facades\Event;
use Keaton\LogContainers\Events\LogWatchTriggered;

config()->set('log-containers.watches', [
    RepeatedFailureWatch::class => ['containers' => ['orders']],
]);

$alerts = [];
Event::listen(LogWatchTriggered::class, function (LogWatchTriggered $event) use (&$alerts): void {
    $alerts[] = $event->message;
});

$log = log_container(OrderLog::class);
$log->error('Payment failed', ['order_id' => 42, 'attempt' => 1]);
$log->error('Payment failed', ['order_id' => 42, 'attempt' => 3]);

echo count($alerts), ' alert fired', PHP_EOL;
echo $alerts[0], PHP_EOL;
```

```text
1 alert fired
Order 42 failed on attempt 3
```

The event has the watch, the entry and the message. The names of the watches that matched are also stored in the entry's `triggered_watches` column.

If a watch throws, the exception is reported to your app's exception handler. The entry is still written.

**Next:** [Redaction and retention](07-redaction-and-retention.md).
