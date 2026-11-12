<?php

use App\Http\Controllers\AiChatController;
use App\Http\Controllers\AppleSessionController;
use App\Http\Controllers\GoogleSessionController;
use App\Http\Controllers\GameStateController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        $database = ['connected' => true, 'name' => DB::connection()->getDatabaseName()];
    } catch (Throwable) {
        $database = ['connected' => false];
    }

    return response()->json([
        'app' => config('app.name'),
        'status' => $database['connected'] ? 'ok' : 'degraded',
        'database' => $database,
        'time' => now()->toIso8601String(),
    ], $database['connected'] ? 200 : 503);
});

Route::post('/ai/chat', [AiChatController::class, 'chat']);

Route::post('/state/load', [GameStateController::class, 'load']);
Route::post('/state/save', [GameStateController::class, 'save']);

Route::post('/auth/apple/challenge', [AppleSessionController::class, 'challenge']);
Route::post('/auth/apple', [AppleSessionController::class, 'store']);
Route::post('/auth/google', [GoogleSessionController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AppleSessionController::class, 'me']);
    Route::post('/auth/sign-out', [AppleSessionController::class, 'destroy']);
    Route::post('/purchases', function (\Illuminate\Http\Request $request) {
        $request->validate(['product_id' => 'required|string']);
        return response()->json(['ai_coins' => $request->user()->ai_coins]);
    });
});
