<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('bleeter_posts')->update(['likes_count' => 0]);
    }

    public function down(): void
    {
        // no-op
    }
};
