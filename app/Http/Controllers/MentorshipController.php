<?php

namespace App\Http\Controllers;

use App\Mail\MentorAssignedMail;
use App\Models\User;
use App\Models\Mentorship;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class MentorshipController extends Controller
{
    public function assign(Request $request)
    {
        $data = $request->validate([
            'application_id' => 'required|exists:applications,id',
            'mentor_id' => [
                'required',
                'exists:users,id',
                \Illuminate\Validation\Rule::unique('mentorships', 'mentor_id')->where(function ($query) use ($request) {
                    return $query->where('application_id', $request->application_id);
                }),
            ],
            'student_id' => 'required|exists:users,id',
        ], [
            'mentor_id.unique' => 'This mentor was already assigned to this application.',
        ]);

        $mentorship = Mentorship::create([
            'application_id' => $data['application_id'],
            'mentor_id' => $data['mentor_id'],
            'assigned_at' => now(),
        ]);

        $mentor = User::findOrFail($data['mentor_id']);
        $student = User::findOrFail($data['student_id']);

        Mail::to($student->email)->queue(new MentorAssignedMail($student, $mentor));

        return response()->json(['message' => 'Mentor assigned', 'data' => $mentorship], 201);
    }

    public function index(Request $request)
    {
        $mentorships = Mentorship::with(['application.team', 'application.call']) 
            ->where('mentor_id', $request->user()->id)
            ->whereNull('ended_at')
            ->get();

        return response()->json($mentorships);
    }

    public function show(Request $request, $id)
    {
        $mentorship = Mentorship::with(['application.team', 'application.call', 'consultations'])
            ->where('mentor_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json($mentorship);
    }

    public function logConsultation(Request $request, $id)
    {
        $mentorship = Mentorship::where('mentor_id', $request->user()->id)->findOrFail($id);

        $data = $request->validate([
            'date' => 'required|date|before_or_equal:today',
            'summary' => 'required|string|min:10|max:2000',
            'duration_minutes' => 'required|integer|min:5|max:480',
        ]);

        $consultation = $mentorship->consultations()->create($data);

        return response()->json(['message' => 'Consultation logged successfully', 'data' => $consultation], 201);
    }

    public function consultations(Request $request, $id)
    {
        $mentorship = Mentorship::where('mentor_id', $request->user()->id)->findOrFail($id);
        
        return response()->json($mentorship->consultations()->orderBy('date', 'desc')->get());
    }

    public function adminIndex(Request $request)
    {
        if (!$request->user()->tokenCan('admin') && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = Mentorship::with(['mentor', 'application.team', 'application.call']);

        if ($request->has('program_id')) {
            $query->whereHas('application', function ($q) use ($request) {
                $q->where('program_id', $request->program_id);
            });
        }

        if ($request->has('call_id')) {
            $query->whereHas('application', function ($q) use ($request) {
                $q->where('call_id', $request->call_id);
            });
        }

        return response()->json($query->get());
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $mentorship = Mentorship::findOrFail($id);

        $isAdmin = $user->tokenCan('admin') || $user->role === 'admin' || 
                ($user->roles && $user->roles->contains('slug', 'super_admin'));

        $isCurrentMentor = $user->roles->contains('slug', 'mentor') && ($mentorship->mentor_id === $user->id);

        if (!$isAdmin && !$isCurrentMentor) {
            return response()->json(['message' => 'Unauthorized. You cannot remove this mentorship.'], 403);
        }

        $mentorship->delete();

        return response()->json(['message' => 'Mentorship removed successfully']);
    }
}
