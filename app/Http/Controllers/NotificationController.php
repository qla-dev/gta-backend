<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['page' => 'sometimes|integer|min:1']);
        $query = DB::table('bleeter_notifications as n')->where('n.user_id', $request->user()->id);
        $unread = (clone $query)->whereNull('n.read_at')->count();
        $page = $query->leftJoin('users as actor', 'actor.id', '=', 'n.actor_id')
            ->join('bleeter_posts as post', 'post.id', '=', 'n.bleeter_post_id')
            ->select('n.id', 'n.type', 'n.read_at', 'n.created_at', 'n.bleeter_post_id as post_id',
                'post.body as post_body', 'actor.name as actor_name')
            ->orderByDesc('n.id')->simplePaginate(10);

        return response()->json([
            'notifications' => collect($page->items())->map(function ($item) {
                $item->created_at = Carbon::parse($item->created_at)->toIso8601String();

                return $item;
            }),
            'unread_count' => $unread,
            'meta' => ['page' => $page->currentPage(), 'has_more' => $page->hasMorePages()],
        ]);
    }

    public function read(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => 'required|array|max:100', 'ids.*' => 'integer|min:1']);
        DB::table('bleeter_notifications')->where('user_id', $request->user()->id)
            ->whereIn('id', $data['ids'])->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);
        $unread = DB::table('bleeter_notifications')->where('user_id', $request->user()->id)->whereNull('read_at')->count();

        return response()->json(['unread_count' => $unread]);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:191', 'regex:/^(Expo|Exponent)PushToken\[[a-zA-Z0-9_-]+\]$/'],
            'platform' => 'required|in:ios,android',
        ]);
        DB::table('push_tokens')->updateOrInsert(['token' => $data['token']], [
            'user_id' => $request->user()->id, 'platform' => $data['platform'],
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json(['registered' => true]);
    }

    public function unregister(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => 'required|string|max:191']);
        DB::table('push_tokens')->where('user_id', $request->user()->id)->where('token', $data['token'])->delete();

        return response()->json(['registered' => false]);
    }
}
