<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationRevisionRequest extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'application_id',
        'requested_by',
        'message',
        'deadline',
        'resolved_at',
        'created_at'
    ];

    protected $casts = [
        'deadline' => 'datetime',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}