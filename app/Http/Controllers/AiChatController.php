<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\PersonalAccessToken;

class AiChatController extends Controller
{
    private const VICE_PROMPT = <<<'PROMPT'
You are V.I.C.E. (Virtual Intelligence Crime Engine), the built-in AI copilot for the fictional LeonidaOS smartphone in GTA VI (Grand Theft Auto VI).
You operate inside the neon-lit, satirical, sunny, and dangerous universe of Vice City and the state of Leonida.
Your personality is street-smart, tactical, slightly sarcastic, highly knowledgeable about criminal getaways, exotic supercars, underground chop shops, waterfront penthouses, Everglades alligators, and police evasion tactics.
Always stay in character as a fictional in-game operating system assistant.
Give crisp, exciting, helpful answers formatted with bullet points or quick tactical summaries when helpful.
If the user sends an image, describe what you see in the context of Vice City and Leonida.
PROMPT;

    private const VEHICLE_PROMPT = <<<'PROMPT'
You analyze vehicle images and data for a GTA VI companion app. Return ONLY a JSON object with: name (string), category (string, e.g. 'Sports', 'Muscle', 'SUV', 'Sedan'), speed (string, e.g. '210 MPH'), color (string), icon (string, Ionicons name like 'car-sport'), description (string, 1-2 sentences GTA-style).
If the user sends an image, identify the vehicle. If they send text, generate vehicle data matching their description.
Always respond with fictional GTA-universe vehicle names and stats. Make the description feel like a Legendary Motorsport listing.
PROMPT;

    private const PROPERTY_PROMPT = <<<'PROMPT'
You analyze property/real estate images and data for a GTA VI companion app. Return ONLY a JSON object with: name (string), location (string), type (string, e.g. 'Penthouse', 'Villa', 'Warehouse', 'Stilt House'), value (string, e.g. '$2,450,000'), icon (string, Ionicons name like 'home'), description (string, 1-2 sentences GTA-style).
If the user sends an image, identify the property. If they send text, generate property data matching their description.
Always respond with fictional GTA-universe property names and locations in Vice City / Leonida. Make the description feel like a Dynasty 8 Real Estate listing.
PROMPT;

    private const PAID_MODES = ['vehicle', 'property'];
    private const COIN_COST = 10;

    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'nullable|string|max:2000',
            'history' => 'array|max:20',
            'history.*.role' => 'in:user,assistant',
            'history.*.content' => 'string|max:4000',
            'image' => ['nullable', 'string', 'max:4000000', 'regex:#^data:image/(?:jpeg|png|webp);base64,[A-Za-z0-9+/\n\r]+=*$#'],
            'mode' => 'nullable|in:vice,vehicle,property',
        ]);

        $mode = $request->input('mode', 'vice');
        $message = $request->input('message', '');
        $image = $request->input('image');

        if (! $message && ! $image) {
            return response()->json(['error' => 'Message or image is required.'], 422);
        }

        $user = null;
        if (in_array($mode, self::PAID_MODES, true)) {
            $bearer = $request->bearerToken();
            if ($bearer) {
                $token = PersonalAccessToken::findToken($bearer);
                $user = $token?->tokenable;
            }
            if (! $user) {
                return response()->json(['error' => 'Authentication required.'], 401);
            }
            if ($user->ai_coins < self::COIN_COST) {
                return response()->json(['error' => 'Insufficient credits. You need '.self::COIN_COST.' credits.', 'required' => self::COIN_COST], 402);
            }
        }

        $apiKey = config('services.openrouter.key');

        if (! $apiKey) {
            return response()->json([
                'reply' => $this->fallbackReply($message ?: 'image'),
                'source' => 'local',
            ]);
        }

        $systemPrompt = match ($mode) {
            'vehicle' => self::VEHICLE_PROMPT,
            'property' => self::PROPERTY_PROMPT,
            default => self::VICE_PROMPT,
        };

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach ($request->input('history', []) as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }

        $userContent = [];
        if ($message) {
            $userContent[] = ['type' => 'text', 'text' => $message];
        }
        if ($image) {
            $userContent[] = ['type' => 'image_url', 'image_url' => ['url' => $image]];
        }

        $messages[] = ['role' => 'user', 'content' => $userContent];

        try {
            $payload = [
                'model' => config('services.openrouter.model'),
                'messages' => $messages,
                'max_tokens' => 1200,
            ];

            if ($mode !== 'vice') {
                $payload['response_format'] = ['type' => 'json_object'];
            }

            $response = Http::withToken($apiKey)
                ->timeout(45)
                ->post(config('services.openrouter.url'), $payload);

            if ($response->successful()) {
                $text = $response->json('choices.0.message.content', '');

                if ($text) {
                    if (in_array($mode, self::PAID_MODES, true) && $user) {
                        $user->decrement('ai_coins', self::COIN_COST);
                        $user->refresh();
                    }

                    if ($mode !== 'vice') {
                        $data = json_decode($text, true);
                        if (! is_array($data)) {
                            $data = ['error' => 'Failed to parse AI response.'];
                        }

                        return response()->json([
                            'data' => $data,
                            'source' => 'openrouter',
                            'ai_coins' => $user?->ai_coins,
                        ]);
                    }

                    return response()->json(['reply' => $text, 'source' => 'openrouter']);
                }
            }
        } catch (\Throwable) {
            // fall through to local
        }

        return response()->json([
            'reply' => $this->fallbackReply($message ?: 'image'),
            'source' => 'local',
        ]);
    }

    private function fallbackReply(string $query): string
    {
        $q = strtolower($query);

        if (preg_match('/car|vehicle|fast|supercar|speed/', $q)) {
            return "🏎️ **V.I.C.E. Fleet Intel — Fastest Rides in Vice City:**\n- **Pegassi Infernus GTS (\$1,450,000):** 215 MPH top speed, AWD grip.\n- **Grotti Cheetah Classic (\$865,000):** Iconic mid-engine 80s supercar.\n- **Pfister Comet S2 Cabrio (\$1,780,000):** Superior launch control.\n\n*Tactical Tip:* Head to the Chop Shop in Little Haiti for EMS Level 4 and Turbo tuning!";
        }

        if (preg_match('/heist|rob|bank|score|vault/', $q)) {
            return "💼 **First Vice Federal Vault — Infiltration Assessment:**\n1. Rooftop AC Approach at 03:15 AM during shift turnover.\n2. Pick up thermite from Cal Hampton's salvage yard in Little Haiti.\n3. Getaway: reinforced AWD vehicle in the subterranean garage.\n4. Estimated Score: \$2,400,000 in bearer bonds.";
        }

        if (preg_match('/safehouse|property|house|penthouse|mansion/', $q)) {
            return "🌴 **Top Safehouse Recommendations:**\n- **Ocean Beach Penthouse 42:** Highest view, 10-car garage.\n- **Starfish Island Villa:** Deepwater dock, private helipad.\n- **Pelican Reef Stilt Shack:** Zero police radar coverage.";
        }

        if (preg_match('/police|cop|escape|wanted|heat|star/', $q)) {
            return "🚨 **Escaping 5-Star Heat:**\n- Avoid MacArthur Causeway (roadblocks with spikes).\n- Go aquatic — Jet Ski into Atlantic reef channels.\n- Chop Shop respray in Little Haiti drops heat instantly.";
        }

        if (preg_match('/alligator|gator|florida|leonida man|swamp/', $q)) {
            return "🐊 **Leonida Man Sightings:**\n- Highway 17 gas station — attempting to tame a 14-foot bull alligator with beef jerky.\n- Warning: Do NOT accept moonshine from \"Swampy Dale\".\n- Confirmed: Albino gator in mangrove canals near Gator Pete's Safari.";
        }

        return "⚡ **V.I.C.E. OS Dispatch:**\nAnalyzing Leonida satellite telemetry for: *\"{$query}\"*...\n\n- **Sector Status:** Clear across Vice Beach and Downtown.\n- **Tactical Intel:** High-value weapon shipments arriving at Port Gellhorn tonight.\n- **Recommended:** Check **Leonida Maps** for waypoints or tune up your ride in **Garage**!\n\nNeed coordinates, vehicle matchups, or heist prep? Just ask!";
    }
}
