<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AppleIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AppleSessionController extends Controller
{
    private const REGISTRATION_COINS = 30;
    private const FREE_DAILY_MESSAGES = 30;
    private const CHALLENGE_TTL = 300; // 5 minutes

    public function challenge(): JsonResponse
    {
        $id = (string) Str::uuid();
        $nonce = Str::random(32);

        Cache::put("apple_challenge:{$id}", $nonce, self::CHALLENGE_TTL);

        return response()->json([
            'challenge_id' => $id,
            'nonce' => $nonce,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'identity_token' => 'required|string',
            'challenge_id' => 'required|string',
            'name' => 'nullable|string|max:255',
        ]);

        $nonce = Cache::pull("apple_challenge:{$request->input('challenge_id')}");
        if (! $nonce) {
            return response()->json(['error' => 'Challenge expired or already used.'], 422);
        }

        $audiences = array_filter([
            'gta.qla.dev',
            config('services.apple.dev_bundle_id'),
        ]);

        try {
            $payload = (new AppleIdentity)->verify(
                $request->input('identity_token'),
                $nonce,
                $audiences,
            );
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $sub = $payload['sub'];
        $email = $payload['email'] ?? null;
        $isPrivateEmail = ($payload['is_private_email'] ?? false) === 'true'
            || ($payload['is_private_email'] ?? false) === true;

        $registered = false;
        $user = User::where('apple_id', $sub)->first();

        if (! $user) {
            $user = User::create([
                'apple_id' => $sub,
                'name' => $request->input('name') ?: 'Player',
                'email' => hash('sha256', $sub).'@apple.local',
                'password' => null,
                'ai_coins' => self::REGISTRATION_COINS,
            ]);
            $registered = true;
        }

        if ($email) {
            $user->update([
                'apple_email' => $email,
                'apple_email_private' => $isPrivateEmail,
            ]);
        }

        $token = $user->createToken('mobile', expiresAt: now()->addDays(90));

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'ai_coins' => $user->ai_coins,
                'free_messages' => [
                    'limit' => self::FREE_DAILY_MESSAGES,
                    'used' => $user->freeMessagesUsedToday(),
                ],
            ],
            'registered' => $registered,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'ai_coins' => $user->ai_coins,
                'free_messages' => [
                    'limit' => self::FREE_DAILY_MESSAGES,
                    'used' => $user->freeMessagesUsedToday(),
                ],
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['signed_out' => true]);
    }
}
