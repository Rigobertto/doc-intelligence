<?php

namespace App\Http\Controllers;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ProcessUploadedFile;

class FileController extends Controller
{
    public function index()
    {
        $files = File::with('metaData')->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $files
        ]);
    }

    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string',
        ]);

        $term = $request->input('q');

        $files = File::whereHas('metaData', function ($query) use ($term) {
            $query->whereRaw('LOWER(CAST(data AS TEXT)) LIKE ?', ['%' . mb_strtolower($term) . '%']);
        })->with('metaData')->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $files
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,png,jpeg,jpg'],
        ]);

        $file = $request->file('file');
        
        if (!$file->getRealPath()) {
            return response()->json([
                'message' => 'Internal Server Error: Temporary file not generated.'
            ], 500);
        }

        $path = $file->store('', 'documents');

        ProcessUploadedFile::dispatch($path);

        return response()->json([
            'message' => 'File received successfully.',
            'identifier' => $path,
        ]);
    }
}
