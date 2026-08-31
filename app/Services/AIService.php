<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

class AIService
{
    private string $apiUrl;
    private string $apiKey;
    private string $model;
    private string $systemPrompt;

    public function __construct()
    {
        $this->apiUrl = env('AI_URL', 'https://api.openai.com/v1/chat/completions');
        $this->apiKey = env('AI_API_KEY', '');
        $this->model = env('AI_MODEL', 'gpt-4o-mini');
        
        $this->systemPrompt = "You are an expert document analyzer. Extract the main metadata from the provided document context. You MUST return your response as a valid JSON object. NOT USE MARKDOWN ANCHOS ``` or ``` ";
    }

    /**
     * Recebe o caminho do arquivo, processa via LLM e salva os models.
     * 
     * @param string $filePath
     * @return File
     */
    public function document_analiser(string $filePath): File
    {
        $fileName = basename($filePath);
        $fileUrl = Storage::disk('documents')->url($filePath);
        $absolutePath = Storage::disk('documents')->path($filePath);
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        Log::info("Iniciando análise do documento: {$fileName}");

        $userMessageContent = [];

        if ($extension === 'pdf') {
            Log::info("Processando PDF. Extraindo texto...");
            $parser = new Parser();
            $pdf = $parser->parseFile($absolutePath);
            $text = $pdf->getText();
            
            Log::debug("Texto extraído do PDF: \n" . $text);
            
            $userMessageContent = "Analyze this PDF document named '{$fileName}'. Create a JSON with relevant inferred metadata fields. Document text content:\n\n" . substr($text, 0, 15000); // Limiting text to avoid token limits
        } elseif (in_array($extension, ['jpg', 'jpeg', 'png'])) {
            Log::info("Processando Imagem. Convertendo para Base64...");
            $mime = mime_content_type($absolutePath) ?: 'image/jpeg';
            $base64 = base64_encode(file_get_contents($absolutePath));
            
            $userMessageContent = [
                [
                    'type' => 'text',
                    'text' => "Analyze this image document named '{$fileName}'. Create a JSON with relevant inferred metadata fields.",
                ],
                [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => "data:{$mime};base64,{$base64}",
                    ]
                ]
            ];
        } else {
            Log::error("Tipo de arquivo não suportado: {$extension}");
            throw new \InvalidArgumentException("Tipo de arquivo não suportado para análise: {$extension}");
        }
        
        Log::info("Enviando payload para a LLM...", ['model' => $this->model]);

        $response = Http::withToken($this->apiKey)
            ->timeout(120)
            ->post($this->apiUrl, [
                'model' => $this->model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->systemPrompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => $userMessageContent,
                    ],
                ],
            ]);

        if ($response->failed()) {
            Log::error("Erro na chamada da LLM", [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
        } else {
            Log::info("Resposta da LLM recebida com sucesso.");
            Log::debug("Resposta COMPLETA da LLM (JSON integral): \n" . $response->body());
        }

        $responseData = $response->json('choices.0.message.content') ?? '';
        
        Log::debug("Conteúdo extraído (texto da mensagem): \n" . $responseData);
        
        // Limpeza de caracteres residuais (Markdown ou aspas extras retornadas por alguns modelos)
        $cleanJson = trim($responseData);
        
        if (str_starts_with($cleanJson, '```')) {
            $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', $cleanJson);
            $cleanJson = preg_replace('/\s*```$/', '', $cleanJson);
        }
        
        // Extrai garantidamente apenas o conteúdo entre as chaves externas principal
        $start = strpos($cleanJson, '{');
        $end = strrpos($cleanJson, '}');
        
        if ($start !== false && $end !== false) {
            $cleanJson = substr($cleanJson, $start, $end - $start + 1);
        }
        
        // Decodificando o JSON retornado pela LLM para array (se falhar, usa array vazio)
        $metadataArray = json_decode($cleanJson, true) ?? [];
        
        if (empty($metadataArray)) {
            Log::warning("A LLM retornou um JSON vazio ou inválido.");
        }

        // 1. Cria o model File
        $fileModel = File::create([
            'url' => $fileUrl,
            'file_name' => $fileName,
        ]);

        // 2. Cria o FileMetaData (relacionado ao File) passando o JSON decodificado
        $fileModel->metaData()->create([
            'data' => $metadataArray,
        ]);

        // Carrega o relacionamento para retornar tudo estruturado
        $fileModel->load('metaData');

        Log::info("Documento salvo no banco de dados com sucesso. ID: {$fileModel->id}");

        return $fileModel;
    }
}
