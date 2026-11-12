<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id');
            $table->string('name');
            $table->string('tag', 8);
            $table->string('color', 16)->default('#FF4F91');
            $table->string('invite_code', 16)->unique();
            $table->timestamps();

            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('crew_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('crew_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role', 16)->default('member');
            $table->timestamps();

            $table->foreign('crew_id')->references('id')->on('crews')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['crew_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_members');
        Schema::dropIfExists('crews');
    }
};
