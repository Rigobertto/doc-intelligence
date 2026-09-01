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
        ], [
            'q.required' => 'O termo de pesquisa (q) é obrigatório.',
            'q.string' => 'O termo de pesquisa deve ser um texto válido.',
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
        ], [
            'file.required' => 'O envio do arquivo é obrigatório.',
            'file.file' => 'O arquivo enviado não é válido.',
            'file.mimes' => 'O arquivo deve ser do tipo: pdf, png, jpeg, jpg.',
        ]);

        $file = $request->file('file');
        
        if (!$file->getRealPath()) {
            return response()->json([
                'message' => 'Erro interno do servidor: Arquivo temporário não gerado.'
            ], 500);
        }

        $path = $file->store('', 'temp_file');

        ProcessUploadedFile::dispatch($path);

        return response()->json([
            'message' => 'Arquivo recebido com sucesso.',
            'identifier' => $path,
        ]);
    }

    public function destroy($id)
    {
        $file = File::findOrFail($id);

        $filePath = basename($file->url);
        if (Storage::disk('files')->exists($filePath)) {
            Storage::disk('files')->delete($filePath);
        }

        $file->delete();

        return response()->json([
            'success' => true,
            'message' => 'Arquivo e registro deletados com sucesso.'
        ]);
    }
}
