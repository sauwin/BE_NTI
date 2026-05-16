<?php

namespace App\Policies;

use App\Models\FaqItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Auth\Access\Response;

class FaqItemPolicy
{
    private function hasAdminRole(User $user): bool
    {
        $row = DB::table('user_roles')->where('user_id', $user->id)->first();

        if (! $row) {
            return false;
        }

        $role = Role::find($row->role_id);

        return $role !== null && in_array($role->slug, ['nti_admin', 'super_admin'], true);
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FaqItem $faqItem): bool
    {
        return true;
    }

    public function create(User $user): Response|bool
    {
        return $this->hasAdminRole($user)
            ?: Response::deny('Only nti_admin or super_admin roles can create FAQ items.');
    }

    public function update(User $user, FaqItem $faqItem): Response|bool
    {
        return $this->hasAdminRole($user)
            ?: Response::deny('Only nti_admin or super_admin roles can update FAQ items.');
    }

    public function forceDelete(User $user, FaqItem $faqItem): Response|bool
    {
        return $this->hasAdminRole($user)
            ?: Response::deny('Only nti_admin or super_admin roles can delete FAQ items.');
    }
}
