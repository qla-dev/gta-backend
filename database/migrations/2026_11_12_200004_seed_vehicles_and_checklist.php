<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('vehicles')->insert([
            ['name' => 'Pegassi Infernus', 'category' => 'Super', 'speed' => '210 mph', 'color' => '#FF4F91', 'icon' => 'car-sport', 'description' => 'The Infernus is Vice City royalty — a mid-engine V12 hypercar built for the neon-lit straightaways of Ocean Beach Boulevard.', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Grotti Cheetah Classic', 'category' => 'Sports Classic', 'speed' => '178 mph', 'color' => '#FFB800', 'icon' => 'car-sport', 'description' => 'A 1980s wedge-shaped legend reborn. The Cheetah Classic turns heads at every stoplight in Little Havana.', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Bravado Gauntlet Hellfire', 'category' => 'Muscle', 'speed' => '165 mph', 'color' => '#FF6B35', 'icon' => 'car-sport', 'description' => 'Supercharged American muscle with a 6.2L V8 that makes the whole of Port Gellhorn shake when you floor it.', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Karin Sultan RS', 'category' => 'Tuners', 'speed' => '155 mph', 'color' => '#4CAF50', 'icon' => 'car-sport', 'description' => 'The tuner scene darling — an AWD rally sedan that drifts Leonida mountain passes as well as it grips city corners.', 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Lampadati Tigon', 'category' => 'Super', 'speed' => '205 mph', 'color' => '#9D67FF', 'icon' => 'car-sport', 'description' => 'Italian open-top hybrid hypercar. Equal parts art and engineering, perfect for the Vice City waterfront.', 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Übermacht Sentinel XS', 'category' => 'Sedans', 'speed' => '142 mph', 'color' => '#00BCD4', 'icon' => 'car-sport', 'description' => 'A luxury sport sedan favored by Leonida business execs and getaway drivers alike.', 'sort_order' => 6, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Shitzu Suntrap', 'category' => 'Boats', 'speed' => '85 kn', 'color' => '#00D4FF', 'icon' => 'boat', 'description' => 'A twin-hull speedboat built for the Leonida Keys. Fast enough to outrun the Coast Guard on a good day.', 'sort_order' => 7, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Western Daemon', 'category' => 'Motorcycles', 'speed' => '126 mph', 'color' => '#F44336', 'icon' => 'bicycle', 'description' => 'A chopper with attitude. Chrome pipes, ape hangers, and enough torque to power-slide through Grassrivers mud.', 'sort_order' => 8, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Buckingham Volatus', 'category' => 'Helicopters', 'speed' => '160 mph', 'color' => '#607D8B', 'icon' => 'airplane', 'description' => 'Executive chopper with leather interior and gold trim. The preferred transport of Leonida cartel bosses.', 'sort_order' => 9, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Vapid Dominator GT', 'category' => 'Muscle', 'speed' => '170 mph', 'color' => '#E91E63', 'icon' => 'car-sport', 'description' => 'A modern pony car that bridges classic American power with track-ready handling. Drag strip king of Vice City.', 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('checklist_items')->insert([
            ['title' => 'Complete Vice City Intro', 'category' => 'Story Missions', 'progress_total' => 1, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'First Vice Federal Vault Heist', 'category' => 'Heists', 'progress_total' => 1, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Hidden Packages', 'category' => 'Collectibles', 'progress_total' => 100, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Stunt Jumps', 'category' => 'Activities', 'progress_total' => 50, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Leonida Stranger Encounters', 'category' => 'Strangers & Freaks', 'progress_total' => 15, 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'All Safehouses Purchased', 'category' => 'Properties', 'progress_total' => 12, 'sort_order' => 6, 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Street Races Won', 'category' => 'Activities', 'progress_total' => 20, 'sort_order' => 7, 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Weapon Stashes Found', 'category' => 'Collectibles', 'progress_total' => 30, 'sort_order' => 8, 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Leonida Business Empires', 'category' => 'Empire', 'progress_total' => 8, 'sort_order' => 9, 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Complete All Heists as Lucia', 'category' => 'Heists', 'progress_total' => 6, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        DB::table('vehicles')->truncate();
        DB::table('checklist_items')->truncate();
    }
};
