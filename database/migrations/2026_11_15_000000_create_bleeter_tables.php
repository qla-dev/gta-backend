<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bleeter_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('bleeter_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained('bleeter_channels')->cascadeOnDelete();
            $table->text('body');
            $table->integer('likes_count')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('bleeter_channels')->insert([
            ['name' => 'General', 'description' => 'All the latest from Leonida', 'icon' => 'chatbubbles', 'color' => '#1DA1F2', 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Vice City', 'description' => 'Life in the city that never sleeps', 'icon' => 'business', 'color' => '#FF4F91', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Rides', 'description' => 'Cars, boats, and everything with an engine', 'icon' => 'car-sport', 'color' => '#FFB800', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Crime', 'description' => 'Heists, busts, and underworld intel', 'icon' => 'skull', 'color' => '#F44336', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Nightlife', 'description' => 'Clubs, bars, and after-hours spots', 'icon' => 'moon', 'color' => '#9D67FF', 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sports', 'description' => 'Scores, bets, and athletic feats', 'icon' => 'football', 'color' => '#4CAF50', 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('bleeter_posts')->insert([
            ['user_id' => null, 'channel_id' => 1, 'body' => 'Just pulled off the biggest score Vice City has ever seen. The Panther is MINE.', 'likes_count' => 14200, 'created_at' => $now->copy()->subHours(2), 'updated_at' => $now->copy()->subHours(2)],
            ['user_id' => null, 'channel_id' => 1, 'body' => 'BREAKING: High-speed boat chase through Vice Keys ends with suspects vanishing into mangrove tunnels. VCPD baffled.', 'likes_count' => 8900, 'created_at' => $now->copy()->subHours(4), 'updated_at' => $now->copy()->subHours(4)],
            ['user_id' => null, 'channel_id' => 2, 'body' => 'The new nightclub on Ocean Drive is absolutely insane. Three floors, rooftop pool, and the DJ lineup is stacked.', 'likes_count' => 5400, 'created_at' => $now->copy()->subHours(5), 'updated_at' => $now->copy()->subHours(5)],
            ['user_id' => null, 'channel_id' => 3, 'body' => 'Finally got the Pegassi Infernus in midnight purple. This thing handles like a dream on the coastal highway.', 'likes_count' => 11300, 'created_at' => $now->copy()->subHours(3), 'updated_at' => $now->copy()->subHours(3)],
            ['user_id' => null, 'channel_id' => 4, 'body' => 'Saw the Leonida Man again near the Grassrivers swamp. This time he was riding an alligator. I am NOT making this up.', 'likes_count' => 23100, 'created_at' => $now->copy()->subHours(6), 'updated_at' => $now->copy()->subHours(6)],
            ['user_id' => null, 'channel_id' => 5, 'body' => 'Vice City nightlife hits different at 3AM. The neon reflections on wet streets are something else.', 'likes_count' => 7200, 'created_at' => $now->copy()->subHours(8), 'updated_at' => $now->copy()->subHours(8)],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('bleeter_posts');
        Schema::dropIfExists('bleeter_channels');
    }
};
