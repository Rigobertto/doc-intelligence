<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ProcessUploadedFile;

class FileController extends Controller
{
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
