<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('roles')->insert([
            ['name' => 'Student', 'slug' => 'student', 'description' => 'Student applying to programs'],
            ['name' => 'Company', 'slug' => 'company', 'description' => 'Company posting tasks for Program B'],
            ['name' => 'Mentor', 'slug' => 'mentor', 'description' => 'Mentor assigned to projects'],
            ['name' => 'Evaluator', 'slug' => 'evaluator', 'description' => 'Commission member evaluating applications'],
            ['name' => 'Content Editor', 'slug' => 'content_editor', 'description' => 'Manages public web content'],
            ['name' => 'NTI Admin', 'slug' => 'nti_admin', 'description' => 'Operational program administrator'],
            ['name' => 'Super Admin', 'slug' => 'super_admin', 'description' => 'Full system access'],
        ]);
    }
}