<?php

namespace App\Http\Controllers;

use App\Models\BleeterChannel;
use App\Models\BleeterPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BleeterController extends Controller
{
    public function channels(): JsonResponse
    {
        $channels = BleeterChannel::orderBy('sort_order')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'icon' => $c->icon,
                'color' => $c->color,
                'sort_order' => $c->sort_order,
            ]);

        return response()->json(['channels' => $channels]);
    }

    public function posts(Request $request): JsonResponse
    {
        $query = BleeterPost::with(['user:id,name', 'channel:id,name'])
            ->orderByDesc('created_at')
            ->limit(50);

        if ($request->has('channel_id')) {
            $query->where('channel_id', $request->input('channel_id'));
        }

        $posts = $query->get()->map(fn ($p) => [
            'id' => $p->id,
            'user_name' => $p->user->name ?? 'Anonymous',
            'body' => $p->body,
            'channel_id' => $p->channel_id,
            'channel_name' => $p->channel->name ?? 'General',
            'likes_count' => $p->likes_count,
            'created_at' => $p->created_at->toIso8601String(),
        ]);

        return response()->json(['posts' => $posts]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'body' => 'required|string|max:280',
            'channel_id' => 'required|exists:bleeter_channels,id',
        ]);

        $post = BleeterPost::create([
            'user_id' => $request->user()->id,
            'channel_id' => $request->input('channel_id'),
            'body' => $request->input('body'),
        ]);

        $post->load(['user:id,name', 'channel:id,name']);

        return response()->json([
            'post' => [
                'id' => $post->id,
                'user_name' => $post->user->name,
                'body' => $post->body,
                'channel_id' => $post->channel_id,
                'channel_name' => $post->channel->name,
                'likes_count' => 0,
                'created_at' => $post->created_at->toIso8601String(),
            ],
        ], 201);
    }

    public function like(int $id): JsonResponse
    {
        $post = BleeterPost::findOrFail($id);
        $post->increment('likes_count');
        $post->load(['user:id,name', 'channel:id,name']);

        return response()->json([
            'post' => [
                'id' => $post->id,
                'user_name' => $post->user->name ?? 'Anonymous',
                'body' => $post->body,
                'channel_id' => $post->channel_id,
                'channel_name' => $post->channel->name ?? 'General',
                'likes_count' => $post->likes_count,
                'created_at' => $post->created_at->toIso8601String(),
            ],
        ]);
    }
}
