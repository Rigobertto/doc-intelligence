<?php

use App\Jobs\ProcessUploadedFile;
use App\Jobs\ProcessStatus;
use App\Services\AIService;
use App\Services\AIMockService;

it('handles processing successfully', function () {
    // Force not to use mock for this test so we can mock AIService
    putenv('USE_AI_MOCK=false');

    $mockService = Mockery::mock(AIService::class);
    $mockService->shouldReceive('document_analiser')
                ->once()
                ->with('test.pdf')
                ->andReturn(Mockery::mock(\Illuminate\Database\Eloquent\Model::class));

    app()->instance(AIService::class, $mockService);

    $job = new ProcessUploadedFile('test.pdf');
    
    expect($job->status)->toBe(ProcessStatus::Pending);

    $job->handle();

    expect($job->status)->toBe(ProcessStatus::Processing);
});

it('fails and throws exception', function () {
    putenv('USE_AI_MOCK=false');

    $mockService = Mockery::mock(AIService::class);
    $mockService->shouldReceive('document_analiser')
                ->once()
                ->with('test.pdf')
                ->andThrow(new \Exception('Service failed'));

    app()->instance(AIService::class, $mockService);

    $job = new ProcessUploadedFile('test.pdf');
    
    expect(fn() => $job->handle())->toThrow(\Exception::class, 'Service failed');
    expect($job->status)->toBe(ProcessStatus::Failed);
});
