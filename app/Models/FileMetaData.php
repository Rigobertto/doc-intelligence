<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FileMetaData extends Model
{
    use HasFactory;
    protected $fillable = ['file_id', 'data', 'confidence_level'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'confidence_level' => 'float',
        ];
    }

    public function file()
    {
        return $this->belongsTo(File::class);
    }
}
