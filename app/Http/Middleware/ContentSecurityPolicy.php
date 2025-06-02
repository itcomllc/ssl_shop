<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicy
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $csp = "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' https://js.squareup.com https://cdn.tailwindcss.com; " .
            "style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; " .
            "img-src 'self' data: https:; " .
            "font-src 'self' https:; " .
            "connect-src 'self' https://connect.squareup.com;";

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
