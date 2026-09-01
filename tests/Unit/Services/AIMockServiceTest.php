<?php

use App\Services\AIMockService;
use App\Models\File;
use App\Models\FailedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('temp_file');
    Storage::fake('files');
    Storage::fake('failed_file');
});

it('mocks success response and creates File', function () {
    Storage::disk('temp_file')->put('test.pdf', 'dummy content');
    
    $service = new AIMockService();
    
    $reflection = new \ReflectionClass($service);
    $prop = $reflection->getProperty('mockStyle');
    $prop->setAccessible(true);
    $prop->setValue($service, 'success');

    $propConf = $reflection->getProperty('minConfidenceLevel');
    $propConf->setAccessible(true);
    $propConf->setValue($service, 0.5);

    $result = $service->document_analiser('test.pdf');
    
    expect($result)->toBeInstanceOf(File::class);
    Storage::disk('files')->assertExists($result->file_name . '.pdf');
    Storage::disk('temp_file')->assertMissing('test.pdf');
});

it('mocks low_confidence response and creates FailedFile', function () {
    Storage::disk('temp_file')->put('test.pdf', 'dummy content');
    
    $service = new AIMockService();
    
    $reflection = new \ReflectionClass($service);
    $prop = $reflection->getProperty('mockStyle');
    $prop->setAccessible(true);
    $prop->setValue($service, 'low_confidence');

    $result = $service->document_analiser('test.pdf');

    expect($result)->toBeInstanceOf(FailedFile::class);
    Storage::disk('failed_file')->assertExists('test.pdf');
});

it('mocks invalid_json response and creates FailedFile', function () {
    Storage::disk('temp_file')->put('test.pdf', 'dummy content');
    
    $service = new AIMockService();
    
    $reflection = new \ReflectionClass($service);
    $prop = $reflection->getProperty('mockStyle');
    $prop->setAccessible(true);
    $prop->setValue($service, 'invalid_json');

    $result = $service->document_analiser('test.pdf');
    
    expect($result)->toBeInstanceOf(FailedFile::class);
    Storage::disk('failed_file')->assertExists('test.pdf');
});
