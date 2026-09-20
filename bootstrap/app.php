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
        // Requests reach the application through ArvanCloud. Trust its
        // forwarded protocol header so Laravel keeps HTTPS URLs and sessions.
        $middleware->trustProxies(at: [
            '185.143.232.0/22',
            '188.229.116.16/30',
            '94.101.182.0/27',
            '2.144.3.128/28',
            '37.32.16.0/27',
            '37.32.17.0/27',
            '37.32.18.0/27',
            '37.32.19.0/27',
            '185.215.232.0/22',
            '178.131.120.48/28',
            '94.101.183.0/28',
            '78.157.36.112/28',
            '95.38.61.80/28',
            '193.24.119.0/29'
        ]);

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
