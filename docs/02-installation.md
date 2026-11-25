<!-- nav-group: Start here -->
# Installation

## Requirements

- PHP 8.2 or higher
- Laravel 11, 12 or 13 (Laravel 13 needs PHP 8.3)

## Installing the package

You can install the package via composer:

```bash
composer require keaton-php/laravel-log-containers
```

The service provider is discovered automatically.

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag=log-containers-migrations
php artisan migrate
```

The migration creates three tables: `log_entries`, `log_sessions` and `log_tags`.

## Configuration

You can publish the config file with:

```bash
php artisan vendor:publish --tag=log-containers-config
```

| Key (`.env`) | Default | What it does |
|---|---|---|
| `enabled` (`LOG_CONTAINERS_ENABLED`) | `true` | Set it to `false` to stop every container from writing. |
| `containers` | `[]` | Your containers, keyed by class. Each one can set `enabled`, `levels` or `level`, and `max_entries`. |
| `level_presets` | 3 presets | Named lists of levels: `everything`, `standard` and `problems`. |
| `watches` | `[]` | The watches to run. See [Watches](06-watches.md). |
| `trim.odds` (`LOG_CONTAINERS_TRIM_ODDS`) | `2` | How many writes in 100 check a container's entry limit. |
| `mirror_to_laravel_log` (`LOG_CONTAINERS_MIRROR`) | `false` | Also writes every entry to the Laravel log. |
| `laravel_log_channel` (`LOG_CONTAINERS_CHANNEL`) | `null` | The Laravel log channel to write to. `null` uses the default channel. |

A container doesn't have to be listed in `containers` to work. List it when you want to override its settings, resolve it by key, or have its old entries pruned.

A container that's turned off still runs the block you pass to `doing()` or `during()`, and its level methods still return a `LogEntry`, so the code that calls it doesn't need a check. The entries just aren't stored.

**Next:** [Basic usage](03-basic-usage.md).
