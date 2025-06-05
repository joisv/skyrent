<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

       $roles = ['super-admin', 'admin'];
        
        $this->call([
            PermissionSeeder::class,
            RolesSeeder::class
        ]);
        
        foreach ($roles as $role) {
            # code...
            User::factory()->create([
                'name' => $role,
                'email' => $role.'@example.com',
            ])->assignRole($role);
        }
    }
}
