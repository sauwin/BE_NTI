<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uploaded_by',
        'type',
        'classification',
        'version',
        'file_path',
        'file_name',
        'mime_type',
        'file_size_bytes',
    ];
}