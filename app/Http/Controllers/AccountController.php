<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AccountController extends Controller
{
    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['confirmation' => ['required', 'in:DELETE']]);
        $user = $request->user();
        $ownedCrews = DB::table('crews')->where('owner_id', $user->id)->pluck('id');
        $images = Message::where('sender_id', $user->id)->orWhere('recipient_id', $user->id)
            ->orWhereIn('crew_id', $ownedCrews)->whereNotNull('image_url')->pluck('image_url');
        $paths = $images->filter()->map(function ($url) {
            $prefix = rtrim(config('app.url'), '/').'/storage/chat-images/';
            if (! str_starts_with($url, $prefix)) {
                return null;
            }
            $name = substr($url, strlen($prefix));

            return $name !== '' && basename($name) === $name ? 'chat-images/'.$name : null;
        })->filter()->values()->all();

        DB::transaction(function () use ($user, $paths) {
            if ($paths && ! Storage::disk('public')->delete($paths)) {
                throw new \RuntimeException('Could not remove uploaded images. Please retry account deletion.');
            }
            // Sanctum tokens are polymorphic and have no user foreign key.
            $user->tokens()->delete();
            DB::table('bleeter_posts')->whereIn('id', DB::table('bleeter_post_likes')
                ->where('user_id', $user->id)->select('bleeter_post_id'))
                ->where('likes_count', '>', 0)->decrement('likes_count');
            // Foreign-key cascades remove posts, comments, chats, crews, credits,
            // memberships, likes, notifications and device push registrations.
            $user->delete();
        });

        return response()->json(['deleted' => true]);
    }
}
