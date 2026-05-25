<?php

namespace Database\Seeders;

use App\Models\Call;
use App\Models\User;
use App\Models\Team;
use App\Models\StudentProfile;
use App\Models\Application;
use App\Models\Program;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TeamAndApplicationSeeder extends Seeder
{
    public function run(): void
    {
        $callA = Call::whereHas('program', fn($q) => $q->where('code', 'program_a'))->first() 
            ?? Call::factory()->create(['status' => 'open']);

        $callB = Call::whereHas('program', fn($q) => $q->where('code', 'program_b'))->first() 
            ?? Call::factory()->create(['status' => 'open']);

        $studentIds = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('roles.slug', 'student')
            ->pluck('user_id');

        $students = User::whereIn('id', $studentIds)->get();

        if ($students->isEmpty()) {
            $students = User::factory(10)->create();
        }

        foreach ($students as $student) {
            // Only create StudentProfile if it doesn't already exist
            StudentProfile::firstOrCreate(
                ['user_id' => $student->id],
                [
                    'study_program' => fake()->word(),
                    'year_of_study' => fake()->numberBetween(1, 4),
                    'university' => fake()->sentence(),
                    'bio' => fake()->paragraph(),
                    'github_url' => fake()->url(),
                    'academic_declaration_confirmed' => true,
                    'cv_document_id' => null,
                ]
            );
        }

        $profiles = StudentProfile::whereIn('user_id', $students->pluck('id'))->get();

        $chunks = $students->chunk(4); 

        foreach ($chunks as $index => $group) {
            if ($group->count() < 3) {
                continue; 
            }

            $leader = $group->first();
            $members = $group->slice(1);

            $isReady = $index === 0; 
            
            $team = Team::factory()->create([
                'leader_id' => $leader->id,
                'status' => $isReady ? 'ready' : 'forming',
            ]);

            DB::table('team_members')->insert([
                'team_id' => $team->id,
                'user_id' => $leader->id,
                'status' => 'accepted',
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($members as $member) {
                DB::table('team_members')->insert([
                    'team_id' => $team->id,
                    'user_id' => $member->id,
                    'status' => 'accepted',
                    'joined_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($isReady) {
                $leaderProfile = $profiles->where('user_id', $leader->id)->first();
                
                Application::factory()->create([
                    'call_id' => $callA->id,
                    'applicant_type' => 'team',
                    'program_type' => 'a',
                    'team_id' => $team->id,
                    'student_profile_id' => $leaderProfile->id,
                    'status' => 'submitted', 
                ]);
            }
        }

        foreach ($profiles->take(3) as $profile) {
            Application::factory()->create([
                'call_id' => $callB->id,
                'applicant_type' => 'student',
                'program_type' => 'b',
                'team_id' => null,
                'student_profile_id' => $profile->id,
                'status' => fake()->randomElement(['draft', 'submitted']),
            ]);
        }
    }
}