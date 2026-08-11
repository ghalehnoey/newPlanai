<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ManagerMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'manager') {
            abort(403, 'این بخش فقط برای مدیر کل است');
        }
        return $next($request);
    }
}
