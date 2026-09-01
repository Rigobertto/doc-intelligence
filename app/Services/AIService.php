<?php

namespace App\Services;

use App\Models\FailedFile;
use App\Models\File;
use Illuminate\Database\Eloquent\Model;
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
    private float $minConfidenceLevel;

    public function __construct()
    {
        $this->apiUrl = env('AI_URL');
        $this->apiKey = env('AI_API_KEY');
        $this->model = env('AI_MODEL');
        $this->minConfidenceLevel = env('AI_MIN_CONFIDENCE_LEVEL', 0.7);
        
        $this->systemPrompt = 
        '
        You are a specialized document extraction engine. Your sole objective is to analyze the provided document and extract structured data strictly adhering to the JSON schema defined below.

### OPERATIONAL RULES
1. OUTPUT FORMAT: Respond ONLY with a single, raw, valid JSON object. Do not include markdown code fences (e.g., ```json or ```), introduction, commentary, or trailing text.
2. LANGUAGE: The field names (keys) must remain in English as defined in the schema. All extracted values, descriptions, and dynamic metadata values must be in Brazilian Portuguese (pt-BR).
3. MISSING, EMPTY, OR UNREADABLE DATA: 
   - If a specific field, form box, or value is missing, unfilled, blank, illegible, or obscured, assign its value strictly as `null`. Never fabricate, guess, or hallucinate data.
   - If the document is entirely blank, contains only empty form boxes/templates, or has no extractable data:
     * Set `metadata` to an empty object `{}`.
     * Describe the state in `description` (e.g., "Documento em branco, modelo não preenchido ou sem dados extraíveis.").
     * Use generic placeholders for `file_name` (e.g., `documento_vazio_nao_identificado_[date]`).
4. FRAUD, ANOMALY & MANIPULATION DETECTION:
   - Scrutinize the document for visual or logical manipulation: mismatched fonts, misaligned text, irregular artifacting around numbers/names, patched backgrounds, or abnormal spacing.
   - Detect evidently fake numbers, placeholders, or sequence patterns (e.g., sequential/repeated IDs like `123456789`, `000.000.000-00`, `11.111.111/1111-11`, impossible issue dates, or invalid mathematical totals/check-digits).
5. CONFIDENCE SCORING: Strictly evaluate document integrity, OCR clarity, field completeness, and authenticity markers. Apply severe penalties to `confidence_level` under the following conditions:
   - Empty documents, blank pages, or documents containing predominantly blank/unfilled fields or empty form boxes.
   - Documents with visibly manipulated regions, digital tampering artifacts, or mismatched typography.
   - Documents presenting clearly fake, sequential, placeholder, or structurally invalid identifiers (CPFs, CNPJs, invoice IDs, barcodes).

### JSON SCHEMA
{
  "file_name": "[type]_[identifier]_[date]",
  "metadata": {
    "description": "string (A concise description in Portuguese summarizing the document type, main subject, parties involved, and explicitly noting any observed anomalies, blank fields, or signs of tampering)",
    "dynamic_field_1": "value",
    "dynamic_field_2": 0.00
  },
  "confidence_level": 0.00
}

### FIELD SPECIFICATIONS
- "file_name": Must follow the naming standard `[type]_[identifier]_[date]`.
  * `[type]`: Standardized document category in lowercase snake_case (e.g., `nota_fiscal`, `contrato_prestacao_servicos`, `comprovante_pagamento`, `relatorio_medico`, or `documento_vazio` if no type can be identified).
  * `[identifier]`: Primary unique identifier such as a sanitized document number, invoice ID, CPF/CNPJ, or primary party name (alphanumerics only, separated by underscores). If unidentifiable or fake, use `nao_identificado` or `suspeita_invalido`.
  * `[date]`: Must be the current execution date/time representing "today", formatted strictly as `YYYY-MM-DD` (derived from the `.date("Y-m-d")` format, using hyphens or underscores to maintain valid filename syntax).
- "description": High-level synthesis in Portuguese detailing the document purpose and key entities, or stating clearly if the document contains blank boxes, signs of digital manipulation, or invalid placeholder numbers.
- "metadata": Key-value pairs extracted dynamically from the document.
  * Extract all relevant entities, including but not limited to: full names, corporate names, tax IDs (CPF/CNPJ), document-internal dates (in `YYYY-MM-DD` format), currency values (as numerical floats), line items, and addresses.
  * Set unreadable, missing, or blank box values to `null`.
  * Return `{}` if no valid entities exist.
- "confidence_level": A float between `0.0` and `1.0` representing total extraction certainty and document validity:
  * `1.0`: Completely legible, verified checksums/totals, fully filled fields, zero manipulation signs or ambiguities.
  * `0.7 - 0.9`: High legibility and authenticity, with minor OCR noise or non-critical omitted secondary fields.
  * `0.3 - 0.6`: Degraded OCR, partial form boxes left blank, or non-critical numerical discrepancies.
  * `0.0 - 0.2`: Empty/blank documents, unfilled form templates, evident signs of digital tampering/alteration, or clearly fake/sequential identifiers.
        
        You MUST use the current date in the file_name, which is: "' . date('Y-m-d') . '" at the end of the file name.
        ';
    }

    
    public function document_analiser(string $filePath): Model
    {
        $fileName = basename($filePath);
        $fileUrl = Storage::disk('temp_file')->url($filePath);
        $absolutePath = Storage::disk('temp_file')->path($filePath);
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        $userMessageContent = [];

        if ($extension === 'pdf') {
            $parser = app(Parser::class);
            $pdf = $parser->parseFile($absolutePath);
            $text = $pdf->getText();
            
            $userMessageContent = "Analyze this PDF document named '{$fileName}'. Create a JSON with relevant inferred metadata fields. Document text content:\n\n" . substr($text, 0, 15000); // Limiting text to avoid token limits
        } elseif (in_array($extension, ['jpg', 'jpeg', 'png'])) {
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
        }

        $responseData = $response->json('choices.0.message.content') ?? '';
        
        
        $cleanJson = trim($responseData);
        
        if (str_starts_with($cleanJson, '```')) {
            $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', $cleanJson);
            $cleanJson = preg_replace('/\s*```$/', '', $cleanJson);
        }
        
        $start = strpos($cleanJson, '{');
        $end = strrpos($cleanJson, '}');
        
        if ($start !== false && $end !== false) {
            $cleanJson = substr($cleanJson, $start, $end - $start + 1);
        }
        
        $metadataArray = json_decode($cleanJson, true) ?? [];
        
        if (empty($metadataArray)) {
            Log::warning("A LLM retornou um JSON vazio ou inválido.");
        }

        $confidenceLevel = $metadataArray['confidence_level'] ?? null;
        $fallbackName = pathinfo($fileName, PATHINFO_FILENAME);
        $newFileName = $metadataArray['file_name'] ?? $fallbackName;
        if (empty($metadataArray) || $confidenceLevel === null || (float)$confidenceLevel < $this->minConfidenceLevel) {
            $level = $confidenceLevel ?? 'N/A';
                        
            if (isset($metadataArray['confidence_level'])) {
                unset($metadataArray['confidence_level']);
            }

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

        return $fileModel;
    }
}
