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
        if (! app()->environment('local', 'testing') && ! $request->secure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        if (! app()->environment('local', 'testing')) {
            URL::forceScheme('https');
        }

        $response = $next($request);

        if (! app()->environment('local', 'testing')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}
