<?php

use App\Http\Controllers\AiChatController;
use App\Http\Controllers\AppleSessionController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\CrewController;
use App\Http\Controllers\GoogleSessionController;
use App\Http\Controllers\GameStateController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NewsCommentController;
use App\Http\Controllers\VehicleController;
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

// Public endpoints
Route::get('/vehicles', [VehicleController::class, 'index']);
Route::get('/checklist', [ChecklistController::class, 'index']);
Route::get('/news/{articleId}/comments', [NewsCommentController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AppleSessionController::class, 'me']);
    Route::post('/auth/sign-out', [AppleSessionController::class, 'destroy']);
    Route::post('/purchases', function (\Illuminate\Http\Request $request) {
        $request->validate(['product_id' => 'required|string']);
        return response()->json(['ai_coins' => $request->user()->ai_coins]);
    });

    // News comments (auth required to post)
    Route::post('/news/{articleId}/comments', [NewsCommentController::class, 'store']);

    // Checklist progress (auth required)
    Route::post('/checklist/{id}/toggle', [ChecklistController::class, 'toggle']);
    Route::post('/checklist/{id}/progress', [ChecklistController::class, 'updateProgress']);

    // Crews
    Route::get('/crews', [CrewController::class, 'index']);
    Route::post('/crews', [CrewController::class, 'store']);
    Route::post('/crews/join', [CrewController::class, 'join']);
    Route::post('/crews/{id}/leave', [CrewController::class, 'leave']);

    // Crew messages
    Route::get('/crews/{id}/messages', [MessageController::class, 'crewMessages']);
    Route::post('/crews/{id}/messages', [MessageController::class, 'sendCrewMessage']);

    // Direct messages
    Route::get('/messages/conversations', [MessageController::class, 'conversations']);
    Route::get('/messages/dm/{partnerId}', [MessageController::class, 'directMessages']);
    Route::post('/messages/dm/{partnerId}', [MessageController::class, 'sendDirectMessage']);
    Route::get('/messages/search-users', [MessageController::class, 'searchUsers']);
});
