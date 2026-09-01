<?php

namespace App\Services;

use App\Models\FailedFile;
use App\Models\File;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AIMockService
{
    private float $minConfidenceLevel;
    private string $mockStyle;

    public function __construct()
    {
        $this->minConfidenceLevel = env('AI_MIN_CONFIDENCE_LEVEL', 0.7);
        $this->mockStyle = env('MOCK_RESPONSE_STYLE', 'success');
    }

    /**
     * Recebe o caminho do arquivo, mocka a resposta da LLM e salva os models.
     * 
     * @param string $filePath
     * @return Model
     */
    public function document_analiser(string $filePath): Model
    {
        $fileName = basename($filePath);
        $fileUrl = Storage::disk('temp_file')->url($filePath);
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        Log::info("[MOCK] Iniciando análise do documento: {$fileName}");
        Log::info("[MOCK] Estilo de resposta configurado: {$this->mockStyle}");

        // Simula o tempo de resposta da API
        sleep(2);

        // Gera a resposta mockada
        $responseData = $this->getMockedResponse();

        Log::debug("[MOCK] Conteúdo gerado (texto da mensagem): \n" . $responseData);

        $cleanJson = trim($responseData);
        
        // Decodificando o JSON mockado para array
        $metadataArray = json_decode($cleanJson, true) ?? [];
        
        if (empty($metadataArray)) {
            Log::warning("[MOCK] O JSON mockado retornou vazio ou é inválido.");
        }
        $confidenceLevel = $metadataArray['confidence_level'] ?? null;
        $fallbackName = pathinfo($fileName, PATHINFO_FILENAME);
        $newFileName = $metadataArray['file_name'] ?? $fallbackName;

        if (empty($metadataArray) || $confidenceLevel === null || (float)$confidenceLevel < $this->minConfidenceLevel) {
            $level = $confidenceLevel ?? 'N/A';
            Log::warning("[MOCK] Documento inválido ou Nível de confiança ({$level}) abaixo do mínimo ({$this->minConfidenceLevel}). Registrando como FailedFile.");
            
            if (isset($metadataArray['confidence_level'])) {
                unset($metadataArray['confidence_level']);
            }

            // Move o arquivo de temp_file para failed_file
            $originalFileContent = Storage::disk('temp_file')->get($filePath);
            Storage::disk('failed_file')->put($fileName, $originalFileContent);
            Storage::disk('temp_file')->delete($filePath);

            $failedFileUrl = Storage::disk('failed_file')->url($fileName);

            $failedFileModel = FailedFile::create([
                'url' => $failedFileUrl,
                'file_name' => $fileName,
            ]);

            $failedFileModel->metaData()->create([
                'data' => $metadataArray,
                'confidence_level' => $confidenceLevel,
            ]);

            $failedFileModel->load('metaData');
            
            return $failedFileModel;
        }

        // Fluxo de sucesso
        $originalFileContent = Storage::disk('temp_file')->get($filePath);
        $newFilePath = $newFileName . '.' . $extension;
        
        Storage::disk('files')->put($newFilePath, $originalFileContent);
        Storage::disk('temp_file')->delete($filePath);
        
        $newFileUrl = Storage::disk('files')->url($newFilePath);

        $fileModel = File::create([
            'url' => $newFileUrl,
            'file_name' => $newFileName,
        ]);

        if (isset($metadataArray['confidence_level'])) {
            unset($metadataArray['confidence_level']);
        }

        $fileModel->metaData()->create([
            'data' => $metadataArray,
            'confidence_level' => $confidenceLevel,
        ]);

        $fileModel->load('metaData');

        Log::info("[MOCK] Documento salvo no banco de dados com sucesso. ID: {$fileModel->id}");

        return $fileModel;
    }

    private function getMockedResponse(): string
    {
        $date = date('Y-m-d');
        $random = rand(1000, 9999);
        
        return match ($this->mockStyle) {
            'low_confidence' => json_encode([
                'file_name' => "documento_desconhecido_{$random}_{$date}",
                'metadata' => [
                    'description' => 'Documento com baixa legibilidade ou informações faltando.',
                    'razao_social' => null,
                ],
                'confidence_level' => 0.4
            ]),
            'invalid_json' => '{"file_name": "teste", "metadata": { "description": "faltando aspas }',
            'success' => json_encode([
                'file_name' => "carteira_identidade_{$random}_{$date}",
                'metadata' => [
                    'description' => 'Carteira de identidade extraída com sucesso (MOCK).',
                    'numero' => "{$random}",
                    'nome' => 'João Silva',
                    'nome_mae' => 'Maria Silva',
                    'data_nascimento' => '2000-01-01',
                    'data_emissao' => '2022-01-01',
                    'data_validade' => '2022-01-01',
                    'orgao_emissor' => 'SSP',
                    'tipo_documento' => 'RG',
                ],
                'confidence_level' => 0.95
            ]),
            default => json_encode([
                'file_name' => "documento_padrao_{$random}_{$date}",
                'metadata' => [
                    'description' => 'Resposta padrão do mock.',
                    'numero' => "{$random}",
                ],
                'confidence_level' => 0.8
            ])
        };
    }
}
