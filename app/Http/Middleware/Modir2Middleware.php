<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Modir2Middleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['manager', 'modir2'])) {
            abort(403, 'دسترسی غیرمجاز');
        }
        return $next($request);
    }
}
