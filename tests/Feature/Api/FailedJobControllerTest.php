<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create the failed_jobs table schema since it's a native table, but in tests,
    // we need it available (RefreshDatabase runs migrations, so if it's there, great)
    // We just insert a fake record.
    DB::table('failed_jobs')->insert([
        'uuid' => '1234-abcd',
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['data' => 'test']),
        'exception' => 'Exception message',
        'failed_at' => now(),
    ]);
});

it('can list failed jobs', function () {
    $response = $this->getJson('/api/failed-jobs');

    $response->assertStatus(200)
             ->assertJsonStructure([
                 'success',
                 'data' => [
                     '*' => ['id', 'uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at']
                 ]
             ])
             ->assertJsonPath('data.0.uuid', '1234-abcd');
});

it('can trigger retry for a failed job', function () {
    Artisan::shouldReceive('call')
        ->once()
        ->with('queue:retry', ['id' => ['all']])
        ->andReturn(0);

    $response = $this->postJson('/api/failed-jobs/retry', [
        'id' => 'all',
    ]);

    $response->assertStatus(200)
             ->assertJson(['success' => true]);
});
