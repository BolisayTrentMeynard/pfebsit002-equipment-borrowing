<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Week 16, chunk 1: what the operations features do when the database cannot be reached (AC-43, AC-45).
 * No RefreshDatabase on purpose: the connection is pointed at nothing, so these tests never touch the
 * real test database. The real MySQL-stopped run is the chunk 4 failure test.
 */
class OperationsDownTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $default = config('database.default');
        config([
            "database.connections.$default.host" => '127.0.0.1',
            "database.connections.$default.port" => 1,
            "database.connections.$default.database" => '/no/such/place/missing.sqlite',
        ]);
        DB::purge($default);
    }

    public function test_ac43_ready_answers_503_fail_with_no_detail_when_the_database_is_down(): void
    {
        $res = $this->getJson('/api/health/ready');

        $res->assertStatus(503)->assertExactJson(['status' => 'fail']);
        $this->assertStringNotContainsString('SQLSTATE', $res->getContent());
        $this->assertStringNotContainsString('127.0.0.1', $res->getContent());
        $this->assertSame('no-store, private', $res->headers->get('Cache-Control'));
    }

    public function test_ac43_liveness_up_still_answers_200_when_the_database_is_down(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_ac45_ops_status_reports_the_database_down_and_exits_1(): void
    {
        $this->artisan('ops:status')
            ->expectsOutputToContain('NOT REACHABLE')
            ->expectsOutputToContain('unknown (database down)')
            ->assertExitCode(1);
    }
}
