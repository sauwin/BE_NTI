<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Call;
use App\Models\CallEvaluator;
use App\Models\User;
use App\Notifications\EvaluationAssigned;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @tags Call Management
 * Endpoints for assigning expert evaluators to program calls, listing assigned reviewers, and managing evaluation permissions.
 */
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
        if (! Auth::user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $call = Call::findOrFail($callId);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($validated['user_id']);

        if (!$user->hasRole('evaluator') && !$user->role_in_org == 'evaluator') {
            return response()->json(['message' => 'User is not an evaluator'], 422);
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

        NotificationController::log($user->id, $user->email, 'evaluator_assigned', 'You have been assigned as evaluator for: '.$call->title, ['call_id' => $call->id]);
        $admins = User::whereHas('roles', fn ($q) => $q->whereIn('slug', ['nti_admin', 'super_admin']))->get();
        foreach ($admins as $admin) {
            NotificationController::log($admin->id, $admin->email, 'evaluator_assigned_success', 'Evaluator '.$user->email.' assigned to call: '.$call->title, ['call_id' => $call->id, 'evaluator_id' => $user->id]);
        }
        $applications = Application::where('call_id', $call->id)->with('studentProfile.user', 'mentorships.mentor')->get();
        foreach ($applications as $app) {
            if ($app->studentProfile?->user) {
                $student = $app->studentProfile->user;
                NotificationController::log($student->id, $student->email, 'evaluator_assigned', 'An evaluator has been assigned to review your application.', ['call_id' => $call->id]);
            }
            foreach ($app->mentorships as $mentorship) {
                $mentor = $mentorship->mentor;
                NotificationController::log($mentor->id, $mentor->email, 'evaluator_assigned', 'An evaluator has been assigned to call: '.$call->title, ['call_id' => $call->id]);
            }
        }

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
        if (! Auth::user()->isAdmin()) {
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
