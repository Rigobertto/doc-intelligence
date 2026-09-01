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

    public function fix(Request $request, $id)
    {
        $validated = $request->validate([
            'file_name' => 'required|string',
            'description' => 'required|string',
        ], [
            'file_name.required' => 'O nome do arquivo é obrigatório.',
            'file_name.string' => 'O nome do arquivo deve ser um texto válido.',
            'description.required' => 'A descrição é obrigatória.',
            'description.string' => 'A descrição deve ser um texto válido.',
        ]);

        $failedFile = FailedFile::with('metaData')->findOrFail($id);

        $oldFilePath = basename($failedFile->url);
        $extension = pathinfo($oldFilePath, PATHINFO_EXTENSION);
        $newFilePath = $validated['file_name'] . '.' . $extension;

        if (Storage::disk('failed_file')->exists($oldFilePath)) {
            $content = Storage::disk('failed_file')->get($oldFilePath);
            Storage::disk('files')->put($newFilePath, $content);
            Storage::disk('failed_file')->delete($oldFilePath);
        }

        $newUrl = Storage::disk('files')->url($newFilePath);

        $file = File::create([
            'url' => $newUrl,
            'file_name' => $validated['file_name'],
        ]);

        $data = [
            'file_name' => $validated['file_name'],
            'metadata' => [
                'description' => $validated['description'],
            ],
        ];

        $file->metaData()->create([
            'data' => $data,
            'confidence_level' => 1.0, 
        ]);

        $failedFile->delete();

        $file->load('metaData');

        return response()->json([
            'success' => true,
            'message' => 'Arquivo corrigido e movido com sucesso.',
            'data' => $file
        ]);
    }

    public function destroy($id)
    {
        $failedFile = FailedFile::findOrFail($id);

        $filePath = basename($failedFile->url);
        if (Storage::disk('failed_file')->exists($filePath)) {
            Storage::disk('failed_file')->delete($filePath);
        }

        $failedFile->delete();

        return response()->json([
            'success' => true,
            'message' => 'Arquivo com falha e registro deletados com sucesso.'
        ]);
    }
}
