<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminActionLog extends Model
{
    protected $fillable = [
        'admin_id',
        'action',
        'action_type',
        'target_user_id',
        'details',
        'ip_address',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
