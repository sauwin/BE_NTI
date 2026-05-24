<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Program;
use App\Models\Call;
use App\Models\User;

class ProgramSeeder extends Seeder
{
    public function run(): void
    {
        Program::factory()
            ->programA()
            ->create();

        Program::factory()
            ->programB()
            ->create();
    }
}