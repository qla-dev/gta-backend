<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class RevenueCatPurchases
{
    public function sync(User $user, string $productId): int
    {
        $coins = (int) (config('purchases.coin_products')[$productId] ?? 0);
        if (! $coins) {
            return 0;
        }

        $transactions = collect($this->purchases($user))
            ->filter(fn (array $p) => $p['product'] === $productId)
            ->sortBy('purchased_at')->values()->all();

        return DB::transaction(function () use ($user, $productId, $coins, $transactions) {
            $granted = 0;
            foreach ($transactions as $tx) {
                $granted += DB::table('revenuecat_purchases')->insertOrIgnore([
                    'user_id' => $user->id,
                    'transaction_id' => $tx['id'],
                    'product_id' => $productId,
                    'coins' => $coins,
                    'purchased_at' => $tx['purchased_at'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]) * $coins;
            }
            if ($granted) {
                DB::table('users')->where('id', $user->id)->increment('ai_coins', $granted);
            }

            return $granted;
        });
    }

    /** @return list<array{id: string, product: ?string, purchased_at: ?Carbon}> */
    private function purchases(User $user): array
    {
        $products = collect($this->list('products'))->pluck('store_identifier', 'id');

        return collect($this->list($this->customer($user).'/purchases'))
            ->filter(fn ($p) => ($p['status'] ?? null) === 'owned'
                && is_string($p['store_purchase_identifier'] ?? null)
                && $p['store_purchase_identifier'] !== '')
            ->map(fn ($p) => [
                'id' => $p['store_purchase_identifier'],
                'product' => $products[$p['product_id'] ?? ''] ?? null,
                'purchased_at' => isset($p['purchased_at']) ? Carbon::createFromTimestampMs($p['purchased_at']) : null,
            ])->values()->all();
    }

    private function customer(User $user): string
    {
        return 'customers/'.rawurlencode(config('purchases.app_user_prefix').$user->id);
    }

    private function list(string $path): array
    {
        $key = config('purchases.secret_api_key');
        abort_unless($key, 503, 'Purchases are not configured yet.');

        $url = 'https://api.revenuecat.com/v2/projects/'.rawurlencode(config('purchases.project_id')).'/'.$path;
        $items = [];

        for ($page = 0; $url && $page < 20; $page++) {
            $response = Http::withToken($key)->acceptJson()->timeout(10)->get($url, $page ? [] : ['limit' => 100]);
            if ($response->notFound() && $response->json('type') === 'resource_missing') {
                return [];
            }
            abort_unless($response->successful(), 502, 'The purchase could not be verified.');
            $items = [...$items, ...(array) $response->json('items', [])];
            $url = $response->json('next_page');
        }

        return $items;
    }
}
