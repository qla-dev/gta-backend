<?php

namespace App\Http\Controllers;

use App\Models\Crew;
use App\Models\CrewMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $crewIds = CrewMember::where('user_id', $request->user()->id)->pluck('crew_id');

        $crews = Crew::whereIn('id', $crewIds)
            ->orWhere('owner_id', $request->user()->id)
            ->with(['members.user:id,name', 'owner:id,name'])
            ->get()
            ->map(fn ($c) => $this->formatCrew($c));

        return response()->json(['crews' => $crews]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:32',
            'tag' => 'required|string|max:8',
            'color' => 'nullable|string|max:16',
        ]);

        $crew = Crew::create([
            'owner_id' => $request->user()->id,
            'name' => $request->input('name'),
            'tag' => strtoupper($request->input('tag')),
            'color' => $request->input('color', '#FF4F91'),
            'invite_code' => Crew::generateCode(),
        ]);

        CrewMember::create([
            'crew_id' => $crew->id,
            'user_id' => $request->user()->id,
            'role' => 'leader',
        ]);

        $crew->load(['members.user:id,name', 'owner:id,name']);

        return response()->json(['crew' => $this->formatCrew($crew)], 201);
    }

    public function join(Request $request): JsonResponse
    {
        $request->validate(['invite_code' => 'required|string|size:6']);

        $crew = Crew::where('invite_code', strtoupper($request->input('invite_code')))->first();

        if (! $crew) {
            return response()->json(['error' => 'Invalid invite code.'], 404);
        }

        $exists = CrewMember::where('crew_id', $crew->id)
            ->where('user_id', $request->user()->id)
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'Already a member.'], 409);
        }

        if ($crew->members()->count() >= 20) {
            return response()->json(['error' => 'Crew is full.'], 422);
        }

        CrewMember::create([
            'crew_id' => $crew->id,
            'user_id' => $request->user()->id,
            'role' => 'member',
        ]);

        $crew->load(['members.user:id,name', 'owner:id,name']);

        return response()->json(['crew' => $this->formatCrew($crew)], 201);
    }

    public function leave(Request $request, int $id): JsonResponse
    {
        $crew = Crew::findOrFail($id);

        CrewMember::where('crew_id', $id)
            ->where('user_id', $request->user()->id)
            ->delete();

        if ($crew->owner_id === $request->user()->id) {
            $next = CrewMember::where('crew_id', $id)->orderBy('id')->first();
            if ($next) {
                $crew->update(['owner_id' => $next->user_id]);
                $next->update(['role' => 'leader']);
            } else {
                $crew->delete();
                return response()->json(['left' => true, 'deleted' => true]);
            }
        }

        return response()->json(['left' => true]);
    }

    private function formatCrew(Crew $crew): array
    {
        return [
            'id' => $crew->id,
            'name' => $crew->name,
            'tag' => $crew->tag,
            'color' => $crew->color,
            'invite_code' => $crew->invite_code,
            'owner' => ['id' => $crew->owner->id, 'name' => $crew->owner->name],
            'members' => $crew->members->map(fn ($m) => [
                'id' => $m->user_id,
                'name' => $m->user->name ?? 'Player',
                'role' => $m->role,
            ]),
        ];
    }
}
