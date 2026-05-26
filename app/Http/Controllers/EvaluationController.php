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
    public function evaluatorApplications(Request $request)
    {
        $user = Auth::user();
        
        $this->authorize('viewAny', Evaluation::class);

        $applications = Application::whereIn('call_id', function ($query) use ($user) {
                $query->select('call_id')
                    ->from('call_evaluators')
                    ->where('user_id', $user->id);
            })
            ->where('status', 'under_evaluation')
            ->when($request->filled('program_type'), function ($q) use ($request) {
                $q->where('program_type', strtolower($request->program_type));
            })
            ->get(); 

        return response()->json(['data' => $applications]);
    }

    public function myEvaluations()
    {
        $this->authorize('viewAny', Evaluation::class);
        
        $evaluations = Evaluation::where('evaluator_id', Auth::id())
            ->with('scores')
            ->get();

        return response()->json(['data' => $evaluations]);
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Evaluation::class);

        $query = Evaluation::query();

        if (Auth::user()->hasRole('evaluator')) {
            $query->where('evaluator_id', Auth::id());
        } elseif ($request->has('evaluator_id')) {
            $query->where('evaluator_id', $request->evaluator_id);
        }

        if ($request->has('application_id')) {
            $query->where('application_id', $request->application_id);
        }

        $evaluations = $query->with(['application', 'evaluator', 'scores'])->paginate(15);

        return response()->json($evaluations);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Evaluation::class);

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
        if ($application->status !== 'under_evaluation' && !$user->hasRole(['nti_admin', 'super_admin'])) {
            return response()->json(['message' => 'Táto prihláška momentálne nie je vo fáze hodnotenia komisiou.'], 403);
        }

        $existing = Evaluation::where('application_id', $application->id)
            ->where('evaluator_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'You have already evaluated this application'], 409);
        }

        try {
            $evaluation = DB::transaction(function () use ($user, $application, $validated) {
                $overallScore = collect($validated['scores'])->sum(fn ($s) => $s['score'] * $s['weight_at_moment'] / 100);

                $evaluation = Evaluation::create([
                    'application_id' => $application->id,
                    'evaluator_id' => $user->id,
                    'status' => 'completed',
                    'overall_score' => $overallScore,
                    'recommendation' => $validated['recommendation'],
                    'comment' => $validated['comment'] ?? null,
                    'evaluated_at' => now(),
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

                return $evaluation;
            });

            return response()->json(['message' => 'Evaluation created', 'id' => $evaluation->id], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $evaluation = Evaluation::with(['application', 'evaluator', 'scores'])->findOrFail($id);

        $this->authorize('view', $evaluation);

        return response()->json($evaluation);
    }

    public function update(Request $request, $id)
    {
        $evaluation = Evaluation::findOrFail($id);

        $this->authorize('update', $evaluation);

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

                    $overallScore = collect($validated['scores'])->sum(fn ($s) => $s['score'] * $s['weight_at_moment'] / 100);
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

                $evaluation->status = 'completed';
                $evaluation->evaluated_at = now();
                $evaluation->save();
            });

            return response()->json(['message' => 'Evaluation updated']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
