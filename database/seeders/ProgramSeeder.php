<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Program;
use App\Models\Call;

class ProgramSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::factory()->create();

        $callState = [
            'created_by' => $user->id,
            'status' => 'open',
            'min_team_size' => 3
        ];

        Program::factory()
            ->programA()
            ->has(Call::factory()->state($callState), 'calls')
            ->create();

        Program::factory()
            ->programB()
            ->has(Call::factory()->state($callState), 'calls')
            ->create();
    }
}