<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\UserBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BusinessController extends Controller
{
    public function index(): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();
        $ownership = $user ? UserBusiness::where('user_id', $user->id)->get()->keyBy('business_id') : collect();
        $businesses = Business::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (Business $business) => $this->serialize($business, $ownership->get($business->id)));

        return response()->json([
            'businesses' => $businesses,
            'summary' => ['total' => $businesses->count(), 'owned' => $businesses->where('owned', true)->count(),
                'daily_income' => $businesses->sum('daily_income'), 'dummy_count' => $businesses->where('is_dummy', true)->count()],
        ]);
    }

    public function updateState(Request $request, Business $business): JsonResponse
    {
        abort_unless($business->is_active, 404);
        $data = $request->validate([
            'owned' => ['required_without_all:level,status', 'boolean'],
            'level' => ['required_without_all:owned,status', 'integer', 'between:1,5'],
            'status' => ['required_without_all:owned,level', Rule::in(['operating', 'paused'])],
        ]);
        $state = DB::transaction(function () use ($request, $business, $data) {
            $state = UserBusiness::firstOrCreate(
                ['user_id' => $request->user()->id, 'business_id' => $business->id],
                ['owned' => false, 'level' => 1, 'status' => 'paused'],
            );
            $state = UserBusiness::whereKey($state->id)->lockForUpdate()->firstOrFail();
            $owned = (bool) ($data['owned'] ?? $state->owned);
            if (! $owned && ! array_key_exists('owned', $data)) {
                throw ValidationException::withMessages(['owned' => 'Mark this business as owned before recording its level or operation.']);
            }
            $level = $owned ? ($data['level'] ?? $state->level) : 1;
            $status = $owned ? ($data['status'] ?? ($state->owned ? $state->status : 'operating')) : 'paused';
            $state->update(['owned' => $owned, 'level' => $level, 'status' => $status]);

            return $state;
        });

        return response()->json(['business' => $this->serialize($business, $state)]);
    }

    private function serialize(Business $business, ?UserBusiness $state): array
    {
        $owned = $state?->owned ?? false;
        $level = $owned ? $state->level : 1;
        $status = $owned ? $state->status : 'paused';
        // A companion estimate only. No wallet charge, payout or purchase is created.
        $income = $owned && $status === 'operating' ? (int) round($business->base_daily_income * (1 + ($level - 1) * 0.25)) : 0;

        return [
            'id' => $business->id, 'slug' => $business->slug, 'name' => $business->name,
            'type' => $business->type, 'district' => $business->district, 'description' => $business->description,
            'icon' => $business->icon, 'color' => $business->color, 'purchase_price' => $business->purchase_price,
            'base_daily_income' => $business->base_daily_income, 'is_dummy' => $business->is_dummy,
            'owned' => $owned, 'level' => $level, 'status' => $status, 'daily_income' => $income,
        ];
    }
}
