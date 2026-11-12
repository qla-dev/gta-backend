<?php

namespace App\Http\Controllers;

use App\Models\NewsComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsCommentController extends Controller
{
    public function index(string $articleId): JsonResponse
    {
        $comments = NewsComment::where('article_id', $articleId)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'user_name' => $c->user->name ?? 'Player',
                'body' => $c->body,
                'created_at' => $c->created_at->toIso8601String(),
            ]);

        return response()->json(['comments' => $comments]);
    }

    public function store(Request $request, string $articleId): JsonResponse
    {
        $request->validate([
            'body' => 'required|string|max:1000',
        ]);

        $comment = NewsComment::create([
            'user_id' => $request->user()->id,
            'article_id' => $articleId,
            'body' => $request->input('body'),
        ]);

        return response()->json([
            'comment' => [
                'id' => $comment->id,
                'user_name' => $request->user()->name,
                'body' => $comment->body,
                'created_at' => $comment->created_at->toIso8601String(),
            ],
        ], 201);
    }
}
