<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\CallOrganization;
use App\Models\OrganizationMember;
use Illuminate\Http\Request;

class CallOrganizationController extends Controller
{
    public function publicTasks()
    {
        $tasks = CallOrganization::with(['call.program', 'organization'])
            ->where('status', 'published')
            ->whereHas('call.program', function ($query) {
                $query->where('code', 'program_b');
            })
            ->get();

        return response()->json($tasks);
    }

    public function index(Request $request)
    {
        $tasks = CallOrganization::with(['call.program', 'organization'])
            ->where('product_owner_user_id', $request->user()->id)
            ->get();

        return response()->json($tasks);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'call_id' => 'required|integer|exists:calls,id',
            'budget' => 'nullable|numeric|min:0',
            'brief' => 'required|string|max:2000',
        ]);

        $member = OrganizationMember::where('user_id', $request->user()->id)->first();

        if (! $member) {
            return response()->json([
                'message' => 'You must belong to an organization before creating a challenge.',
            ], 403);
        }

        $call = Call::with('program')->findOrFail($data['call_id']);

        if ($call->program->code !== 'program_b') {
            return response()->json([
                'message' => 'Only Program B calls can receive company tasks.',
            ], 422);
        }

        $task = CallOrganization::create([
            'call_id' => $data['call_id'],
            'organization_id' => $member->organization_id,
            'product_owner_user_id' => $request->user()->id,
            'budget' => $data['budget'],
            'brief' => $data['brief'],
            'status' => 'published',
        ]);

        return response()->json($task, 201);
    }
}
