<?php

namespace Tests\Feature;

use App\Models\BleeterPost;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountSafetyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
            'cache.default' => 'array',
            'support.mailer' => 'array',
        ]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        Http::preventStrayRequests();
    }

    private function postFor(User $user): BleeterPost
    {
        return BleeterPost::create(['user_id' => $user->id, 'channel_id' => 1, 'body' => 'A bleet for testing']);
    }

    public function test_sign_in_requires_explicit_current_terms_acceptance(): void
    {
        foreach (['google' => ['id_token' => 'test'], 'apple' => ['identity_token' => 'test', 'challenge_id' => 'test']] as $provider => $identity) {
            $this->postJson("http://localhost/api/auth/{$provider}", $identity)->assertUnprocessable();
            $this->postJson("http://localhost/api/auth/{$provider}", [...$identity, 'accepted_terms' => false, 'terms_version' => '2026-11-15'])->assertUnprocessable();
            $this->postJson("http://localhost/api/auth/{$provider}", [...$identity, 'accepted_terms' => true, 'terms_version' => 'old'])->assertUnprocessable();
        }
        Http::assertNothingSent();
    }

    public function test_successful_sign_in_records_legal_acceptance(): void
    {
        Http::fake(['https://oauth2.googleapis.com/tokeninfo*' => Http::response([
            'sub' => 'test-google-subject', 'email' => 'player@example.test', 'name' => 'Terms Player',
            'aud' => config('services.google.web_client_id'), 'iss' => 'https://accounts.google.com',
        ])]);
        $this->postJson('http://localhost/api/auth/google', [
            'id_token' => 'test', 'accepted_terms' => true, 'terms_version' => '2026-11-15',
        ])->assertOk()->assertJsonPath('registered', true);
        $user = User::where('google_id', 'test-google-subject')->firstOrFail();
        $this->assertSame('2026-11-15', $user->terms_version);
        $this->assertNotNull($user->terms_accepted_at);
    }

    public function test_contact_form_delivers_to_the_admin_and_deduplicates_retries(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $data = ['request_id' => (string) Str::uuid(), 'type' => 'support', 'subject' => 'Help with the app',
            'message' => 'I need help with my account settings.', 'reply_to' => 'reply@example.test'];
        $first = $this->postJson('http://localhost/api/support/reports', $data)->assertCreated()->assertJsonPath('sent', true);
        $this->postJson('http://localhost/api/support/reports', $data)->assertOk()->assertJsonPath('reference', $first->json('reference'));
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $email = $messages->first()->getOriginalMessage();
        $this->assertSame('gta@qla.dev', $email->getTo()[0]->getAddress());
        $this->assertSame('reply@example.test', $email->getReplyTo()[0]->getAddress());
        $this->assertStringContainsString($data['message'], $email->getTextBody());
        $this->assertDatabaseCount('support_reports', 1);
    }

    public function test_player_report_records_the_player_reason_and_selected_bleet(): void
    {
        $reporter = User::factory()->create();
        $player = User::factory()->create();
        $post = $this->postFor($player);
        Sanctum::actingAs($reporter);
        $this->postJson('http://localhost/api/support/reports', [
            'request_id' => (string) Str::uuid(), 'type' => 'player', 'subject' => 'Player report',
            'reported_user_id' => $player->id, 'player_name' => $player->name,
            'post_id' => $post->id, 'reason' => 'harassment', 'message' => 'Please review the messages from this player.',
        ])->assertCreated();
        $this->assertDatabaseHas('support_reports', ['user_id' => $reporter->id,
            'reported_user_id' => $player->id, 'post_id' => $post->id, 'reason' => 'harassment']);
    }

    public function test_a_mail_failure_is_not_reported_as_success(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Mail::shouldReceive('mailer')->with('array')->andThrow(new \RuntimeException('Mail unavailable'));
        $this->postJson('http://localhost/api/support/reports', [
            'request_id' => (string) Str::uuid(), 'type' => 'support', 'subject' => 'Account help',
            'message' => 'This report must survive an email outage.',
        ])->assertStatus(503)->assertJsonStructure(['error']);
        $this->assertDatabaseCount('support_reports', 1);
        $this->assertNull(DB::table('support_reports')->value('mail_sent_at'));
    }

    public function test_likes_are_unique_and_create_private_activity_and_push(): void
    {
        Http::fake(['https://exp.host/--/api/v2/push/send' => Http::response(['data' => [['status' => 'ok', 'id' => 'ticket']]])]);
        $author = User::factory()->create();
        $actor = User::factory()->create();
        $post = $this->postFor($author);
        Sanctum::actingAs($author);
        $this->postJson('http://localhost/api/notifications/push-token', ['token' => 'ExpoPushToken[owner_123]', 'platform' => 'ios'])->assertOk();
        Sanctum::actingAs($actor);
        $this->postJson("http://localhost/api/bleeter/posts/{$post->id}/like")->assertOk()->assertJsonPath('post.likes_count', 1);
        $this->postJson("http://localhost/api/bleeter/posts/{$post->id}/like")->assertOk()->assertJsonPath('post.likes_count', 1);
        $this->assertDatabaseCount('bleeter_notifications', 1);
        $this->assertDatabaseHas('bleeter_notifications', ['user_id' => $author->id, 'actor_id' => $actor->id, 'type' => 'like']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request[0]['to'] === 'ExpoPushToken[owner_123]'
            && $request[0]['data']['screen'] === 'BleeterNotifications');
        $this->postJson("http://localhost/api/bleeter/posts/{$post->id}/comments", ['body' => 'A comment from another player'])->assertCreated();
        $this->assertDatabaseCount('bleeter_notifications', 2);
        Sanctum::actingAs($author);
        $this->postJson("http://localhost/api/bleeter/posts/{$post->id}/like")->assertOk();
        $this->assertDatabaseCount('bleeter_notifications', 2);
    }

    public function test_notification_pages_and_read_actions_are_scoped_to_the_viewer(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $post = $this->postFor($owner);
        for ($i = 0; $i < 22; $i++) {
            DB::table('bleeter_notifications')->insert([
                'user_id' => $i < 21 ? $owner->id : $other->id, 'actor_id' => $other->id,
                'bleeter_post_id' => $post->id, 'type' => 'comment', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        Sanctum::actingAs($owner);
        $first = $this->getJson('http://localhost/api/bleeter/notifications')->assertOk()
            ->assertJsonCount(10, 'notifications')->assertJsonPath('unread_count', 21)->assertJsonPath('meta.has_more', true);
        $this->getJson('http://localhost/api/bleeter/notifications?page=3')->assertOk()
            ->assertJsonCount(1, 'notifications')->assertJsonPath('meta.has_more', false);
        $ids = array_column($first->json('notifications'), 'id');
        $ids[] = 22; // A notification belonging to someone else must remain unread.
        $this->postJson('http://localhost/api/bleeter/notifications/read', ['ids' => $ids])->assertOk()->assertJsonPath('unread_count', 11);
        $this->assertNull(DB::table('bleeter_notifications')->where('id', 22)->value('read_at'));
    }

    public function test_disabling_push_only_removes_the_current_users_device_registration(): void
    {
        $first = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($first);
        $this->postJson('http://localhost/api/notifications/push-token', ['token' => 'ExpoPushToken[first]', 'platform' => 'android'])->assertOk();
        Sanctum::actingAs($other);
        $this->deleteJson('http://localhost/api/notifications/push-token', ['token' => 'ExpoPushToken[first]'])->assertOk();
        $this->assertDatabaseCount('push_tokens', 1);
        Sanctum::actingAs($first);
        $this->deleteJson('http://localhost/api/notifications/push-token', ['token' => 'ExpoPushToken[first]'])->assertOk();
        $this->assertDatabaseCount('push_tokens', 0);
    }

    public function test_account_deletion_removes_personal_data_tokens_and_chat_uploads(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $post = $this->postFor($owner);
        $crewId = DB::table('crews')->insertGetId(['owner_id' => $owner->id, 'name' => 'Crew', 'tag' => 'TEST', 'invite_code' => 'TESTCODE', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('crew_members')->insert(['user_id' => $other->id, 'crew_id' => $crewId]);
        Storage::disk('public')->put('chat-images/owned.jpg', 'image');
        Storage::disk('public')->put('chat-images/keep.jpg', 'other image');
        DB::table('messages')->insert(['sender_id' => $owner->id, 'recipient_id' => $other->id, 'body' => '',
            'image_url' => rtrim(config('app.url'), '/').'/storage/chat-images/owned.jpg']);
        DB::table('news_comments')->insert(['user_id' => $owner->id, 'article_id' => '1', 'body' => 'Comment']);
        DB::table('push_tokens')->insert(['user_id' => $owner->id, 'token' => 'ExpoPushToken[delete]', 'platform' => 'ios']);
        $token = $owner->createToken('test')->plainTextToken;
        $owner->createToken('another-device');
        $this->withToken($token)->deleteJson('http://localhost/api/auth/account', ['confirmation' => 'DELETE'])->assertOk()->assertJsonPath('deleted', true);
        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        $this->assertDatabaseHas('users', ['id' => $other->id]);
        $this->assertDatabaseMissing('crews', ['id' => $crewId]);
        $this->assertDatabaseMissing('bleeter_posts', ['id' => $post->id]);
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('news_comments', 0);
        $this->assertDatabaseCount('push_tokens', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        Storage::disk('public')->assertMissing('chat-images/owned.jpg');
        Storage::disk('public')->assertExists('chat-images/keep.jpg');
    }

    public function test_account_deletion_requires_confirmation_and_authentication(): void
    {
        $this->deleteJson('http://localhost/api/auth/account', ['confirmation' => 'DELETE'])->assertUnauthorized();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->deleteJson('http://localhost/api/auth/account')->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
