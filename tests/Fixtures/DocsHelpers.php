<?php

declare(strict_types=1);

/*
 * The functions the README and the docs pages wrap in a container. They stand for the reader's
 * own code, so they do nothing. The docs tests (tests/Feature/DocsExamplesTest.php) load this
 * file before running the page examples. They live in the global namespace because the pages
 * call them that way.
 */

if (! function_exists('reserveStock')) {
    /** @param  array<string, mixed>  $order */
    function reserveStock(array $order): void
    {
        //
    }
}

if (! function_exists('chargeCard')) {
    /** @param  array<string, mixed>  $order */
    function chargeCard(array $order): void
    {
        //
    }
}

if (! function_exists('sendReceipt')) {
    /** @param  array<string, mixed>  $order */
    function sendReceipt(array $order): void
    {
        //
    }
}
