<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enables the Bazaar checkout only inside the signed WebView app.
 * The query string opts the app session in; the user-agent marker prevents a
 * normal browser from enabling the Bazaar UI by copying that URL.
 */
class BazaarAppModeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $isBazaarWebView = preg_match(
            '/(?:^|\s)HakimIsadrBazaar\/[0-9]+(?:\.[0-9]+)*(?:\s|$)/',
            (string) $request->userAgent()
        ) === 1;

        if ($isBazaarWebView) {
            if ($request->query('client') === 'web') {
                $request->session()->forget('bazaar_app_mode');
            } elseif ($request->query('client') === 'android' && $request->query('store') === 'bazaar') {
                $request->session()->put('bazaar_app_mode', true);
            }
        }

        $bazaarMode = $isBazaarWebView && (bool) $request->session()->get('bazaar_app_mode', false);
        $request->attributes->set('bazaar_app_mode', $bazaarMode);
        view()->share('bazaarMode', $bazaarMode);

        return $next($request);
    }
}
