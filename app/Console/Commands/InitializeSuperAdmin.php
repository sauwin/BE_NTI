<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InitializeSuperAdmin extends Command
{
    protected $signature = 'app:initializeSuperAdmin';
    protected $description = 'Initialize super admin from environment variables';

    public function handle()
    {
        $email = env('SUPER_ADMIN_EMAIL');
        $password = env('SUPER_ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->error('SUPER_ADMIN_EMAIL SUPER_ADMIN_EMAILor SUPER_ADMIN_PASSWORD not set in .env');
            return 1;
        }

        if (User::where('email', $email)->exists()) {
            $this->info('Super admin already exists');
            return 0;
        }

        DB::transaction(function () use ($email, $password) {
            $user = User::create([
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => $email,
                'password' => Hash::make($password),
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            $role = Role::where('slug', 'super_admin')->firstOrFail();
            DB::table('user_roles')->insert([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'granted_by' => $user->id,
                'granted_at' => now(),
            ]);
        });

        $this->info('Super admin initialized successfully');
        return 0;
    }
}