<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
public function handle(Request $request, Closure $next): Response
{
    if (! config('app.force_https')) {
        return $next($request);
    }

    if (! $request->secure()) {
        return redirect()->secure($request->getRequestUri(), 301);
    }

    URL::forceScheme('https');

    $response = $next($request);

    $response->headers->set(
        'Strict-Transport-Security',
        'max-age=31536000; includeSubDomains'
    );

    return $response;
}
}
