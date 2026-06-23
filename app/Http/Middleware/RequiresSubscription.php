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
        $user = $request->user();

        // Load the relations entitlements() reads up front so this gate (and the
        // downstream controller) doesn't lazy-load them per request.
        $user?->loadMissing(['subscription', 'partner']);

        if (! $user?->hasEntitlement(Entitlement::AppAccess)) {
            return response()->json([
                'message' => 'Subscription required.',
                'code' => 'subscription_required',
            ], 403);
        }

        return $next($request);
    }
}
