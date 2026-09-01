<?php

namespace App\Jobs;

use App\Services\AIService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

enum ProcessStatus: string
{
    case Pending = 'Pending';
    case Processing = 'Processing';
    case Failed = 'Failed';
}

#[Tries(3)]
class ProcessUploadedFile implements ShouldQueue
{
    use Queueable;

    public string $identifier;
    public ProcessStatus $status;

    public function __construct(string $identifier)
    {
        $this->identifier = $identifier;
        $this->status = ProcessStatus::Pending;
    }

    public function handle(): void
    {
        $this->status = ProcessStatus::Processing;

        try {
            $service = env('USE_AI_MOCK', true) 
                ? app(\App\Services\AIMockService::class) 
                : app(\App\Services\AIService::class);

            $service->document_analiser($this->identifier);
        } catch (\Exception $e) {
            $this->status = ProcessStatus::Failed;
            throw $e;
        }
    }
}
