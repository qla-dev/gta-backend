<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BleeterPaginationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // This suite never opens the database configured in the local .env.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
        ]);
        DB::purge('sqlite');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('bleeter_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('bleeter_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('channel_id');
            $table->text('body');
            $table->integer('likes_count')->default(0);
            $table->timestamps();
        });
        Schema::create('bleeter_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bleeter_post_id');
        });
        DB::table('bleeter_channels')->insert([
            ['id' => 1, 'name' => 'General'],
            ['id' => 2, 'name' => 'Rides'],
            ['id' => 3, 'name' => 'Empty'],
        ]);

        for ($id = 1; $id <= 25; $id++) {
            DB::table('bleeter_posts')->insert([
                'id' => $id,
                'body' => "Post {$id}",
                'channel_id' => $id % 2 === 1 ? 1 : 2,
                // Tied timestamps must still produce a deterministic order.
                'created_at' => '2026-11-15 12:00:00',
                'updated_at' => '2026-11-15 12:00:00',
            ]);
        }
        DB::table('bleeter_comments')->insert(['bleeter_post_id' => 25]);
    }

    public function test_the_feed_loads_ten_at_a_time_without_gaps_or_duplicates(): void
    {
        $seen = [];
        foreach ([1 => 10, 2 => 10, 3 => 5, 4 => 0] as $page => $count) {
            $response = $this->getJson("http://localhost/api/bleeter/posts?page={$page}")
                ->assertOk()
                ->assertJsonCount($count, 'posts')
                ->assertJsonPath('meta.page', $page)
                ->assertJsonPath('meta.limit', 10)
                ->assertJsonPath('meta.has_more', $page < 3);
            $seen = array_merge($seen, array_column($response->json('posts'), 'id'));
        }
        $this->assertSame(range(25, 1), $seen);
        $this->assertSame(25, count(array_unique($seen)));
    }

    public function test_channel_filtering_is_applied_to_every_page(): void
    {
        $first = $this->getJson('http://localhost/api/bleeter/posts?channel_id=1&page=1')
            ->assertOk()->assertJsonCount(10, 'posts')->assertJsonPath('meta.has_more', true);
        $second = $this->getJson('http://localhost/api/bleeter/posts?channel_id=1&page=2')
            ->assertOk()->assertJsonCount(3, 'posts')->assertJsonPath('meta.has_more', false);
        $posts = array_merge($first->json('posts'), $second->json('posts'));
        $this->assertSame(range(25, 1, -2), array_column($posts, 'id'));
        $this->assertSame([1], array_values(array_unique(array_column($posts, 'channel_id'))));
        $first->assertJsonPath('posts.0.comments_count', 1)
            ->assertJsonPath('posts.0.user_name', 'Anonymous');
    }

    public function test_empty_and_exact_size_pages_report_the_end_correctly(): void
    {
        $this->getJson('http://localhost/api/bleeter/posts?channel_id=3')->assertOk()
            ->assertJsonCount(0, 'posts')->assertJsonPath('meta.has_more', false);
        $this->getJson('http://localhost/api/bleeter/posts?limit=25')->assertOk()
            ->assertJsonCount(25, 'posts')->assertJsonPath('meta.has_more', false);
    }

    public function test_invalid_pagination_parameters_are_rejected(): void
    {
        foreach (['page=0', 'page=invalid', 'limit=0', 'limit=51', 'channel_id=0'] as $query) {
            $this->getJson("http://localhost/api/bleeter/posts?{$query}")->assertUnprocessable();
        }
    }
}
