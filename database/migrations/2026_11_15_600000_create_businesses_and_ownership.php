<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('type', 32);
            $table->string('district');
            $table->text('description');
            $table->string('icon', 40);
            $table->string('color', 7);
            $table->unsignedBigInteger('purchase_price');
            $table->unsignedInteger('base_daily_income');
            $table->boolean('is_dummy')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('user_businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->boolean('owned')->default(false);
            $table->unsignedTinyInteger('level')->default(1);
            $table->string('status', 16)->default('paused');
            $table->timestamps();
            $table->unique(['user_id', 'business_id']);
        });
        $now = now();
        DB::table('businesses')->insert([
            ['slug' => 'sunset-royale-casino', 'name' => 'Sunset Royale Casino', 'type' => 'Casino', 'district' => 'Downtown Vice',
                'description' => 'A fictional casino on the downtown waterfront, with a rooftop lounge and private gaming rooms.',
                'icon' => 'diamond', 'color' => '#FFB800', 'purchase_price' => 2400000, 'base_daily_income' => 18000,
                'is_dummy' => true, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'neon-palms-club', 'name' => 'Neon Palms Club', 'type' => 'Strip club', 'district' => 'Little Havana',
                'description' => 'A fictional adults-only nightlife venue with a neon-lit lounge and late-night events.',
                'icon' => 'sparkles', 'color' => '#FF4F91', 'purchase_price' => 850000, 'base_daily_income' => 8000,
                'is_dummy' => true, 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'ocean-drive-nightclub', 'name' => 'Ocean Drive Nightclub', 'type' => 'Nightclub', 'district' => 'Vice Beach',
                'description' => 'A fictional beachside nightclub with three floors, a rooftop DJ booth and an ocean terrace.',
                'icon' => 'musical-notes', 'color' => '#9D67FF', 'purchase_price' => 1600000, 'base_daily_income' => 12000,
                'is_dummy' => true, 'is_active' => true, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_businesses');
        Schema::dropIfExists('businesses');
    }
};
