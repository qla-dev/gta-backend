<?php

use App\Http\Controllers\AiChatController;
use App\Http\Controllers\AppleSessionController;
use App\Http\Controllers\BleeterController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\CrewController;
use App\Http\Controllers\GoogleSessionController;
use App\Http\Controllers\GameStateController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NewsCommentController;
use App\Services\RevenueCatPurchases;
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
Route::get('/bleeter/channels', [BleeterController::class, 'channels']);
Route::get('/bleeter/posts', [BleeterController::class, 'posts']);
Route::get('/bleeter/posts/{id}/comments', [BleeterController::class, 'comments']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AppleSessionController::class, 'me']);
    Route::post('/auth/sign-out', [AppleSessionController::class, 'destroy']);
    Route::post('/purchases', function (\Illuminate\Http\Request $request, RevenueCatPurchases $purchases) {
        $products = array_keys(config('purchases.coin_products'));
        $data = $request->validate([
            'product_id' => ['required', 'string', \Illuminate\Validation\Rule::in($products)],
        ]);
        $granted = $purchases->sync($request->user(), $data['product_id']);

        return response()->json([
            'ai_coins' => $request->user()->fresh()->ai_coins,
            'granted' => $granted,
        ]);
    });

    // News comments (auth required to post)
    Route::post('/news/{articleId}/comments', [NewsCommentController::class, 'store']);

    // Bleeter (auth required to post/like)
    Route::post('/bleeter/posts', [BleeterController::class, 'store']);
    Route::post('/bleeter/posts/{id}/like', [BleeterController::class, 'like']);
    Route::post('/bleeter/posts/{id}/comments', [BleeterController::class, 'storeComment']);

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
