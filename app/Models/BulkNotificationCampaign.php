<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkNotificationCampaign extends Model
{
    protected $fillable = [
        'recipient_group',
        'subject',
        'message',
        'total_recipients',
        'sender_id'
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}