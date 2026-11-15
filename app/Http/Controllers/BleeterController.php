<?php

namespace App\Http\Controllers;

use App\Models\BleeterChannel;
use App\Models\BleeterComment;
use App\Models\BleeterPost;
use App\Services\BleeterActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $data = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:50',
            'channel_id' => 'sometimes|integer|min:1',
        ]);

        $query = BleeterPost::with(['user:id,name', 'channel:id,name'])
            ->withCount('comments')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->has('channel_id')) {
            $query->where('channel_id', $request->input('channel_id'));
        }

        $page = $query->simplePaginate($data['limit'] ?? 10);
        $posts = $page->getCollection()->map(fn ($p) => [
            'id' => $p->id,
            'user_id' => $p->user_id,
            'user_name' => $p->user->name ?? 'Anonymous',
            'body' => $p->body,
            'channel_id' => $p->channel_id,
            'channel_name' => $p->channel->name ?? 'General',
            'likes_count' => $p->likes_count,
            'comments_count' => $p->comments_count,
            'created_at' => $p->created_at->toIso8601String(),
        ]);

        return response()->json([
            'posts' => $posts,
            'meta' => [
                'page' => $page->currentPage(),
                'limit' => $page->perPage(),
                'has_more' => $page->hasMorePages(),
            ],
        ]);
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
                'comments_count' => 0,
                'created_at' => $post->created_at->toIso8601String(),
            ],
        ], 201);
    }

    public function like(Request $request, int $id, BleeterActivity $activity): JsonResponse
    {
        $post = BleeterPost::findOrFail($id);
        $added = DB::table('bleeter_post_likes')->insertOrIgnore([
            'user_id' => $request->user()->id, 'bleeter_post_id' => $id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($added) {
            $post->increment('likes_count');
            $activity->notify($post, $request->user()->id, 'like');
        }
        $post->load(['user:id,name', 'channel:id,name']);
        $post->loadCount('comments');

        return response()->json([
            'post' => [
                'id' => $post->id,
                'user_name' => $post->user->name ?? 'Anonymous',
                'body' => $post->body,
                'channel_id' => $post->channel_id,
                'channel_name' => $post->channel->name ?? 'General',
                'likes_count' => $post->likes_count,
                'comments_count' => $post->comments_count,
                'created_at' => $post->created_at->toIso8601String(),
            ],
        ]);
    }

    public function comments(int $id): JsonResponse
    {
        $post = BleeterPost::findOrFail($id);

        $comments = $post->comments()
            ->with('user:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'user_name' => $c->user->name ?? 'Anonymous',
                'body' => $c->body,
                'created_at' => $c->created_at->toIso8601String(),
            ]);

        return response()->json(['comments' => $comments]);
    }

    public function storeComment(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'body' => 'required|string|max:500',
        ]);

        BleeterPost::findOrFail($id);

        $comment = BleeterComment::create([
            'user_id' => $request->user()->id,
            'bleeter_post_id' => $id,
            'body' => $request->input('body'),
        ]);

        $comment->load('user:id,name');
        app(BleeterActivity::class)->notify(BleeterPost::findOrFail($id), $request->user()->id, 'comment');

        return response()->json([
            'comment' => [
                'id' => $comment->id,
                'user_name' => $comment->user->name,
                'body' => $comment->body,
                'created_at' => $comment->created_at->toIso8601String(),
            ],
        ], 201);
    }
}
