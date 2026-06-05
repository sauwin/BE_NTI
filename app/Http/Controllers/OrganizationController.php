<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * @tags Organization Management
 * Endpoints for retrieving authenticated user-associated organization profiles, handling atomic creation or update operations for organization entities, and fetching a directory of public partners.
 */
class OrganizationController extends Controller
{
    /**
     * Looking for a user-organization
     */
    public function show(Request $request)
    {
        $org = $request->user()->organization;

        if (!$org) {
            return response()->json(null);
        }

        Gate::authorize('view', $org);
        return response()->json($org);
    }

    /**
     * Update or create new org (for owner)
     */
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
            $user = $request->user();
            $org = $user->organization;

            if ($org) {
                Gate::authorize('update', $org);

                $org->update([
                    'name' => $request->name,
                    'registration_number' => $request->registration_number,
                    'sector' => $request->sector,
                    'description' => $request->description,
                    'website' => $request->website,
                ]);
            } else {
                Gate::authorize('create', Organization::class);
                \Log::info('After authorize');

                $org = Organization::create([
                    'name' => $request->name,
                    'registration_number' => $request->registration_number,
                    'sector' => $request->sector,
                    'description' => $request->description,
                    'website' => $request->website,
                    'status' => 'pending',
                    'is_public_partner' => false,
                ]);

                $user->update([
                    'organization_id' => $org->id,
                    'role_in_org' => 'owner',
                ]);
            }
        });

        return response()->json(['message' => 'Profile updated']);
    }

    public function publicPartners()
    {
        $partners = Organization::where('is_public_partner', true)
            ->get(['id', 'name', 'sector', 'website', 'description']);

        return response()->json($partners);
    }
}