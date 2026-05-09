<?php
namespace App\Http\Controllers;

use App\Mail\MentorAssignedMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class MentorshipController extends Controller
{
    public function assign(Request $request)
    {
        $data = $request->validate([
            'application_id' => 'required|exists:applications,id',
            'mentor_id'      => 'required|exists:users,id',
            'student_id'     => 'required|exists:users,id',
        ]);

        DB::table('mentorships')->insert([
            'application_id' => $data['application_id'],
            'mentor_id'      => $data['mentor_id'],
            'status'         => 'active',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $mentor  = User::findOrFail($data['mentor_id']);
        $student = User::findOrFail($data['student_id']);

        Mail::to($student->email)->send(new MentorAssignedMail($student, $mentor));

        return response()->json(['message' => 'Mentor assigned']);
    }
}
