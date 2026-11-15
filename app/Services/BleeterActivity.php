<?php

namespace App\Services;

use App\Models\BleeterPost;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class BleeterActivity
{
    public function notify(BleeterPost $post, int $actorId, string $type): void
    {
        if (! $post->user_id || $post->user_id === $actorId) {
            return;
        }
        $id = DB::table('bleeter_notifications')->insertGetId([
            'user_id' => $post->user_id, 'actor_id' => $actorId,
            'bleeter_post_id' => $post->id, 'type' => $type,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $tokens = DB::table('push_tokens')->where('user_id', $post->user_id)->pluck('token');
        if ($tokens->isEmpty()) {
            return;
        }
        $messages = $tokens->map(fn ($token) => [
            'to' => $token, 'title' => 'Bleeter',
            'body' => $type === 'like' ? 'Someone liked your bleet.' : 'Someone commented on your bleet.',
            'sound' => 'default', 'channelId' => 'bleeter',
            'data' => ['screen' => 'BleeterNotifications', 'notificationId' => $id],
        ]);
        foreach ($messages->chunk(100) as $chunk) {
            try {
                $response = Http::acceptJson()->timeout(5)->retry(2, 200)
                    ->post('https://exp.host/--/api/v2/push/send', $chunk->values()->all());
                foreach ($response->json('data', []) as $index => $ticket) {
                    if (($ticket['details']['error'] ?? '') === 'DeviceNotRegistered') {
                        DB::table('push_tokens')->where('token', $chunk->values()[$index]['to'])->delete();
                    }
                }
            } catch (\Throwable $error) {
                // In-app activity is retained even when a push service is offline.
                report($error);
            }
        }
    }
}
