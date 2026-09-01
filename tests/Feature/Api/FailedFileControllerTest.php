<?php

use Illuminate\Support\Facades\Storage;
use App\Models\FailedFile;
use App\Models\FailedFileMetaData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can list all failed files', function () {
    $failedFile = FailedFile::factory()->create();
    FailedFileMetaData::factory()->create(['failed_file_id' => $failedFile->id]);

    $response = $this->getJson('/api/failed-file');

    $response->assertStatus(200)
             ->assertJsonStructure([
                 'success',
                 'data' => [
                     '*' => ['id', 'url', 'file_name', 'meta_data']
                 ]
             ]);
});

it('can fix a failed file', function () {
    Storage::fake('failed_file');
    Storage::fake('files');

    $fakeFileName = 'wrong.png';
    Storage::disk('failed_file')->put($fakeFileName, 'dummy image content');

    $failedFile = FailedFile::factory()->create([
        'url' => Storage::disk('failed_file')->url($fakeFileName),
    ]);
    FailedFileMetaData::factory()->create(['failed_file_id' => $failedFile->id]);

    $response = $this->postJson("/api/fix-file/{$failedFile->id}", [
        'file_name' => 'corrected_name',
        'description' => 'Manually corrected invoice',
    ]);

    $response->assertStatus(200)
             ->assertJson(['success' => true]);

    $this->assertDatabaseMissing('failed_files', ['id' => $failedFile->id]);
    $this->assertDatabaseHas('files', ['file_name' => 'corrected_name']);
    
    Storage::disk('failed_file')->assertMissing($fakeFileName);
    Storage::disk('files')->assertExists('corrected_name.png');
});

it('fails to fix with invalid data', function () {
    $failedFile = FailedFile::factory()->create();

    $response = $this->postJson("/api/fix-file/{$failedFile->id}", [
        'file_name' => '',
    ]);

    $response->assertStatus(422)
             ->assertJsonValidationErrors(['file_name', 'description']);
});

it('can delete a failed file and its record', function () {
    Storage::fake('failed_file');
    $fakeFileName = 'test_failed.png';
    Storage::disk('failed_file')->put($fakeFileName, 'dummy content');

    $failedFile = FailedFile::factory()->create([
        'url' => Storage::disk('failed_file')->url($fakeFileName),
    ]);

    $response = $this->deleteJson("/api/failed-file/{$failedFile->id}");

    $response->assertStatus(200)
             ->assertJson(['success' => true]);

    $this->assertDatabaseMissing('failed_files', ['id' => $failedFile->id]);
    Storage::disk('failed_file')->assertMissing($fakeFileName);
});
