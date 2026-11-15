<?php

namespace Tests\Feature;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MissionProgressTest extends TestCase
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

    public function test_catalog_has_twenty_explicit_demo_missions_and_preserves_the_checklist(): void
    {
        $missions = $this->getJson('http://localhost/api/missions')->assertOk()->assertJsonCount(20, 'missions')
            ->assertJsonPath('summary.completed', 0)->assertJsonPath('summary.dummy_count', 20)->json('missions');
        $this->assertCount(20, array_unique(array_column($missions, 'slug')));
        foreach ($missions as $mission) {
            $this->assertTrue($mission['is_dummy']);
            $this->assertGreaterThanOrEqual(0, $mission['map_x']);
            $this->assertLessThanOrEqual(100, $mission['map_y']);
        }
        $this->assertDatabaseCount('checklist_items', 10);
    }

    public function test_guest_cannot_save_progress(): void
    {
        $this->postJson('http://localhost/api/missions/1/progress', ['status' => 'completed'])->assertUnauthorized();
        $this->assertDatabaseCount('mission_progress', 0);
    }

    public function test_bearer_progress_is_persisted_private_and_resettable(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $token = $user->createToken('progress-test')->plainTextToken;
        $otherToken = $other->createToken('other-test')->plainTextToken;
        $this->withToken($token)->postJson('http://localhost/api/missions/1/progress', ['status' => 'in_progress'])
            ->assertOk()->assertJsonPath('mission.status', 'in_progress')->assertJsonPath('mission.progress_current', 0);
        $this->withToken($token)->postJson('http://localhost/api/missions/1/progress', ['status' => 'completed'])
            ->assertOk()->assertJsonPath('mission.done', true)->assertJsonPath('mission.progress_current', 1);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('http://localhost/api/missions')->assertOk()->assertJsonPath('summary.completed', 1)->assertJsonPath('summary.percent', 5);
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)->getJson('http://localhost/api/missions')->assertOk()->assertJsonPath('summary.completed', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('http://localhost/api/missions/1/progress', ['status' => 'not_started'])
            ->assertOk()->assertJsonPath('mission.progress_current', 0)->assertJsonPath('mission.done', false);
        $this->assertDatabaseCount('mission_progress', 1);
    }

    public function test_objective_counts_are_bounded_and_update_completion(): void
    {
        $token = User::factory()->create()->createToken('test')->plainTextToken;
        $mission = Mission::where('slug', 'container-yard-heist')->firstOrFail();
        $url = "http://localhost/api/missions/{$mission->id}/progress";
        $this->withToken($token)->postJson($url, ['progress_current' => 2])->assertOk()
            ->assertJsonPath('mission.status', 'in_progress')->assertJsonPath('mission.progress_current', 2);
        $this->postJson($url, ['progress_current' => 3])->assertOk()->assertJsonPath('mission.done', true);
        foreach ([['progress_current' => -1], ['progress_current' => 4], ['status' => 'invalid'], []] as $data) {
            $this->postJson($url, $data)->assertUnprocessable();
        }
        $this->assertDatabaseHas('mission_progress', ['mission_id' => $mission->id, 'progress_current' => 3, 'status' => 'completed']);
    }

    public function test_inactive_and_unknown_missions_cannot_be_updated(): void
    {
        $this->withToken(User::factory()->create()->createToken('test')->plainTextToken);
        DB::table('missions')->where('id', 1)->update(['is_active' => false]);
        $this->getJson('http://localhost/api/missions')->assertOk()->assertJsonCount(19, 'missions');
        $this->postJson('http://localhost/api/missions/1/progress', ['status' => 'completed'])->assertNotFound();
        $this->postJson('http://localhost/api/missions/9999/progress', ['status' => 'completed'])->assertNotFound();
    }
}
