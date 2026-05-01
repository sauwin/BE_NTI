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

    protected static function booted(): void
    {
        static::creating(function ($document) {
            $document->created_at = now();
        });
    }
}