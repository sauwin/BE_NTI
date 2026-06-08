<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use App\Services\AuditService;
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

    /**
     * Get publicPartners from company
     */
    public function publicPartners()
    {
        $partners = Organization::where('is_public_partner', true)
            ->get(['id', 'name', 'sector', 'website', 'description']);

        return response()->json($partners);
    }

    /**
     * Get all company (with filters)
     */
    public function index(Request $request) 
    {
        $query = Organization::query();

        if ($request->has('search_name') && $request->search_name != '') {
            $query->where('name', 'like', "%{$request->search_name}%");
        }

        if ($request->has('search_number') && $request->search_number != '') {
            $query->where('registration_number', 'like', "%{$request->search_number}%");
        }

        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        $companies = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($companies);
    }

    /**
     * Approve company and activate owner
     */
    public function approveCompany(Request $request, int $orgId) 
    {
        DB::transaction(function () use ($orgId)
        {
            $org = Organization::findOrFail($orgId);

            $org->update(['status' => 'active']);

            User::where('organization_id', $orgId)
                ->where('role_in_org', 'owner')
                ->update(['status' => 'active']);

            return $org;
        });

        AuditService::log('Approve', 'Organization', [
            'target_org_id' => $orgId,
            'target_org_number' => $org->registration_number,
        ]);

        return response()->json(['message' => 'Company approved']);
    }

    /**
     * Reject company and deactivate owner
     */
    public function rejectCompany(Request $request, int $orgId) 
    {

        DB::transaction(function () use ($orgId)
        {
            $org = Organization::findOrFail($orgId);

            User::where('organization_id', $orgId)
                ->where('role_in_org', 'owner')
                ->update(['status' => 'pending_approval']);

            $org->delete();
        });

        AuditService::log('Reject', 'Organization', [
            'target_org_id' => $orgId,
            'target_org_number' => $org->registration_number,
        ]);

        return response()->json(['message' => 'Company rejected']);
    }

    /**
     * Activate company 
     */
    public function activateCompany(Request $request, int $orgId) 
    {
        $org = Organization::findOrFail($orgId);

        $org->update(['status' => 'active']);

        AuditService::log('Activate', 'Organization', [
            'target_org_id' => $orgId,
            'target_org_number' => $org->registration_number,
        ]);

        return response()->json(['message' => 'Company activated']);
    }

    /**
     * Deactivate company
     */
    public function deactivateCompany(Request $requst, int $orgId) 
    {
        $org = Organization::findOrFail($orgId);

        $org->update(['status' => 'inactive']);

        AuditService::log('Deactivate', 'Organization', [
            'target_org_id' => $orgId,
            'target_org_number' => $org->registration_number,
        ]);

        return response()->json(['message' => 'Company deactivated']);
    }

    /**
     * Delete company from DB
     */
    public function deleteCompany(Request $requst, int $orgId) 
    {
        $org = Organization::findOrFail($orgId);

        $org->delete();

        AuditService::log('Delete', 'Organization', [
            'target_org_id' => $orgId,
            'target_org_number' => $org->registration_number,
        ]);

        return response()->json(['message' => 'Company deleted']);
    }

    /**
     * Update partner status
     */
    public function updateCompanyPartnerStatus(Request $request, int $orgId)
    {
        $org = Organization::findOrFail($orgId);

        if ($org->is_public_partner === 0) {
            $org->update(['is_public_partner' => '1']);
        }

        if ($org->is_public_partner === 1) {
            $org->update(['is_public_partner' => '0']);
        }

        AuditService::log('Update', 'Partner Status', [
            'target_org_id' => $orgId,
            'target_org_number' => $org->registration_number,
        ]);

        return response()->json(['message' => 'Partner status changed']);
    }

    /**
     * Get contact members for partnes company
     */
    public function ContactMember(Request $request, int $partnerId) 
    {
        $contacts = User::query()
            ->select('id', 'first_name', 'last_name', 'email')
            ->where('organization_id', $partnerId)
            ->where('role_in_org', 'contact')
            ->get();

        return response()->json($contacts);
    }
}