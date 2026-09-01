<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use App\Jobs\ProcessUploadedFile;
use App\Models\File;
use App\Models\FileMetaData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can list all files with metadata', function () {
    $file = File::factory()->create();
    FileMetaData::factory()->create(['file_id' => $file->id]);

    $response = $this->getJson('/api/file');

    $response->assertStatus(200)
             ->assertJsonStructure([
                 'success',
                 'data' => [
                     '*' => ['id', 'url', 'file_name', 'meta_data']
                 ]
             ]);
});

it('can search files by metadata', function () {
    $file1 = File::factory()->create();
    FileMetaData::factory()->create([
        'file_id' => $file1->id,
        'data' => ['document_type' => 'Invoice 123']
    ]);

    $file2 = File::factory()->create();
    FileMetaData::factory()->create([
        'file_id' => $file2->id,
        'data' => ['document_type' => 'Receipt 456']
    ]);

    $response = $this->getJson('/api/file-search?q=Invoice');

    $response->assertStatus(200)
             ->assertJsonFragment(['document_type' => 'Invoice 123'])
             ->assertJsonMissing(['document_type' => 'Receipt 456']);
});

it('can upload a file and dispatch job', function () {
    Storage::fake('temp_file');
    Queue::fake();

    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $response = $this->postJson('/api/file', [
        'file' => $file,
    ]);

    $response->assertStatus(200)
             ->assertJsonStructure(['message', 'identifier']);
    
    Queue::assertPushed(ProcessUploadedFile::class);
});

it('fails to upload an invalid file extension', function () {
    $file = UploadedFile::fake()->create('document.txt', 100, 'text/plain');

    $response = $this->postJson('/api/file', [
        'file' => $file,
    ]);

    $response->assertStatus(422)
             ->assertJsonValidationErrors(['file']);
});

it('can delete a file and its record', function () {
    Storage::fake('files');
    $fakeFileName = 'test.pdf';
    Storage::disk('files')->put($fakeFileName, 'dummy content');

    $file = File::factory()->create([
        'url' => Storage::disk('files')->url($fakeFileName),
    ]);

    $response = $this->deleteJson("/api/file/{$file->id}");

    $response->assertStatus(200)
             ->assertJson(['success' => true]);

    $this->assertDatabaseMissing('files', ['id' => $file->id]);
    Storage::disk('files')->assertMissing($fakeFileName);
});
