<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description');
            $table->string('category', 32);
            $table->string('district');
            $table->decimal('map_x', 5, 2);
            $table->decimal('map_y', 5, 2);
            $table->string('reward')->nullable();
            $table->unsignedInteger('progress_total')->default(1);
            $table->boolean('is_dummy')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('mission_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mission_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('not_started');
            $table->unsignedInteger('progress_current')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'mission_id']);
        });

        // Fictional placeholders, not a claim about the released game's mission list.
        $missions = [
            ['vice-city-arrival', 'The Vice City Arrival', 'Meet your contact at the Ocean Beach motel and collect a starter ride.', 'story', 'Vice Beach', 82, 42, '$15,000 + starter ride', 1],
            ['ocean-drive-pickup', 'Ocean Drive Pickup', 'Collect a package from the Art Deco strip and deliver it across town.', 'story', 'Vice Beach', 83, 44, '$8,000', 1],
            ['little-havana-connection', 'The Little Havana Connection', 'Find a new contact and make your first neighborhood delivery.', 'story', 'Little Havana', 57, 48, '$12,000', 1],
            ['neon-after-hours', 'Neon After Hours', 'Meet the crew outside the Malibu Club after sunset.', 'story', 'Vice Beach', 84, 48, '$18,000', 1],
            ['port-gellhorn-manifest', 'Port Gellhorn Manifest', 'Recover a missing cargo manifest from the container terminal.', 'story', 'Port Gellhorn', 24, 56, '$25,000', 1],
            ['keys-offshore-run', 'Keys Offshore Run', 'Deliver a sealed crate between two docks in the Leonida Keys.', 'story', 'Leonida Keys', 36, 80, '$35,000', 1],
            ['downtown-diversion', 'Downtown Diversion', 'Create a diversion while your crew retrieves its missing equipment.', 'story', 'Downtown Vice', 68, 36, '$22,000', 1],
            ['starfish-invitation', 'The Starfish Invitation', 'Visit the island estate and meet a potential business partner.', 'story', 'Starfish Island', 72, 50, '$40,000', 1],
            ['ambrosia-night-shift', 'Ambrosia Night Shift', 'Recover three lost supply crates near the sugar refineries.', 'story', 'Ambrosia', 39, 42, '$28,000', 3],
            ['last-light-at-pelican', 'Last Light at Pelican Key', 'Reach the stilt hideaway before sunset to finish the first chapter.', 'story', 'Leonida Keys', 36, 80, '$50,000', 1],
            ['container-yard-heist', 'Container Yard Heist', 'Complete the three preparation stages for a fictional cargo-yard score.', 'heist', 'Port Gellhorn', 25, 57, '$280,000', 3],
            ['grand-royale-vault', 'The Grand Royale Vault', 'Plan, prepare, and complete a fictional downtown vault operation.', 'heist', 'Downtown Vice', 66, 30, '$2,500,000', 3],
            ['marina-night-job', 'The Marina Night Job', 'Collect the access key and recover a boat from the marina.', 'heist', 'Starfish Island', 74, 53, '$180,000', 2],
            ['midnight-street-races', 'Midnight Street Races', 'Win eight demo street-racing events across the city.', 'activity', 'Vice Beach', 80, 46, 'Custom engine tune', 8],
            ['airboat-safari', 'Lake Leonida Airboat Safari', 'Visit three marked wetland checkpoints by airboat.', 'activity', 'Lake Leonida', 46, 24, 'Airboat paint finish', 3],
            ['gator-coast-time-trial', 'Gator Coast Time Trial', 'Complete a lap challenge at the coastal raceway.', 'activity', 'Port Gellhorn', 28, 64, '$10,000', 1],
            ['coastline-packages', 'Coastline Packages', 'Find 20 demo package locations along the Leonida shoreline.', 'collectible', 'Leonida Keys', 32, 84, '$100,000', 20],
            ['art-deco-murals', 'Art Deco Neon Murals', 'Photograph ten demo murals in Vice Beach and Little Haiti.', 'collectible', 'Little Haiti', 55, 44, 'Neon photo filter', 10],
            ['leonida-man', 'The Legend of Leonida Man', 'Investigate a strange story near the swamp sanctuary.', 'stranger', 'Lake Leonida', 48, 22, 'Alligator charm', 1],
            ['ghost-galleon', 'Ghost of the Spanish Galleon', 'Visit the southern reef and investigate a local ghost-ship rumor.', 'stranger', 'Leonida Keys', 28, 88, 'Antique compass', 1],
        ];
        $now = now();
        foreach ($missions as $index => [$slug, $title, $description, $category, $district, $x, $y, $reward, $total]) {
            DB::table('missions')->insert([
                'slug' => $slug, 'title' => $title, 'description' => $description,
                'category' => $category, 'district' => $district, 'map_x' => $x, 'map_y' => $y,
                'reward' => $reward, 'progress_total' => $total, 'is_dummy' => true,
                'is_active' => true, 'sort_order' => $index + 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_progress');
        Schema::dropIfExists('missions');
    }
};
