<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Models\Partner;
use App\Models\User;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SocialAuthController extends Controller
{
    public function authenticate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:google,apple'],
            'token' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
        ]);

        try {
            $identity = match ($validated['provider']) {
                'google' => $this->verifyGoogleToken($validated['token']),
                'apple' => $this->verifyAppleToken($validated['token']),
            };
        } catch (\Throwable) {
            return response()->json(['message' => 'Invalid social token.'], 422);
        }

        $partnerId = $this->resolvePartnerId($validated['partner_id'] ?? null);
        $user = $this->findOrCreateUser($identity, $validated['name'] ?? null, $partnerId, $validated['provider']);
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Authentication successful',
            'user' => new UserResource($user->load(['partner', 'profile'])),
            'token' => $token,
        ]);
    }

    private function verifyGoogleToken(string $token): array
    {
        $jwks = $this->fetchJwks('google_public_keys', 'https://www.googleapis.com/oauth2/v3/certs');

        try {
            $decoded = JWT::decode($token, JWK::parseKeySet($jwks));
        } catch (SignatureInvalidException) {
            // Keys may have rotated — bust cache and retry once
            Cache::forget('google_public_keys');
            $jwks = $this->fetchJwks('google_public_keys', 'https://www.googleapis.com/oauth2/v3/certs');
            $decoded = JWT::decode($token, JWK::parseKeySet($jwks));
        }

        $allowedClientIds = array_values(array_filter([
            config('services.google.client_id'),
            config('services.google.ios_client_id'),
            config('services.google.android_client_id'),
        ]));

        if (empty($allowedClientIds)) {
            throw new \RuntimeException('Google client IDs are not configured.');
        }

        $aud = is_array($decoded->aud) ? $decoded->aud : [$decoded->aud];

        if (empty(array_intersect($aud, $allowedClientIds))) {
            throw new \RuntimeException('Invalid Google token audience.');
        }

        if (! in_array($decoded->iss, ['accounts.google.com', 'https://accounts.google.com'])) {
            throw new \RuntimeException('Invalid Google token issuer.');
        }

        return [
            'sub' => $decoded->sub,
            'email' => $decoded->email ?? null,
            'name' => $decoded->name ?? null,
        ];
    }

    private function verifyAppleToken(string $token): array
    {
        $jwks = $this->fetchJwks('apple_public_keys', 'https://appleid.apple.com/auth/keys');

        try {
            $decoded = JWT::decode($token, JWK::parseKeySet($jwks));
        } catch (SignatureInvalidException) {
            Cache::forget('apple_public_keys');
            $jwks = $this->fetchJwks('apple_public_keys', 'https://appleid.apple.com/auth/keys');
            $decoded = JWT::decode($token, JWK::parseKeySet($jwks));
        }

        if ($decoded->iss !== 'https://appleid.apple.com') {
            throw new \RuntimeException('Invalid Apple token issuer.');
        }

        // Accept both iOS bundle ID and web Service ID as valid audiences
        $allowedAudiences = array_values(array_filter([
            config('services.apple.bundle_id'),
            config('services.apple.service_id'),
        ]));

        if (empty($allowedAudiences)) {
            throw new \RuntimeException('Apple audience IDs are not configured.');
        }

        if (! in_array($decoded->aud, $allowedAudiences)) {
            throw new \RuntimeException('Invalid Apple token audience.');
        }

        return [
            'sub' => $decoded->sub,
            'email' => $decoded->email ?? null,
            'name' => null, // Apple never includes name in the JWT; name comes from the client on first sign-in only
        ];
    }

    private function fetchJwks(string $cacheKey, string $url): array
    {
        return Cache::remember($cacheKey, 3600, fn () => Http::get($url)->json());
    }

    private function resolvePartnerId(?int $requestedId): int
    {
        if (! $requestedId) {
            return 1;
        }

        $partner = Partner::find($requestedId);

        return ($partner && $partner->is_active) ? $requestedId : 1;
    }

    private function findOrCreateUser(array $identity, ?string $name, int $partnerId, string $provider): User
    {
        return DB::transaction(function () use ($identity, $name, $partnerId, $provider) {
            // Step 1: match by social_provider_id (sub)
            $user = User::where('social_provider', $provider)
                ->where('social_provider_id', $identity['sub'])
                ->lockForUpdate()
                ->first();

            if ($user) {
                $user->update(['last_login_at' => now()]);

                return $user;
            }

            // Step 2: match by email — auto-link and persist the sub so future logins hit step 1
            if ($identity['email']) {
                $user = User::where('email', $identity['email'])->lockForUpdate()->first();

                if ($user) {
                    $user->update([
                        'social_provider' => $provider,
                        'social_provider_id' => $identity['sub'],
                        'last_login_at' => now(),
                    ]);

                    return $user;
                }
            }

            // Step 3: create new account
            return User::create([
                'name' => $name ?? $identity['name'] ?? 'Member',
                'email' => $identity['email'] ?? $identity['sub'].'@noemail.fitnation.mk',
                'partner_id' => $partnerId,
                'social_provider' => $provider,
                'social_provider_id' => $identity['sub'],
                'email_verified_at' => now(),
                'last_login_at' => now(),
                'password' => null,
            ]);
        });
    }
}
