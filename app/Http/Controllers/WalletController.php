<?php

namespace App\Http\Controllers;

use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function index(): JsonResponse
    {
        $transactions = WalletTransaction::orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'amount' => $t->amount,
                'icon' => $t->icon,
                'created_at' => $t->created_at->toIso8601String(),
            ]);

        $balance = WalletTransaction::sum('amount');

        return response()->json([
            'transactions' => $transactions,
            'balance' => (int) $balance,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:100',
            'amount' => 'required|integer',
            'icon' => 'required|string',
        ]);

        $transaction = WalletTransaction::create([
            'user_id' => $request->user()->id,
            'title' => $request->input('title'),
            'amount' => $request->input('amount'),
            'icon' => $request->input('icon'),
        ]);

        $balance = (int) WalletTransaction::sum('amount');

        return response()->json([
            'transaction' => [
                'id' => $transaction->id,
                'title' => $transaction->title,
                'amount' => $transaction->amount,
                'icon' => $transaction->icon,
                'created_at' => $transaction->created_at->toIso8601String(),
            ],
            'balance' => $balance,
        ], 201);
    }
}
