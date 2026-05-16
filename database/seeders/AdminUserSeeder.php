<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@nti.sk'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'zih8d7lxf@mozmail.com',
                'password_hash' => Hash::make('ChangeMe123!'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $superAdminRole = Role::where('slug', 'super_admin')->first();
        if ($superAdminRole && ! $user->roles()->where('role_id', $superAdminRole->id)->exists()) {
            DB::table('user_roles')->insert([
                'user_id' => $user->id,
                'role_id' => $superAdminRole->id,
                'granted_by' => $user->id, 
                'granted_at' => now(),
            ]);
        }

        DB::table('user_roles')
            ->whereNull('granted_by')
            ->where('user_id', '!=', $user->id)
            ->update([
                'granted_by' => $user->id,
                'granted_at' => now(),
            ]);

    }
}
