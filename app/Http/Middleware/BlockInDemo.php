<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a request while the public demo is on. Used on what one visitor could do to lock the others out until the
 * nightly reset: changing or resetting passwords, registering, and deleting logins.
 */
class BlockInDemo
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('demo.enabled')) {
            return response()->json(['message' => 'This is turned off in the demo, so every visitor can sign in with the shared logins.'], 403);
        }
        return $next($request);
    }
}
