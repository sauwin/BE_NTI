<?php

namespace App\Http\Controllers;

use App\Models\Mentorship;
use App\Models\Consultation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConsultationController extends Controller
{
    /**
     * Store a newly created consultation (planned or past).
     */
    public function store(Request $request, $mentorshipId)
    {
        $mentorship = Mentorship::where('mentor_id', $request->user()->id)->findOrFail($mentorshipId);

        $isPastOrToday = $request->input('date') <= now()->toDateString();

        $data = $request->validate([
            'date' => 'required|date',
            'duration_minutes' => 'required|integer|min:5|max:480',
            'summary' => [
                $isPastOrToday ? 'required' : 'nullable',
                'string',
                'min:10',
                'max:2000'
            ],
        ], [
            'summary.required' => 'Meeting summary is required for consultations that have already occurred.',
        ]);

        $consultation = $mentorship->consultations()->create($data);

        return response()->json([
            'message' => 'Consultation record saved successfully.',
            'data' => $consultation
        ], 201);
    }

    /**
     * Update an existing consultation (e.g., adding summary after the meeting concluded).
     */
    public function update(Request $request, $mentorshipId, $id)
    {
        $mentorship = Mentorship::where('mentor_id', $request->user()->id)->findOrFail($mentorshipId);
        $consultation = $mentorship->consultations()->findOrFail($id);

        $isPastOrToday = $request->input('date', $consultation->date) <= now()->toDateString();

        $data = $request->validate([
            'date' => 'sometimes|required|date',
            'duration_minutes' => 'sometimes|required|integer|min:5|max:480',
            'summary' => [
                $isPastOrToday ? 'required' : 'nullable',
                'string',
                'min:10',
                'max:2000'
            ],
        ], [
            'summary.required' => 'Meeting summary is required for completed consultations.',
        ]);

        $consultation->update($data);

        return response()->json([
            'message' => 'Consultation log updated successfully.',
            'data' => $consultation
        ]);
    }

    /**
     * Delete an unneeded or misconfigured consultation slot.
     */
    public function destroy(Request $request, $mentorshipId, $id)
    {
        $mentorship = Mentorship::where('mentor_id', $request->user()->id)->findOrFail($mentorshipId);
        $consultation = $mentorship->consultations()->findOrFail($id);

        $consultation->delete();

        return response()->json([
            'message' => 'Consultation log removed successfully.'
        ]);
    }
}