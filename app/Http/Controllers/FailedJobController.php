<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class FailedJobController extends Controller
{
    /**
     * Lista todos os jobs que falharam na tabela failed_jobs.
     */
    public function index()
    {
        $failedJobs = DB::table('failed_jobs')->latest('failed_at')->get();

        // Decodificando o payload para facilitar a leitura
        $failedJobs->transform(function ($job) {
            $job->payload = json_decode($job->payload);
            return $job;
        });

        return response()->json([
            'success' => true,
            'data' => $failedJobs
        ]);
    }

    /**
     * Executa o retry de um job específico (ID ou UUID) ou de todos (usando 'all').
     */
    public function retry(Request $request)
    {
        $request->validate([
            'id' => 'required' // pode ser o ID, UUID ou a string 'all'
        ]);

        $id = $request->input('id');

        try {
            if ($id === 'all') {
                Artisan::call('queue:retry', ['id' => ['all']]);
            } else {
                Artisan::call('queue:retry', ['id' => [$id]]);
            }
            
            return response()->json([
                'success' => true,
                'message' => $id === 'all' 
                    ? 'Todos os jobs falhos foram enviados para a fila de reprocessamento.' 
                    : "O job [{$id}] foi enviado para a fila de reprocessamento."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao tentar reprocessar job: ' . $e->getMessage()
            ], 500);
        }
    }
}
