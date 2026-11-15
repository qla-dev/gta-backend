<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BusinessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null, 'cache.default' => 'array']);
        DB::purge('sqlite');
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    public function test_three_demo_businesses_are_public_and_guests_cannot_edit(): void
    {
        $businesses = $this->getJson('http://localhost/api/businesses')->assertOk()->assertJsonCount(3, 'businesses')
            ->assertJsonPath('summary.dummy_count', 3)->assertJsonPath('summary.owned', 0)->assertJsonPath('summary.daily_income', 0)->json('businesses');
        $this->assertEqualsCanonicalizing(['Casino', 'Strip club', 'Nightclub'], array_column($businesses, 'type'));
        $this->postJson('http://localhost/api/businesses/1/state', ['owned' => true])->assertUnauthorized();
        $this->assertDatabaseCount('user_businesses', 0);
    }

    public function test_ownership_is_private_persisted_and_does_not_create_wallet_transactions(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $token = $owner->createToken('business-test')->plainTextToken;
        $walletCount = DB::table('wallet_transactions')->count();
        $this->withToken($token)->postJson('http://localhost/api/businesses/1/state', ['owned' => true])->assertOk()
            ->assertJsonPath('business.owned', true)->assertJsonPath('business.status', 'operating')->assertJsonPath('business.daily_income', 18000);
        $this->postJson('http://localhost/api/businesses/1/state', ['level' => 2])->assertOk()->assertJsonPath('business.daily_income', 22500);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('http://localhost/api/businesses')->assertOk()->assertJsonPath('summary.owned', 1)->assertJsonPath('summary.daily_income', 22500);
        $this->app['auth']->forgetGuards();
        $this->withToken($other->createToken('other')->plainTextToken)->getJson('http://localhost/api/businesses')->assertOk()->assertJsonPath('summary.owned', 0);
        $this->assertSame($walletCount, DB::table('wallet_transactions')->count());
        $this->assertDatabaseCount('user_businesses', 1);
    }

    public function test_pause_resume_and_untracking_produce_consistent_estimates(): void
    {
        $this->withToken(User::factory()->create()->createToken('test')->plainTextToken);
        $url = 'http://localhost/api/businesses/3/state';
        $this->postJson($url, ['owned' => true, 'level' => 3])->assertOk()->assertJsonPath('business.daily_income', 18000);
        $this->postJson($url, ['status' => 'paused'])->assertOk()->assertJsonPath('business.daily_income', 0);
        $this->postJson($url, ['status' => 'operating'])->assertOk()->assertJsonPath('business.daily_income', 18000);
        $this->postJson($url, ['owned' => false])->assertOk()->assertJsonPath('business.level', 1)->assertJsonPath('business.daily_income', 0)->assertJsonPath('business.status', 'paused');
        $this->postJson($url, ['level' => 2])->assertUnprocessable();
    }

    public function test_invalid_levels_states_and_inactive_businesses_are_rejected(): void
    {
        $this->withToken(User::factory()->create()->createToken('test')->plainTextToken);
        $url = 'http://localhost/api/businesses/1/state';
        foreach ([[], ['owned' => 'yes'], ['level' => 0], ['level' => 6], ['status' => 'invalid']] as $data) {
            $this->postJson($url, $data)->assertUnprocessable();
        }
        DB::table('businesses')->where('id', 1)->update(['is_active' => false]);
        $this->postJson($url, ['owned' => true])->assertNotFound();
        $this->postJson('http://localhost/api/businesses/999/state', ['owned' => true])->assertNotFound();
    }

    public function test_deleting_an_account_removes_only_its_business_tracking(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        foreach ([$owner, $other] as $user) {
            DB::table('user_businesses')->insert(['user_id' => $user->id, 'business_id' => 1, 'owned' => true, 'level' => 1, 'status' => 'operating', 'created_at' => now(), 'updated_at' => now()]);
        }
        $owner->delete();
        $this->assertDatabaseCount('user_businesses', 1);
        $this->assertDatabaseHas('user_businesses', ['user_id' => $other->id]);
        $this->assertDatabaseCount('businesses', 3);
    }
}
