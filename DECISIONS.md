# Decisions

Notes on why the package is laid out the way it is. Read this before you move code between classes.

## 1. `LogContainer` holds what a container is, and nothing else

Before 1.0 `LogContainer` was one class of about 900 lines. It declared a container, kept its scope, parsed the magic calls, read the config, ran the hooks, wrote the row, counted sessions, mirrored to the Laravel log and trimmed the table.

It is also the class every user extends. After 1.0 anything a subclass can see on it is frozen, so it was split before the first tag.

`LogContainer` keeps three things:

- the declarations a subclass overrides: `key()`, `name()`, `allowedLevels()`, `retentionDays()`, `maxEntries()`, `redactKeys()`, `redactPatterns()`, `defaultContext()`, `mirrorsToLaravelLog()` and `laravelLogChannel()`
- the scope: type, context, sticky tags and the `doing()` and `during()` labels
- the magic methods that make `OrderLog::info()` and `$log->info()` the same call

Everything else moved:

| Class | Job |
|---|---|
| `Writing\EntryWriter` | The write path, in order: gate, context, hooks, row, tags, session counts, mirror, watches, trim. |
| `Writing\Scope` | A read-only snapshot of the container's scope, handed to the writer. |
| `Writing\ContainerSettings` | Whether a container is on, which levels it accepts and its row cap. Config wins over the class, and bad config throws. |
| `Writing\ContextPreparer` | Merge, sanitize, then redact. The order matters and now lives in one place. |
| `Writing\HookRunner` | Finds and calls the `before*` and `on*` hooks through the service container. |
| `Writing\SessionRecorder` | Opens, counts and closes `LogSession` rows. |
| `Writing\OpenBlocks` | The `doing()` and `during()` blocks that are running right now. See decision 3. |
| `Writing\Trimmer` | The row cap: the lottery and the delete. |
| `Support\LevelArguments` | Checks the arguments of a magic level call. |
| `Support\Tag` | Turns a model or a string into a tag. |

### Why classes and not traits

Traits would have made the file shorter and left the class the same size. Every trait method would still be a method of `LogContainer`, in the same class as the user's hooks and with the same private state.

Separate classes can be tested on their own, most of them without a database. They are `final` and marked `@internal`, so they can change in a minor release. A trait on a public base class can't.

### What a subclass can rely on

The rule was to start closed. Opening something later is a minor release. Closing it is a major one.

- The declarations are public and meant to be overridden.
- `write()` is protected. It is the one extension point: every entry goes through it, so a subclass can wrap it, or call it from a method of its own.
- There is no constructor. A subclass can add one and type-hint what it needs. The base class resolves its collaborators from the service container when it writes, so its dependencies can change without breaking a subclass.
- `__call()`, `__callStatic()`, `tags()` and `startSession()` are final. They read or write the private scope, and an override would put the two out of step.
- The methods behind the magic names (`applyType()`, `applyContext()`, `applyTag()`, `applyClearTags()`, `startDoing()`, `startDuring()`, `writeException()`) were protected and are now private. Nothing was meant to override them.
- Everything under `Writing\` and `Support\` is internal.

`tests/Feature/SubclassSurfaceTest.php` lists the public and protected methods of `LogContainer`. If you add one, that test fails until you add it to the list, which is the moment to decide whether it should be frozen.

## 2. Hooks must be public

A hook is found by name (`beforeLog`, `beforeError`, `onLog`, `onError` and so on) and called through the service container, which can only call public methods.

`method_exists()` also finds protected and private methods. A protected `beforeLog()` used to be found and then fail with "Call to undefined method", because the call fell through to `__call()`.

Now a hook that isn't public throws a `LogicException` that names the method, and it throws before the entry is stored. Skipping the hook silently was the other option, but a hook is often there to veto or scrub an entry, so a hook that quietly doesn't run is worse than an exception.

Protected hooks could be allowed later without breaking anyone. The reverse isn't true.

An after hook is given the entry by name and by type, so `onError(LogEntry $failed)` receives the stored entry. Passing it by name only would have let the service container build an empty `LogEntry` for a parameter that isn't called `$entry`.

## 3. Code called from inside a block joins it

A `doing()` or `during()` block used to be known only to the container instance that was passed into the closure. Any class the block called had to be handed that instance, or its entries were written outside the block.

Now the blocks that are running are kept in `Writing\OpenBlocks`, innermost last. Two things read it.

- `LogContainerManager::container()` returns a copy of the container of the innermost open block of that class. So `OrderLog::info()` and `log_container(OrderLog::class)` start with the block's labels, session, context and tags. It is a copy so that a tag or a context set by the deeper call stays with that call and doesn't change the block.
- A new session takes the session of the innermost open `doing()` block as its `parent_id`, whichever container opened it. `LogSession` has `parent()` and `children()`.

A block is added before its closure runs and removed in a `finally`, so a block that throws doesn't leave anything behind. A container you resolved before the block started keeps the scope it had, because nothing changes an instance you already hold.

The other option was a container instance shared per class. That would have let one caller's `tagWith()` or `context()` change every later call in the request, which is the bug a new instance per call was there to prevent.
