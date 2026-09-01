<?php

namespace App\Http\Controllers;

use App\Models\FailedFile;
use App\Models\File;
use Illuminate\Http\Request;

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

        // 1. Create the new File record
        $file = File::create([
            'url' => $failedFile->url,
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
