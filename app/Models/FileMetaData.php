<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileMetaData extends Model
{
    protected $fillable = ['file_id', 'data'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function file()
    {
        return $this->belongsTo(File::class);
    }
}
