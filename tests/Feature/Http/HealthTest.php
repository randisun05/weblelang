<?php

namespace Tests\Feature\Http;

use App\Jobs\QueueHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_healthy_when_scheduler_and_queue_heartbeats_are_fresh(): void
    {
        Cache::put('heartbeat:scheduler', now()->timestamp);
        (new QueueHeartbeat)->handle();

        $this->getJson(route('health'))->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.queue', 'ok')
            ->assertJsonPath('checks.scheduler', 'ok');
    }

    public function test_degraded_when_workers_are_down(): void
    {
        Cache::put('heartbeat:scheduler', now()->subMinutes(10)->timestamp);

        $this->getJson(route('health'))->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.scheduler', 'fail')
            ->assertJsonPath('checks.queue', 'fail')
            ->assertJsonPath('checks.database', 'ok');
    }

    public function test_scheduler_registers_heartbeats_and_backups(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('heartbeat')
            ->expectsOutputToContain('backup:run')
            ->assertSuccessful();
    }
}
