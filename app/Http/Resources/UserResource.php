<?php
/**
 * Resource for wrapping authorized user data
 * 
 * Used in: 
 * FE_NTI/src/features/auth/stores/auth.ts
 */

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\User;
use App\Models\Role;
use App\Models\Organization;

class UserResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = optional($this->roles)->first();

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'status' => $this->status,

            'role_slug' => $role?->slug,
            'organization_id' => $this->organization_id,
            'role_in_org' => $this->role_in_org
        ];
    }
}
