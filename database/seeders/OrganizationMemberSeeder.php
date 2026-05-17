<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrganizationMemberSeeder extends Seeder
{
    public function run(): void
    {
        $companyRole = DB::table('roles')->where('slug', 'company')->first();

        if (!$companyRole) {
            $this->command->error("Role 'company' not found in roles table.");
            return;
        }

        $userRoleRelation = DB::table('user_roles')
            ->where('role_id', $companyRole->id)
            ->first();

        if (!$userRoleRelation) {
            $this->command->error("No user found with 'company' role in user_roles.");
            return;
        }

        $userId = $userRoleRelation->user_id;
        $organization = DB::table('organizations')->first();

        if (!$organization) {
            $this->command->warn("No organizations found. Creating a default test organization...");
            
            $organizationId = DB::table('organizations')->insertGetId([
                'name' => 'Test IT Company',
                'description' => 'Default organization created by seeder.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $organization = DB::table('organizations')->where('id', $organizationId)->first();
        }

        $exists = DB::table('organization_members')
            ->where('user_id', $userId)
            ->where('organization_id', $organization->id)
            ->exists();

        if (!$exists) {
            DB::table('organization_members')->insert([
                'organization_id' => $organization->id,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info("Successfully created organization and linked user ID {$userId} to it!");
        } else {
            $this->command->comment("Link already exists.");
        }
    }
}