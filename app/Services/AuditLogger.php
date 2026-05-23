<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public static function log(string $action, string $object = null, array $details = [])
    {
        DB::table('audit')->insert([
            'user_id'    => Auth::id(), // ID адміна
            'action'     => $action,
            'object'     => $object,
            'details'    => json_encode($details),
            'created_at' => now(),
        ]);
    }
}