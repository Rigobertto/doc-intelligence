<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class File extends Model
{
    use HasFactory;
    protected $fillable = ['url', 'file_name'];

    public function metaData()
    {
        return $this->hasOne(FileMetaData::class);
    }
}
