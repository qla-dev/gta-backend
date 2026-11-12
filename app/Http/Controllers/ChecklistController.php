<?php

namespace App\Http\Controllers;

use App\Models\ChecklistItem;
use App\Models\ChecklistProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChecklistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = ChecklistItem::orderBy('sort_order')->get();

        $progress = [];
        if ($request->user()) {
            $progress = ChecklistProgress::where('user_id', $request->user()->id)
                ->get()
                ->keyBy('checklist_item_id');
        }

        $result = $items->map(fn ($item) => [
            'id' => $item->id,
            'title' => $item->title,
            'category' => $item->category,
            'progress_total' => $item->progress_total,
            'progress_current' => $progress[$item->id]->progress_current ?? 0,
            'done' => (bool) ($progress[$item->id]->done ?? false),
        ]);

        return response()->json(['items' => $result]);
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $item = ChecklistItem::findOrFail($id);

        $progress = ChecklistProgress::firstOrCreate(
            ['user_id' => $request->user()->id, 'checklist_item_id' => $id],
            ['progress_current' => 0, 'done' => false],
        );

        $progress->done = ! $progress->done;
        if ($progress->done) {
            $progress->progress_current = $item->progress_total;
        }
        $progress->save();

        return response()->json([
            'id' => $item->id,
            'done' => $progress->done,
            'progress_current' => $progress->progress_current,
        ]);
    }

    public function updateProgress(Request $request, int $id): JsonResponse
    {
        $request->validate(['progress_current' => 'required|integer|min:0']);

        $item = ChecklistItem::findOrFail($id);
        $current = min($request->input('progress_current'), $item->progress_total);

        $progress = ChecklistProgress::firstOrCreate(
            ['user_id' => $request->user()->id, 'checklist_item_id' => $id],
            ['progress_current' => 0, 'done' => false],
        );

        $progress->progress_current = $current;
        $progress->done = $current >= $item->progress_total;
        $progress->save();

        return response()->json([
            'id' => $item->id,
            'done' => $progress->done,
            'progress_current' => $progress->progress_current,
        ]);
    }
}
