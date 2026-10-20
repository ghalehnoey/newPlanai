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
            '/(?:^|\s)HakimIsadrBazaar\/([0-9]+(?:\.[0-9]+)*)(?:\s|$)/',
            (string) $request->userAgent(),
            $versionMatch
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
        // Version 1 used another Bazaar app's RSA key and cannot verify receipts.
        // Keep that client from starting a charge until it has been updated.
        $versionSupported = $isBazaarWebView && version_compare($versionMatch[1], '1.0.1', '>=');
        $request->attributes->set('bazaar_version_supported', $versionSupported);
        view()->share('bazaarMode', $bazaarMode);
        view()->share('bazaarVersionSupported', $versionSupported);

        return $next($request);
    }
}
