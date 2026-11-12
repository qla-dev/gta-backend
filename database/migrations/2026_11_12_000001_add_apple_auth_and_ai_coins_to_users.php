<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('apple_id')->nullable()->unique()->after('id');
            $table->string('apple_email')->nullable()->after('email');
            $table->boolean('apple_email_private')->default(false)->after('apple_email');
            $table->unsignedInteger('ai_coins')->default(0)->after('apple_email_private');
            $table->unsignedInteger('free_messages_used')->default(0)->after('ai_coins');
            $table->date('free_messages_date')->nullable()->after('free_messages_used');

            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'apple_id',
                'apple_email',
                'apple_email_private',
                'ai_coins',
                'free_messages_used',
                'free_messages_date',
            ]);
        });
    }
};
