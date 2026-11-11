<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameStateController extends Controller
{
    public function load(Request $request): JsonResponse
    {
        $request->validate([
            'device_id' => 'required|string|max:64',
        ]);

        $state = DB::table('game_states')
            ->where('device_id', $request->input('device_id'))
            ->first();

        if (! $state) {
            return response()->json(['exists' => false, 'state' => null]);
        }

        return response()->json([
            'exists' => true,
            'state' => json_decode($state->state_data, true),
            'updated_at' => $state->updated_at,
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $request->validate([
            'device_id' => 'required|string|max:64',
            'state' => 'required|array',
        ]);

        DB::table('game_states')->updateOrInsert(
            ['device_id' => $request->input('device_id')],
            [
                'state_data' => json_encode($request->input('state')),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json(['saved' => true]);
    }
}
