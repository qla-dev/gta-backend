<?php

use App\Http\Controllers\AiChatController;
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
