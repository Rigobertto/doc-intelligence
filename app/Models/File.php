<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    protected $fillable = ['url', 'file_name'];

    public function metaData()
    {
        return $this->hasOne(FileMetaData::class);
    }
}
