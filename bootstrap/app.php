<?php
use Illuminate\Foundation\Application;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php')
    ->withMiddleware(function (Illuminate\Foundation\Configuration\Middleware $middleware): void {})
    ->withExceptions(function (Illuminate\Foundation\Configuration\Exceptions $exceptions): void {})
    ->create();
