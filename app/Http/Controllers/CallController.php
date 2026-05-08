<?php

namespace App\Http\Controllers;

use App\Models\Call;
use Illuminate\Http\Request;

class CallController extends Controller
{
    public function active(string $program_type)
    {
        $call = Call::whereHas('program', fn($q) => $q->where('code', 'program_' . $program_type))
                    ->where('status', 'open')
                    ->latest()
                    ->first();

        if (!$call) {
            return response()->json(['message' => 'No active call found'], 404);
        }

        return response()->json(['call_id' => $call->id]);
    }
}