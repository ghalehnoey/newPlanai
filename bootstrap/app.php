<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'subscription' => \App\Http\Middleware\CheckSubscription::class,
            'admin'        => \App\Http\Middleware\AdminMiddleware::class,
            'manager'      => \App\Http\Middleware\ManagerMiddleware::class,
            'modir2'       => \App\Http\Middleware\Modir2Middleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $e, $request) {
            return redirect()->route('login')
                ->withErrors(['error' => 'نشست شما منقضی شده است. لطفاً دوباره وارد شوید.']);
        });
    })
    ->create();
