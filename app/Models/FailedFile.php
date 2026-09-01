<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FailedFile extends Model
{
    use HasFactory;
    protected $fillable = ['url', 'file_name'];

    public function metaData()
    {
        return $this->hasOne(FailedFileMetaData::class, 'failed_file_id');
    }
}
