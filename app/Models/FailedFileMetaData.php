<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedFileMetaData extends Model
{
    protected $fillable = ['failed_file_id', 'data', 'confidence_level'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'confidence_level' => 'float',
        ];
    }

    public function failedFile()
    {
        return $this->belongsTo(FailedFile::class, 'failed_file_id');
    }
}
