<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckSubscription
{
    public function handle(Request $request, Closure $next)
    {
        // ۱. اگر لاگین نیست → بفرست صفحه ورود
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        // هر کاربر لاگین‌کرده می‌تواند صفحه چت را ببیند
        // بلاک کردن فقط در sendMessage اتفاق می‌افتد
        return $next($request);
    }
}
