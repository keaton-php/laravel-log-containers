<?php

declare(strict_types=1);

namespace Keaton\LogContainers\Tests\Fixtures;

use Illuminate\Contracts\Debug\ExceptionHandler;
use RuntimeException;
use Throwable;

/**
 * An exception handler whose report() itself throws. Bound in place of the real
 * handler to prove that a broken reporter, the last line of defense when a
 * watch fails, still cannot break the log write that triggered the watch.
 *
 * The interface declares no return types, so neither does this double.
 */
class ThrowingReporter implements ExceptionHandler
{
    public function report(Throwable $e): void
    {
        throw new RuntimeException('the reporter itself is broken');
    }

    public function shouldReport(Throwable $e)
    {
        return true;
    }

    public function render($request, Throwable $e): void
    {
        throw new RuntimeException('render is not exercised by these tests');
    }

    public function renderForConsole($output, Throwable $e): void
    {
        // not exercised
    }
}
