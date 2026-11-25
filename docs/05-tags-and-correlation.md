<!-- nav-group: Advanced -->
# Tags and correlation

There are two ways to find related entries. A tag links entries to a record. An execution id links all the entries written during one request, job or command, in any container.

## Tagging a record

`tagWith()` links entries to an Eloquent model. The tag stays on every entry you write after it, until you call `clearTags()` or the `doing()` or `during()` block it was set in ends.

A model can choose its own tag label by implementing `ProvidesLogTagLabel`. A model that doesn't is labeled with its class name and key.

```php
<?php

use Illuminate\Database\Eloquent\Model;
use Keaton\LogContainers\Contracts\ProvidesLogTagLabel;

class Order extends Model implements ProvidesLogTagLabel
{
    protected $guarded = [];

    public $timestamps = false;

    public function logTagLabel(): string
    {
        return "Order #{$this->getKey()}";
    }
}
```

`LogEntry::taggedWith($order)` returns every entry that was tagged with the order:

```php
<?php

use Keaton\LogContainers\Models\LogEntry;

$order = Order::create(['id' => 42, 'total' => 80]);

$log = log_container(OrderLog::class)->tagWith($order);
$log->info('Order placed');
$log->warning('Address looks incomplete');

echo LogEntry::taggedWith($order)->count(), ' entries touched ', $order->logTagLabel(), PHP_EOL;
echo LogEntry::taggedWith($order)->first()->tags->first()->label, PHP_EOL;
```

```text
2 entries touched Order #42
Order #42
```

You can also pass a string to `tagWith()`. That stores a label without a link to a model.

## One id for each run

Every entry stores an execution id. `ExecutionContext` is a singleton that creates the id the first time it's needed and keeps it for as long as the singleton lives, so entries from different containers share it.

The example uses `ImportLog`, a second container that's declared on the [Redaction and retention](07-redaction-and-retention.md) page.

```php
<?php

use Keaton\LogContainers\ExecutionContext;
use Keaton\LogContainers\Models\LogEntry;

log_container(OrderLog::class)->info('Request received');
log_container(ImportLog::class)->info('Kicked off a background import');

$executionId = app(ExecutionContext::class)->id();

echo LogEntry::forExecution($executionId)->count(), ' entries share one execution id', PHP_EOL;
echo LogEntry::forExecution($executionId)->distinct()->count('container_key'), ' containers, one run', PHP_EOL;
```

```text
2 entries share one execution id
2 containers, one run
```

The package doesn't listen for the start of a request, job or command. On a queue worker, that means every job would get the same id. To start a new run, call `reset()` and then `initializeForHttp()`, `initializeForConsole()` or `initializeForJob()`.

A queued job can also continue the run of the request that dispatched it. Pass the id along with the job and call `continueFrom()`:

```php
<?php

use Keaton\LogContainers\ExecutionContext;
use Keaton\LogContainers\Models\LogEntry;

$execution = app(ExecutionContext::class);

log_container(OrderLog::class)->info('Handling the request');
$carried = $execution->id();

// ...later, in a queued listener that should belong to the same run.
// Carry $carried across the boundary (on the event, the job payload, a header)
// and continue from it:
$execution->reset()->continueFrom($carried);

log_container(OrderLog::class)->info('Handling the queued follow-up');

echo LogEntry::forExecution($carried)->count(), ' entries, one run across the boundary', PHP_EOL;
```

```text
2 entries, one run across the boundary
```

**Next:** [Watches](06-watches.md).
