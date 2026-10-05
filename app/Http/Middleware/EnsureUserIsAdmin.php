<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The super-admin gate: everything under /admin is for the `admin` role only.
 * Partner admins and app users get a 403.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        return $next($request);
    }
}
