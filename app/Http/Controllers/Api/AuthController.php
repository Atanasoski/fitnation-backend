<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'invitation_token' => ['required', 'string', 'exists:user_invitations,token'],
        ]);

        // Validate the invitation
        $invitation = \App\Models\UserInvitation::where('token', $validated['invitation_token'])->first();

        if (! $invitation) {
            return response()->json([
                'message' => 'Invalid invitation token',
            ], 422);
        }

        if ($invitation->isAccepted()) {
            return response()->json([
                'message' => 'This invitation has already been used',
            ], 422);
        }

        if ($invitation->isExpired()) {
            return response()->json([
                'message' => 'This invitation has expired',
            ], 422);
        }

        // Verify the email matches the invitation
        if ($invitation->email !== $validated['email']) {
            return response()->json([
                'message' => 'Email does not match the invitation',
            ], 422);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'partner_id' => $invitation->partner_id,
        ]);

        // Mark invitation as accepted
        $invitation->markAsAccepted();

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => new UserResource($user->load([
                'partner',
                'profile',
                'activeSubscription' => fn ($q) => $q->with(['subscriptionPlan' => fn ($q) => $q->withTrashed()]),
            ])),
            'token' => $token,
        ], 201);
    }

    /**
     * Login user and create token.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => new UserResource($user->load([
                'partner',
                'profile',
                'activeSubscription' => fn ($q) => $q->with(['subscriptionPlan' => fn ($q) => $q->withTrashed()]),
            ])),
            'token' => $token,
        ]);
    }

    /**
     * Logout user (revoke token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }
}
