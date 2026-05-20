<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Role;
use App\Models\User;
use App\Models\Organization;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::all()->keyBy('slug');

        $slugs = ['student', 'company', 'mentor', 'evaluator', 'content_editor'];

        User::factory(10)->create()->each(function (User $user) use ($roles, $slugs) {

            $slug = $slugs[array_rand($slugs)];
            $role = $roles->get($slug);

            if ($role) {
                DB::table('user_roles')->insert([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'granted_by' => $user->id,
                    'granted_at' => now(),
                ]);
            }

            if ($slug === 'company') {

                $organization = Organization::create([
                    'name' => fake()->company(),
                    'registration_number' => fake()->unique()->numerify('########'),
                    'sector' => fake()->word(),
                    'description' => fake()->sentence(),
                    'website' => fake()->url(),
                    'status' => 'active',
                    'is_public_partner' => false,
                ]);

                $user->update([
                    'organization_id' => $organization->id,
                    'role_in_org' => 'owner',
                ]);
            }
        });
    }
}