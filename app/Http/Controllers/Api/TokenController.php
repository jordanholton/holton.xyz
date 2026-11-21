<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IssueTokenRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TokenController extends Controller
{
    /**
     * Abilities granted to tokens issued to the mobile app.
     */
    public const array ABILITIES = ['posts:read', 'posts:write'];

    /**
     * Exchange credentials for a bearer token, one per device.
     */
    public function store(IssueTokenRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $deviceName = $request->string('device_name')->toString();

        // Signing in again from the same device replaces its old token.
        $user->tokens()->where('name', $deviceName)->delete();

        $token = $user->createToken($deviceName, self::ABILITIES);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'abilities' => self::ABILITIES,
            'user' => new UserResource($user),
        ], Response::HTTP_CREATED);
    }

    /**
     * Revoke the token used for this request (sign out on the device).
     */
    public function destroy(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $user->currentAccessToken()->delete();

        return response()->noContent();
    }
}
