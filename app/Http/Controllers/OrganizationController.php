<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationController extends Controller
{
    public function show(Request $request)
    {
        $member = OrganizationMember::where('user_id', $request->user()->id)->first();

        if (!$member) {
            return response()->json(null);
        }

        $org = Organization::find($member->organization_id);
        return response()->json($org);
    }

    public function update(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'registration_number' => 'nullable|string|max:50',
            'sector' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:2000',
            'website' => 'nullable|url|max:255',
        ]);

        DB::transaction(function () use ($request) {
            $member = OrganizationMember::where('user_id', $request->user()->id)->first();

            if ($member) {
                Organization::where('id', $member->organization_id)->update([
                    'name' => $request->name,
                    'registration_number' => $request->registration_number,
                    'sector' => $request->sector,
                    'description' => $request->description,
                    'website' => $request->website,
                ]);
            } else {
                $org = Organization::create([
                    'name' => $request->name,
                    'registration_number' => $request->registration_number,
                    'sector' => $request->sector,
                    'description' => $request->description,
                    'website' => $request->website,
                    'status' => 'pending',
                    'is_public_partner' => false,
                ]);

                OrganizationMember::create([
                    'organization_id' => $org->id,
                    'user_id' => $request->user()->id,
                    'role_in_org' => 'owner',
                ]);
            }
        });

        return response()->json(['message' => 'Profile updated']);
    }
}