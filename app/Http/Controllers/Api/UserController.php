<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeleteUserRequest;
use App\Http\Resources\Api\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserController extends Controller
{
    /**
     * Get authenticated user.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()->load(['partner', 'profile', 'subscription'])),
        ]);
    }

    /**
     * Delete the authenticated user's account.
     *
     * Revokes all tokens, anonymizes PII, then soft-deletes the record.
     */
    public function destroy(DeleteUserRequest $request): Response
    {
        $user = $request->user();

        $user->tokens()->delete();

        $user->forceFill([
            'name' => 'Deleted User',
            'email' => 'deleted_'.$user->id.'@deleted.invalid',
        ])->save();

        $user->delete();

        return response()->noContent();
    }
}
