<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CallPeriodSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = DB::table('users')->insertGetId([
            'email' => 'admin@nti.sk',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'first_name' => 'Dominik',
            'last_name' => 'Admin',
            'language_preference' => 'sk',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adminRole = DB::table('roles')->where('slug', 'nti_admin')->first();
        if ($adminRole) {
            DB::table('user_roles')->insert([
                'user_id' => $adminId,
                'role_id' => $adminRole->id,
                'granted_by' => null,
                'granted_at' => now(),
            ]);
        }

        DB::table('calls')->insert([
            [
                'program' => 'a',
                'name' => 'Summer Semester 2026 - Program A Call',
                'status' => 'open',
                'opens_at' => now()->subDays(2),
                'deadline_at' => now()->addDays(60),
                'min_team_size' => 3,
                'max_team_size' => 5,
                'evaluation_criteria' => json_encode(['tech_stack' => 0.4, 'solution_design' => 0.6]),
                'required_documents' => json_encode([
                    ['document_name' => 'Executive Summary', 'is_mandatory' => false, 'max_size_mb' => 10, 'type' => 'executive_summary'],
                    ['document_name' => 'Technical Architecture', 'is_mandatory' => false, 'max_size_mb' => 15, 'type' => 'technical_architecture'],
                    ['document_name' => 'Roadmap', 'is_mandatory' => false, 'max_size_mb' => 5, 'type' => 'roadmap'],
                    ['document_name' => 'Budget', 'is_mandatory' => false, 'max_size_mb' => 15, 'type' => 'budget'],
                    ['document_name' => 'Risk Analysis', 'is_mandatory' => false, 'max_size_mb' => 15, 'type' => 'risk_analysis'],
                    ['document_name' => 'Monetization Model', 'is_mandatory' => false, 'max_size_mb' => 15, 'type' => 'monetization_model'],
                ]),
                'created_by' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
