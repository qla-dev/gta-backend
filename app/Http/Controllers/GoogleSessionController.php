<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GoogleSessionController extends Controller
{
    private const REGISTRATION_COINS = 30;
    private const FREE_DAILY_MESSAGES = 30;
    private const GOOGLE_CERTS_URL = 'https://www.googleapis.com/oauth2/v3/certs';
    private const GOOGLE_TOKEN_INFO_URL = 'https://oauth2.googleapis.com/tokeninfo';

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_token' => 'required|string',
            'name' => 'nullable|string|max:255',
        ]);

        $payload = $this->verifyIdToken($request->input('id_token'));
        if (! $payload) {
            return response()->json(['error' => 'Invalid Google token.'], 422);
        }

        $googleId = $payload['sub'];
        $email = $payload['email'] ?? null;
        $googleName = $payload['name'] ?? $request->input('name') ?? 'Player';

        $registered = false;
        $user = User::where('google_id', $googleId)->first();

        if (! $user && $email) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->update(['google_id' => $googleId, 'google_email' => $email]);
            }
        }

        if (! $user) {
            $user = User::create([
                'google_id' => $googleId,
                'google_email' => $email,
                'name' => $googleName,
                'email' => $email ?: hash('sha256', $googleId).'@google.local',
                'password' => null,
                'ai_coins' => self::REGISTRATION_COINS,
            ]);
            $registered = true;
        }

        if ($email && ! $user->google_email) {
            $user->update(['google_email' => $email]);
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

    private function verifyIdToken(string $idToken): ?array
    {
        $response = Http::get(self::GOOGLE_TOKEN_INFO_URL, [
            'id_token' => $idToken,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();

        $validAudiences = array_filter([
            config('services.google.ios_client_id'),
            config('services.google.web_client_id'),
            config('services.google.android_client_id'),
        ]);

        if (! empty($validAudiences) && ! in_array($payload['aud'] ?? '', $validAudiences, true)) {
            return null;
        }

        if (($payload['iss'] ?? '') !== 'accounts.google.com'
            && ($payload['iss'] ?? '') !== 'https://accounts.google.com') {
            return null;
        }

        return $payload;
    }
}
