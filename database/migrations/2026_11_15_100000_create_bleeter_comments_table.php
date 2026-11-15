<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bleeter_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('bleeter_post_id')->constrained('bleeter_posts')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        $now = now();

        DB::table('bleeter_comments')->insert([
            ['user_id' => null, 'bleeter_post_id' => 1, 'body' => 'No way you got the Panther! That thing is locked down tighter than Fort Zancudo.', 'created_at' => $now->copy()->subMinutes(90), 'updated_at' => $now->copy()->subMinutes(90)],
            ['user_id' => null, 'bleeter_post_id' => 1, 'body' => 'VCPD is gonna be on your tail for weeks lmao', 'created_at' => $now->copy()->subMinutes(60), 'updated_at' => $now->copy()->subMinutes(60)],
            ['user_id' => null, 'bleeter_post_id' => 1, 'body' => 'Respect. That heist is no joke. What crew did you run with?', 'created_at' => $now->copy()->subMinutes(30), 'updated_at' => $now->copy()->subMinutes(30)],
            ['user_id' => null, 'bleeter_post_id' => 5, 'body' => 'The Leonida Man is just a myth... right? RIGHT?', 'created_at' => $now->copy()->subMinutes(120), 'updated_at' => $now->copy()->subMinutes(120)],
            ['user_id' => null, 'bleeter_post_id' => 5, 'body' => 'I saw him too last week near Grassrivers! He threw a catfish at my boat!', 'created_at' => $now->copy()->subMinutes(45), 'updated_at' => $now->copy()->subMinutes(45)],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('bleeter_comments');
    }
};
