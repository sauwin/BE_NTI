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
            'password_hash' => Hash::make('password123'),
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

        $programB = DB::table('programs')->where('code', 'program_b')->first();
        $programBId = $programB ? $programB->id : 2;

        $callId = DB::table('calls')->insertGetId([
            'program_id' => $programBId,
            'status' => 'open',
            'opens_at' => now()->subDays(2),
            'deadline_at' => now()->addMonths(3),
            'min_team_size' => 3,
            'max_team_size' => 5,
            'evaluation_criteria' => json_encode([
                'tech_stack' => 0.4, 
                'solution_design' => 0.6
            ]),
            'required_documents' => json_encode([
                'cv', 
                'motivation_letter', 
                'technical_proposal'
            ]),
            'created_by' => $adminId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('call_translations')->insert([
            [
                'call_id' => $callId,
                'language' => 'sk',
                'name' => 'Letný semester 2026 - Podávanie firemných zadaní',
                'description' => 'Otvorené obdobie pre technologické firmy z Nitrianskeho regiónu.'
            ],
            [
                'call_id' => $callId,
                'language' => 'en',
                'name' => 'Summer Semester 2026 - Company Briefs Submission',
                'description' => 'Open call period for industrial high-tech partners.'
            ]
        ]);
    }
}