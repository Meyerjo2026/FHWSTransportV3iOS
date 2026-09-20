<?php

// Trust proxies before any request handling (explicit CIDRs, never '*')
require __DIR__.'/proxies.php';

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Proxy trust is handled in bootstrap/proxies.php and public/index.php
        // using explicit RFC-1918 CIDRs. Do NOT call trustProxies(at: '*') here —
        // the '*' wildcard can produce null CIDR entries in Symfony's IpUtils.

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'password.changed' => \App\Http\Middleware\EnsurePasswordChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
