<?php

namespace App\Http\Controllers;

use App\Models\CrewMember;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    /**
     * List crew messages (must be a member).
     */
    public function crewMessages(Request $request, int $crewId): JsonResponse
    {
        $isMember = CrewMember::where('crew_id', $crewId)
            ->where('user_id', $request->user()->id)
            ->exists();

        if (! $isMember) {
            return response()->json(['error' => 'Not a crew member.'], 403);
        }

        $messages = Message::where('crew_id', $crewId)
            ->with('sender:id,name')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($m) => $this->format($m));

        return response()->json(['messages' => $messages]);
    }

    /**
     * Send a message to a crew chat.
     */
    public function sendCrewMessage(Request $request, int $crewId): JsonResponse
    {
        $request->validate(['body' => 'required|string|max:2000']);

        $isMember = CrewMember::where('crew_id', $crewId)
            ->where('user_id', $request->user()->id)
            ->exists();

        if (! $isMember) {
            return response()->json(['error' => 'Not a crew member.'], 403);
        }

        $message = Message::create([
            'sender_id' => $request->user()->id,
            'crew_id' => $crewId,
            'body' => $request->input('body'),
        ]);

        $message->load('sender:id,name');

        return response()->json(['message' => $this->format($message)], 201);
    }

    /**
     * List DM conversations (latest message per conversation partner).
     */
    public function conversations(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $sent = Message::whereNull('crew_id')
            ->where('sender_id', $userId)
            ->selectRaw('recipient_id as partner_id, MAX(id) as latest_id')
            ->groupBy('recipient_id');

        $received = Message::whereNull('crew_id')
            ->where('recipient_id', $userId)
            ->selectRaw('sender_id as partner_id, MAX(id) as latest_id')
            ->groupBy('sender_id');

        $union = $sent->unionAll($received);

        $latestIds = \DB::query()
            ->fromSub($union, 'combined')
            ->selectRaw('partner_id, MAX(latest_id) as latest_id')
            ->groupBy('partner_id')
            ->orderByDesc('latest_id')
            ->limit(50)
            ->get();

        $messageIds = $latestIds->pluck('latest_id');
        $partnerIds = $latestIds->pluck('partner_id');

        $messages = Message::whereIn('id', $messageIds)
            ->with('sender:id,name')
            ->get()
            ->keyBy('id');

        $partners = User::whereIn('id', $partnerIds)->get()->keyBy('id');

        $conversations = $latestIds->map(function ($row) use ($messages, $partners, $userId) {
            $msg = $messages[$row->latest_id] ?? null;
            $partner = $partners[$row->partner_id] ?? null;
            if (! $msg || ! $partner) {
                return null;
            }

            return [
                'partner_id' => (int) $partner->id,
                'partner_name' => $partner->name,
                'last_message' => $msg->body,
                'last_message_at' => $msg->created_at->toIso8601String(),
                'is_mine' => $msg->sender_id === $userId,
            ];
        })->filter()->values();

        return response()->json(['conversations' => $conversations]);
    }

    /**
     * List DMs with a specific user.
     */
    public function directMessages(Request $request, int $partnerId): JsonResponse
    {
        $userId = $request->user()->id;

        $messages = Message::whereNull('crew_id')
            ->where(function ($q) use ($userId, $partnerId) {
                $q->where(function ($q2) use ($userId, $partnerId) {
                    $q2->where('sender_id', $userId)->where('recipient_id', $partnerId);
                })->orWhere(function ($q2) use ($userId, $partnerId) {
                    $q2->where('sender_id', $partnerId)->where('recipient_id', $userId);
                });
            })
            ->with('sender:id,name')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($m) => $this->format($m));

        return response()->json(['messages' => $messages]);
    }

    /**
     * Send a DM to a user.
     */
    public function sendDirectMessage(Request $request, int $partnerId): JsonResponse
    {
        $request->validate(['body' => 'required|string|max:2000']);

        if ($partnerId === $request->user()->id) {
            return response()->json(['error' => 'Cannot message yourself.'], 422);
        }

        $partner = User::find($partnerId);
        if (! $partner) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $message = Message::create([
            'sender_id' => $request->user()->id,
            'recipient_id' => $partnerId,
            'body' => $request->input('body'),
        ]);

        $message->load('sender:id,name');

        return response()->json(['message' => $this->format($message)], 201);
    }

    /**
     * Search users by name (for starting a new DM).
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2|max:32']);

        $users = User::where('name', 'like', '%'.$request->input('q').'%')
            ->where('id', '!=', $request->user()->id)
            ->select('id', 'name')
            ->limit(20)
            ->get();

        return response()->json(['users' => $users]);
    }

    private function format(Message $m): array
    {
        return [
            'id' => $m->id,
            'sender_id' => $m->sender_id,
            'sender_name' => $m->sender->name ?? 'Player',
            'body' => $m->body,
            'created_at' => $m->created_at->toIso8601String(),
        ];
    }
}
