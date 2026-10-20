<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request) {
            return route(match (true) {
                $request->is('administration', 'administration/*') => 'admin.login',
                $request->is('directeur', 'directeur/*', 'direction', 'direction/*') => 'director.login',
                default => 'login',
            });
        });
        $middleware->redirectUsersTo(fn () => route(auth()->user()->homeRoute()));
        $middleware->appendToGroup('web', \App\Http\Middleware\ProtectAuthenticationResponses::class);
        $middleware->alias([
            'active' => \App\Http\Middleware\EnsureAccountIsActive::class,
            'password.changed' => \App\Http\Middleware\EnsurePasswordIsChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation']);
    })->create();
