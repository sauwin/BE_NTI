<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Evaluation;
use App\Models\EvaluationCriteriaScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EvaluationController extends Controller
{
    public function index(Request $request)
    {
        $query = Evaluation::query();

        if ($request->has('application_id')) {
            $query->where('application_id', $request->application_id);
        }

        if ($request->has('evaluator_id')) {
            $query->where('evaluator_id', $request->evaluator_id);
        }

        $evaluations = $query->with(['application', 'evaluator', 'scores'])
            ->paginate(15);

        return response()->json($evaluations);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'application_id' => 'required|exists:applications,id',
            'scores' => 'required|array',
            'scores.*.criterion_key' => 'required|string',
            'scores.*.score' => 'required|numeric|min:0|max:100',
            'scores.*.weight_at_moment' => 'required|numeric|min:0|max:100',
            'scores.*.comment' => 'nullable|string',
            'recommendation' => 'required|in:approve,reject,request_revision',
            'comment' => 'nullable|string',
        ]);

        $application = Application::findOrFail($validated['application_id']);

        if (! Auth::user()->hasRole(['evaluator', 'admin', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $existing = Evaluation::where('application_id', $application->id)
            ->where('evaluator_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'You have already evaluated this application'], 409);
        }

        try {
            DB::transaction(function () use ($user, $application, $validated) {
                $overallScore = collect($validated['scores'])->avg('score');

                $evaluation = Evaluation::create([
                    'application_id' => $application->id,
                    'evaluator_id' => $user->id,
                    'status' => 'in_progress',
                    'overall_score' => $overallScore,
                    'recommendation' => $validated['recommendation'],
                    'comment' => $validated['comment'] ?? null,
                ]);

                foreach ($validated['scores'] as $score) {
                    EvaluationCriteriaScore::create([
                        'evaluation_id' => $evaluation->id,
                        'criterion_key' => $score['criterion_key'],
                        'score' => $score['score'],
                        'weight_at_moment' => $score['weight_at_moment'],
                        'comment' => $score['comment'] ?? null,
                    ]);
                }
            });

            return response()->json(['message' => 'Evaluation created'], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $evaluation = Evaluation::with(['application', 'evaluator', 'scores'])
            ->findOrFail($id);

        return response()->json($evaluation);
    }

    public function update(Request $request, $id)
    {
        $evaluation = Evaluation::findOrFail($id);

        if ($evaluation->status === 'completed') {
            return response()->json(['message' => 'Cannot update completed evaluation'], 422);
        }

        if ($evaluation->evaluator_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'scores' => 'sometimes|array',
            'scores.*.criterion_key' => 'required|string',
            'scores.*.score' => 'required|numeric|min:0|max:100',
            'scores.*.weight_at_moment' => 'required|numeric|min:0|max:100',
            'scores.*.comment' => 'nullable|string',
            'recommendation' => 'sometimes|in:approve,reject,request_revision',
            'comment' => 'nullable|string',
        ]);

        try {
            DB::transaction(function () use ($evaluation, $validated) {
                if (isset($validated['scores'])) {
                    $evaluation->scores()->delete();

                    $overallScore = collect($validated['scores'])->avg('score');
                    $evaluation->overall_score = $overallScore;

                    foreach ($validated['scores'] as $score) {
                        EvaluationCriteriaScore::create([
                            'evaluation_id' => $evaluation->id,
                            'criterion_key' => $score['criterion_key'],
                            'score' => $score['score'],
                            'weight_at_moment' => $score['weight_at_moment'],
                            'comment' => $score['comment'] ?? null,
                        ]);
                    }
                }

                if (isset($validated['recommendation'])) {
                    $evaluation->recommendation = $validated['recommendation'];
                }

                if (isset($validated['comment'])) {
                    $evaluation->comment = $validated['comment'];
                }

                $evaluation->save();
            });

            return response()->json(['message' => 'Evaluation updated']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
