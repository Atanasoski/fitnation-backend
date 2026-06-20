<?php

namespace App\Http\Middleware;

use App\Enums\Entitlement;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequiresSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasEntitlement(Entitlement::AppAccess)) {
            return response()->json(['message' => 'Subscription required.'], 403);
        }

        return $next($request);
    }
}
