<!-- nav-group: Reference -->
# Reference

## Container methods

A container has to define `key()` and `name()`. It can override the others.

| Method | Default | What it does |
|---|---|---|
| `key()` | required | The value stored in the `container_key` column of each entry. |
| `name()` | required | A name for people to read. |
| `allowedLevels()` | `null` | The levels the container accepts. `null` accepts all of them. |
| `retentionDays()` | `null` | The age at which `log-containers:prune` deletes entries. |
| `maxEntries()` | `null` | The most entries the container keeps. |
| `redactKeys()` | `[]` | Context keys whose values are replaced with `[redacted]`. |
| `redactPatterns()` | `[]` | Regular expressions that are redacted inside string values. |
| `defaultContext()` | `[]` | Context that is added to every entry. |
| `mirrorsToLaravelLog()` | from config | Whether each entry is also written to the Laravel log. |
| `laravelLogChannel()` | from config | The Laravel log channel to write to. |

## Calling a container

The level methods, `type()`, `context()`, `tagWith()`, `clearTags()`, `doing()` and `during()` can be called statically on the class, like `OrderLog::info()`, or on an instance. A static call resolves the container from the service container, so the application has to be booted. Any other method name throws `BadMethodCallException`.

`log_container(OrderLog::class)` and `LogContainers::container(OrderLog::class)` return an instance. Each call returns a new one. Inside a `doing()` or `during()` block of that container it starts with the block's scope. Anywhere else it starts empty. A tag or a context you set on it never carries over to the next call.

You can also pass a key, like `log_container('orders')`. This works when the container is listed in `config('log-containers.containers')`, or when it was already resolved by class in the same request. An unknown key throws `InvalidArgumentException`.

`type()`, `context()`, `tagWith()` and `clearTags()` return the container, so you can chain them. `context()` merges with the context that's already set. `doing()` and `during()` return the container too, unless you pass a closure. Then they run it and return its value.

## Hooks

A container can define hooks that run before and after an entry is written.

| Method | When it runs |
|---|---|
| `beforeLog(array $attributes)` | Before every entry. |
| `before{Level}(array $attributes)` | Before an entry of that level, after `beforeLog()`. `beforeError()` is one. |
| `onLog(LogEntry $entry)` | After every entry is stored. |
| `on{Level}(LogEntry $entry)` | After an entry of that level is stored, after `onLog()`. |

A before hook can return the attributes with changes, or `false` to drop the entry. Its array parameter has to be named `$attributes`.

Hooks have to be public. They're called through the service container, so you can type-hint a service and it will be injected. A hook that isn't public throws a `LogicException` before the entry is stored.

## Extending a container

You can add your own methods to a container. To store an entry from one, call the protected `write()` method with a level, a message, a context array and an optional `Throwable`. Every entry goes through `write()`, so you can also override it. Apart from `write()` and the methods in the first table, the base class is closed. Its other methods are private or final.

## Models

`LogEntry` has the scopes `forContainer($key)`, `forExecution($id)` and `taggedWith($model)`, and the relations `session()` and `tags()`.

`LogSession` has the relations `entries()`, `parent()` and `children()`, a `status` of `open`, `completed` or `failed`, and the columns `duration_ms`, `entry_count` and `error_count`. `LogTag` has the relations `entry()` and `taggable()`.

## Watch configuration

Watches are registered in `config/log-containers.php`, keyed by class:

```
'watches' => [
    App\Logging\Watches\RepeatedFailureWatch::class => [
        'containers' => ['orders'],   // container keys, or ['*'] for all
        'enabled' => true,            // optional, defaults to true
    ],
],
```

## Commands

```bash
php artisan log-containers:prune            # delete entries past each container's retentionDays()
php artisan log-containers:prune --pretend  # report what would be deleted
```

Only the containers listed in `config('log-containers.containers')` are pruned. Add every container that has a retention window there.

## Testing your logging

`LogContainers::fake()` limits the assertions to entries written after the call, and stops entries from being written to the Laravel log. `assertLogged()`, `assertNotLogged()` and `assertCount()` then read the stored entries.

```php
<?php

use Keaton\LogContainers\Facades\LogContainers;

LogContainers::fake();

log_container(OrderLog::class)->info('Order placed');
log_container(OrderLog::class)->error('Payment failed');

LogContainers::assertLogged(OrderLog::class);
LogContainers::assertLogged(OrderLog::class, 'error');
LogContainers::assertNotLogged(OrderLog::class, 'critical');
LogContainers::assertCount(OrderLog::class, 2);
LogContainers::assertCount(OrderLog::class, 1, 'error');

echo 'all assertions passed', PHP_EOL;
```

```text
all assertions passed
```
