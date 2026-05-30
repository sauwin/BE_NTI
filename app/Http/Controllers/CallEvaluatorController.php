<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\CallEvaluator;
use App\Models\User;
use App\Notifications\EvaluationAssigned;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\AuditService;

class CallEvaluatorController extends Controller
{
    public function index($callId)
    {
        $call = Call::findOrFail($callId);

        $evaluators = CallEvaluator::where('call_id', $call->id)
            ->with('evaluator')
            ->get()
            ->map(function ($ce) {
                return [
                    'id' => $ce->evaluator->id,
                    'name' => $ce->evaluator->name,
                    'email' => $ce->evaluator->email,
                    'assigned_at' => $ce->assigned_at,
                ];
            });

        return response()->json($evaluators);
    }

    public function assign(Request $request, $callId)
    {
        if (! Auth::user()->hasRole(['nti_admin', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $call = Call::findOrFail($callId);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($validated['user_id']);

        if (! $user->hasRole('evaluator')) {
            return response()->json(['message' => 'User does not have evaluator role'], 422);
        }

        $existing = CallEvaluator::where('call_id', $call->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'User is already assigned to this call'], 409);
        }

        CallEvaluator::create([
            'call_id' => $call->id,
            'user_id' => $user->id,
        ]);

        AuditService::log('assign_evaluator', 'call', [
            'call_id' => $call->id,
            'user_id' => $user->id,
            'user_email' => $user->email,
        ]);

        $user->notify(new EvaluationAssigned($call));

        AuditService::log('assign_evaluator', 'call', [
            'call_id' => $call->id,
            'user_id' => $user->id,
            'user_email' => $user->email,
        ]);

        return response()->json(['message' => 'Evaluator assigned'], 201);
    }

    public function remove($callId, $userId)
    {
        if (! Auth::user()->hasRole(['nti_admin', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $call = Call::findOrFail($callId);

        CallEvaluator::where('call_id', $call->id)
            ->where('user_id', $userId)
            ->delete();

        AuditService::log('remove_evaluator', 'call', [
            'call_id' => $call->id,
            'user_id' => $userId,
        ]);

        return response()->json(['message' => 'Evaluator removed']);
    }
}
