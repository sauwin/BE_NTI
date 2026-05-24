<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ApplicationSeeder extends Seeder
{
    public function run(): void
    {
        $callId = DB::table('calls')->value('id');

        if (! $callId) {
            $this->command->warn('ApplicationSeeder: missing call — skipping.');
            return;
        }

        $studentUserId = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('roles.slug', 'student')
            ->value('user_roles.user_id');

        if (! $studentUserId) {
            $this->command->warn('ApplicationSeeder: no student user — skipping.');
            return;
        }

        $profileId = DB::table('student_profiles')->where('user_id', $studentUserId)->value('id');

        if (! $profileId) {
            $profileId = DB::table('student_profiles')->insertGetId([
                'user_id' => $studentUserId,
                'study_program' => 'Informatika',
                'year_of_study' => 2,
                'university' => 'UKF Nitra',
                'bio' => 'Test student',
                'academic_declaration_confirmed' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('applications')->insertOrIgnore([
            'id' => 1,
            'call_id' => $callId,
            'applicant_type' => 'individual',
            'program_type' => 'a',
            'student_profile_id' => $profileId,
            'team_id' => null,
            'status' => 'submitted',
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
