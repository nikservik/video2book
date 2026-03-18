<?php

use App\Mcp\Support\McpUrlTokenRedactor;
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
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);
        $middleware->web(append: \App\Http\Middleware\AuthenticateTeamAccessToken::class);
        $middleware->alias([
            'api.access-token' => \App\Http\Middleware\AuthenticateApiAccessToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->context(function (): array {
            $sanitizedUrl = app(McpUrlTokenRedactor::class)->currentRequestUrl();

            return $sanitizedUrl === null
                ? []
                : ['request_url' => $sanitizedUrl];
        });
    })->create();
