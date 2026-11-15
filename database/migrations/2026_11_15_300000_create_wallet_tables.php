<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->integer('amount');
            $table->string('icon');
            $table->timestamps();
        });

        $now = now();

        DB::table('wallet_transactions')->insert([
            ['user_id' => null, 'title' => 'Heist Payout — Vice Federal', 'amount' => 2450000, 'icon' => 'diamond', 'created_at' => $now->copy()->subHours(2), 'updated_at' => $now->copy()->subHours(2)],
            ['user_id' => null, 'title' => 'Vehicle Purchase — Pegassi Infernus', 'amount' => -1200000, 'icon' => 'car-sport', 'created_at' => $now->copy()->subHours(5), 'updated_at' => $now->copy()->subHours(5)],
            ['user_id' => null, 'title' => 'Property Income — Ocean Beach', 'amount' => 85000, 'icon' => 'home', 'created_at' => $now->copy()->subDays(1), 'updated_at' => $now->copy()->subDays(1)],
            ['user_id' => null, 'title' => 'Weapon Resupply — Ammu-Nation', 'amount' => -32500, 'icon' => 'shield', 'created_at' => $now->copy()->subDays(1), 'updated_at' => $now->copy()->subDays(1)],
            ['user_id' => null, 'title' => 'Race Winnings — Vice Keys Circuit', 'amount' => 145000, 'icon' => 'trophy', 'created_at' => $now->copy()->subDays(2), 'updated_at' => $now->copy()->subDays(2)],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
