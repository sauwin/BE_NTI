<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use App\Models\EvaluationCriterion;
use App\Models\Call;

class EvaluationCriteriaController extends Controller
{
    /**
     * Display all evaluation criteria
     */
    public function index(Request $request, Call $call)
    {
        $this->authorize('viewCriteria', $call);

        return response()->json($call->criteria);
    }

    /**
     * Store a new set of evaluation criteria for a call
     */
    public function sync(Request $request, Call $call)
    {
        //Request validation
        $data = $request->validate([
            'criteria' => 'required|array|min:1',
            'criteria.*.id' => 'nullable|exists:evaluation_criteria,id',
            'criteria.*.slug' => 'required|max:35',
            'criteria.*.title' => 'required|string|max:255',
            'criteria.*.weight' => 'required|integer|between:0,100',
            'criteria.*.comment' => 'nullable|string',
        ],
        [],
        [
            'criteria.*.id' => 'criterion id',
            'criteria.*.slug' => 'criterion slug',
            'criteria.*.title' => 'criterion title',
            'criteria.*.weight' => 'criterion weight',
            'criteria.*.comment' => 'criterion comment',
        ]);

        //Whether all weights sum up to 100
        $sum = collect($data['criteria'])->sum('weight');

        if ($sum !== 100) {
            throw ValidationException::withMessages([
                'criteria' => 'Total weight must sum up to 100%',
            ]);
        }

        //Whether slugs are unique
        $slugs = collect($data['criteria'])->pluck('slug');

        if ($slugs->count() !== $slugs->unique()->count()) {
            throw ValidationException::withMessages([
                'criteria' => 'Criterion slugs must be unique.',
            ]);
        }

        //Whether user in not trying to update wrong call criterion
        $existingIds = collect($data['criteria'])
            ->pluck('id')
            ->filter();

        $invalidIds = $existingIds
            ->diff(
                $call->criteria()
                    ->pluck('id')
            );

        if ($invalidIds->isNotEmpty()) 
        {
            throw ValidationException::withMessages([
                'criteria' => 'Criterion belongs to wrong call',
            ]);
        }

        //Creating or updating
        DB::transaction(function () use ($call, $data, $existingIds) {

            $call->criteria()
                ->whereNotIn('id', $existingIds)
                ->delete();

            foreach ($data['criteria'] as $criterion) {
                $call->criteria()->updateOrCreate(
                    ['id' => $criterion['id'] ?? null],
                    [
                        'slug' => $criterion['slug'],
                        'title' => $criterion['title'],
                        'weight' => $criterion['weight'],
                        'comment' => $criterion['comment'] ?? null,
                    ]
                );
            }
        });

        return response()->json(
            $call->criteria()->get()
        );
    }
}
