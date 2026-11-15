<?php

namespace App\Http\Controllers;

use App\Models\Mission;
use App\Models\MissionProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MissionController extends Controller
{
    public function index(): JsonResponse
    {
        // Public catalog with optional bearer auth, so signed-in GETs include private progress.
        $user = Auth::guard('sanctum')->user();
        $progress = $user ? MissionProgress::where('user_id', $user->id)->get()->keyBy('mission_id') : collect();
        $missions = Mission::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (Mission $mission) => $this->serialize($mission, $progress->get($mission->id)));
        $completed = $missions->where('status', 'completed')->count();

        return response()->json([
            'missions' => $missions,
            'summary' => [
                'total' => $missions->count(), 'completed' => $completed,
                'in_progress' => $missions->where('status', 'in_progress')->count(),
                'percent' => $missions->isEmpty() ? 0 : (int) round($completed / $missions->count() * 100),
                'dummy_count' => $missions->where('is_dummy', true)->count(),
            ],
        ]);
    }

    public function updateProgress(Request $request, Mission $mission): JsonResponse
    {
        abort_unless($mission->is_active, 404);
        $data = $request->validate([
            'status' => ['required_without:progress_current', Rule::in(['not_started', 'in_progress', 'completed'])],
            'progress_current' => ['required_without:status', 'integer', 'min:0', 'max:'.$mission->progress_total],
        ]);

        $progress = DB::transaction(function () use ($request, $mission, $data) {
            $progress = MissionProgress::firstOrCreate(
                ['user_id' => $request->user()->id, 'mission_id' => $mission->id],
                ['status' => 'not_started', 'progress_current' => 0],
            );
            $progress = MissionProgress::whereKey($progress->id)->lockForUpdate()->firstOrFail();
            $current = $data['progress_current'] ?? $progress->progress_current;
            $status = $data['status'] ?? ($current >= $mission->progress_total ? 'completed' : ($current > 0 ? 'in_progress' : 'not_started'));
            if ($status === 'completed') {
                $current = $mission->progress_total;
            } elseif ($status === 'not_started') {
                $current = 0;
            } else {
                $current = min($current, $mission->progress_total - 1);
            }
            $progress->update(['status' => $status, 'progress_current' => $current]);

            return $progress;
        });

        return response()->json(['mission' => $this->serialize($mission, $progress)]);
    }

    private function serialize(Mission $mission, ?MissionProgress $progress): array
    {
        $current = min($mission->progress_total, $progress?->progress_current ?? 0);

        return [
            'id' => $mission->id, 'slug' => $mission->slug, 'title' => $mission->title,
            'description' => $mission->description, 'category' => $mission->category,
            'district' => $mission->district, 'map_x' => $mission->map_x, 'map_y' => $mission->map_y,
            'reward' => $mission->reward, 'is_dummy' => $mission->is_dummy,
            'progress_total' => $mission->progress_total, 'progress_current' => $current,
            'status' => $progress?->status ?? 'not_started',
            'done' => ($progress?->status ?? 'not_started') === 'completed',
        ];
    }
}
