<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedFile extends Model
{
    protected $fillable = ['url', 'file_name'];

    public function metaData()
    {
        return $this->hasOne(FailedFileMetaData::class, 'failed_file_id');
    }
}
