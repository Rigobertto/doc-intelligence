<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

#[Tries(3)]
class ProcessUploadedFile implements ShouldQueue
{
    use Queueable;

    public string $identifier;

    /**
     * Create a new job instance.
     */
    public function __construct(string $identifier)
    {
        $this->identifier = $identifier;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Process the file using $this->identifier
    }
}
