<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('terms_version', 32)->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
        });
        // Archive article identifiers must fit without truncating comment links.
        Schema::table('news_comments', fn (Blueprint $table) => $table->string('article_id', 191)->change());
        Schema::create('support_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reported_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 16);
            $table->string('player_name')->nullable();
            $table->unsignedBigInteger('post_id')->nullable();
            $table->string('reason', 32)->nullable();
            $table->string('reply_to')->nullable();
            $table->string('subject');
            $table->text('message');
            $table->timestamp('mail_sent_at')->nullable();
            $table->timestamps();
        });
        Schema::create('push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 191)->unique();
            $table->string('platform', 16);
            $table->timestamps();
        });
        Schema::create('bleeter_post_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bleeter_post_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'bleeter_post_id']);
        });
        Schema::create('bleeter_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('bleeter_post_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bleeter_notifications');
        Schema::dropIfExists('bleeter_post_likes');
        Schema::dropIfExists('push_tokens');
        Schema::dropIfExists('support_reports');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['terms_version', 'terms_accepted_at']));
    }
};
