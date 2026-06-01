<?php

namespace App\Policies;

use Illuminate\Auth\Access\Response;
use App\Models\NewsArticle;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class NewsArticlePolicy
{
    private function hasAdminRole(User $user): bool
    {
        $row = DB::table('user_roles')->where('user_id', $user->id)->first();
        if (!$row) return false;

        $role = Role::find($row->role_id);
        return $role !== null && in_array($role->slug, ['nti_admin', 'super_admin', 'content_editor'], true);
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, NewsArticle $newsArticle): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->hasAdminRole($user)
            ?: Response::deny('Only nti_admin or super_admin roles can create articles.');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, NewsArticle $article): Response|bool
    {
        return $this->hasAdminRole($user)
            ?: Response::deny('Only nti_admin or super_admin roles can update articles.');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, NewsArticle $newsArticle): bool
    {
        return $this->hasAdminRole($user)
            ?: Response::deny('Only nti_admin or super_admin roles can delete articles.');
    }
}
