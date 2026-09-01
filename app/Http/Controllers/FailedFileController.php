<?php

namespace App\Http\Controllers;

use App\Models\FailedFile;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FailedFileController extends Controller
{
    /**
     * Display a listing of the failed files.
     */
    public function index()
    {
        $failedFiles = FailedFile::with('metaData')->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $failedFiles
        ]);
    }

    /**
     * Fix a failed file by providing a corrected file_name and description.
     */
    public function fix(Request $request, $id)
    {
        $validated = $request->validate([
            'file_name' => 'required|string',
            'description' => 'required|string',
        ]);

        $failedFile = FailedFile::with('metaData')->findOrFail($id);

        // Mover fisicamente da pasta failed_file para a pasta files
        $oldFilePath = basename($failedFile->url);
        $extension = pathinfo($oldFilePath, PATHINFO_EXTENSION);
        $newFilePath = $validated['file_name'] . '.' . $extension;

        if (Storage::disk('failed_file')->exists($oldFilePath)) {
            $content = Storage::disk('failed_file')->get($oldFilePath);
            Storage::disk('files')->put($newFilePath, $content);
            Storage::disk('failed_file')->delete($oldFilePath);
        }

        $newUrl = Storage::disk('files')->url($newFilePath);

        // 1. Create the new File record
        $file = File::create([
            'url' => $newUrl,
            'file_name' => $validated['file_name'],
        ]);

        // 2. Prepare and create the FileMetaData record
        $data = [
            'file_name' => $validated['file_name'],
            'metadata' => [
                'description' => $validated['description'],
            ],
        ];

        $file->metaData()->create([
            'data' => $data,
            'confidence_level' => 1.0, // Manually fixed, so 100% confidence
        ]);

        // 3. Delete the old FailedFile (cascades to FailedFileMetaData)
        $failedFile->delete();

        $file->load('metaData');

        return response()->json([
            'success' => true,
            'message' => 'Arquivo corrigido e movido com sucesso.',
            'data' => $file
        ]);
    }
}
