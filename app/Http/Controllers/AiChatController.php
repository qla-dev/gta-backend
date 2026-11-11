<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiChatController extends Controller
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are V.I.C.E. (Virtual Intelligence Crime Engine), the built-in AI copilot for the fictional LeonidaOS smartphone in GTA VI (Grand Theft Auto VI).
You operate inside the neon-lit, satirical, sunny, and dangerous universe of Vice City and the state of Leonida.
Your personality is street-smart, tactical, slightly sarcastic, highly knowledgeable about criminal getaways, exotic supercars, underground chop shops, waterfront penthouses, Everglades alligators, and police evasion tactics.
Always stay in character as a fictional in-game operating system assistant.
Give crisp, exciting, helpful answers formatted with bullet points or quick tactical summaries when helpful.
PROMPT;

    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'history' => 'array|max:20',
            'history.*.role' => 'in:user,assistant',
            'history.*.content' => 'string|max:4000',
        ]);

        $apiKey = config('services.gemini.key');

        if (! $apiKey) {
            return response()->json([
                'reply' => $this->fallbackReply($request->input('message')),
                'source' => 'local',
            ]);
        }

        $contents = [];

        foreach ($request->input('history', []) as $msg) {
            $contents[] = [
                'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $msg['content']]],
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => self::SYSTEM_PROMPT."\n\nUser Question: ".$request->input('message')]],
        ];

        try {
            $response = Http::timeout(30)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}",
                ['contents' => $contents]
            );

            if ($response->successful()) {
                $text = $response->json('candidates.0.content.parts.0.text', '');
                if ($text) {
                    return response()->json(['reply' => $text, 'source' => 'gemini']);
                }
            }
        } catch (\Throwable) {
            // fall through to local
        }

        return response()->json([
            'reply' => $this->fallbackReply($request->input('message')),
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
