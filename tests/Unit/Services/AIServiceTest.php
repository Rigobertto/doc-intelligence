<?php

use App\Services\AIService;
use App\Models\File;
use App\Models\FailedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Smalot\PdfParser\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('temp_file');
    Storage::fake('files');
    Storage::fake('failed_file');
});

it('processes a PDF successfully with high confidence', function () {
    Storage::disk('temp_file')->put('test.pdf', 'dummy content');

    $mockPdf = Mockery::mock(Document::class);
    $mockPdf->shouldReceive('getText')->andReturn('Extracted text from PDF');

    $mockParser = Mockery::mock(Parser::class);
    $mockParser->shouldReceive('parseFile')->once()->andReturn($mockPdf);

    app()->instance(Parser::class, $mockParser);

    Http::fake([
        '*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'file_name' => 'processed',
                            'confidence_level' => 0.95,
                            'total' => 150.00
                        ])
                    ]
                ]
            ]
        ], 200)
    ]);

    $service = app(AIService::class);
    $result = $service->document_analiser('test.pdf');

    expect($result)->toBeInstanceOf(File::class);
    expect($result->file_name)->toBe('processed');
    expect($result->metaData->confidence_level)->toBe(0.95);
    
    Storage::disk('files')->assertExists('processed.pdf');
    Storage::disk('temp_file')->assertMissing('test.pdf');
});

it('moves file to failed_file if confidence is low', function () {
    Storage::disk('temp_file')->put('test.png', 'dummy image content');

    Http::fake([
        '*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'file_name' => 'bad',
                            'confidence_level' => 0.5,
                        ])
                    ]
                ]
            ]
        ], 200)
    ]);

    $service = app(AIService::class);
    $result = $service->document_analiser('test.png');

    expect($result)->toBeInstanceOf(FailedFile::class);
    expect($result->metaData->confidence_level)->toBe(0.5);

    Storage::disk('failed_file')->assertExists('test.png');
    Storage::disk('temp_file')->assertMissing('test.png');
});

it('creates failed_file if AI returns invalid JSON', function () {
    Storage::disk('temp_file')->put('test.jpg', 'dummy image content');

    Http::fake([
        '*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => 'This is not a JSON string'
                    ]
                ]
            ]
        ], 200)
    ]);

    $service = app(AIService::class);
    $result = $service->document_analiser('test.jpg');
    
    expect($result)->toBeInstanceOf(FailedFile::class);
    Storage::disk('failed_file')->assertExists('test.jpg');
});

it('throws InvalidArgumentException for unsupported files', function () {
    Storage::disk('temp_file')->put('test.txt', 'dummy content');

    $service = app(AIService::class);
    
    expect(fn() => $service->document_analiser('test.txt'))->toThrow(\InvalidArgumentException::class);
});
