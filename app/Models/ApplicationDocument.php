<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationDocument extends Model
{
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'application_id',
        'document_id',
    ];
}