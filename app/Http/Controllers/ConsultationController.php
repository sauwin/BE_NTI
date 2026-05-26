<?php

namespace App\Http\Controllers;

use App\Models\Consultation;
use App\Models\Mentorship;
use Illuminate\Http\Request;

class ConsultationController extends Controller
{
    public function store(Request $request, $mentorshipId)
    {
        $mentorship = Mentorship::findOrFail($mentorshipId);

        $this->authorize('manageConsultations', $mentorship);

        $isPastOrToday = $request->input('date') <= now()->toDateString();

        $data = $request->validate([
            'date' => 'required|date',
            'duration_minutes' => 'required|integer|min:5|max:480',
            'summary' => [
                $isPastOrToday ? 'required' : 'nullable',
                'string',
                'min:10',
                'max:2000',
            ],
        ], [
            'summary.required' => 'Meeting summary is required for consultations that have already occurred.',
        ]);

        $consultation = $mentorship->consultations()->create($data);

        return response()->json([
            'message' => 'Consultation record saved successfully.',
            'data' => $consultation,
        ], 201);
    }

    public function update(Request $request, $mentorshipId, $id)
    {
        $mentorship = Mentorship::findOrFail($mentorshipId);

        $this->authorize('manageConsultations', $mentorship);

        $consultation = $mentorship->consultations()->findOrFail($id);

        $isPastOrToday = $request->input('date', $consultation->date) <= now()->toDateString();

        $data = $request->validate([
            'date' => 'sometimes|required|date',
            'duration_minutes' => 'sometimes|required|integer|min:5|max:480',
            'summary' => [
                $isPastOrToday ? 'required' : 'nullable',
                'string',
                'min:10',
                'max:2000',
            ],
        ], [
            'summary.required' => 'Meeting summary is required for completed consultations.',
        ]);

        $consultation->update($data);

        return response()->json([
            'message' => 'Consultation log updated successfully.',
            'data' => $consultation,
        ]);
    }

    public function destroy(Request $request, $mentorshipId, $id)
    {
        $mentorship = Mentorship::findOrFail($mentorshipId);

        $this->authorize('manageConsultations', $mentorship);

        $consultation = $mentorship->consultations()->findOrFail($id);

        $consultation->delete();

        return response()->json([
            'message' => 'Consultation log removed successfully.',
        ]);
    }
}
