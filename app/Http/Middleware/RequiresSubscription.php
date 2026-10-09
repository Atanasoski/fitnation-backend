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
        // Dark deploy: with enforcement off nobody is gated (config/subscriptions.php).
        if (! config('subscriptions.enforced')) {
            return $next($request);
        }

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
