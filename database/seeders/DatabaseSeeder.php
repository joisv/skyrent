<?php

namespace Database\Seeders;

use App\Models\Gallery as ModelsGallery;
use App\Models\Iphones;
use App\Models\Payment;
use App\Models\Revenue;
use App\Models\User;
use Database\Factories\FaqFactory;
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
            RolesSeeder::class,
            ShopSettingsSeeder::class,
            AffiliateSeeder::class,
        ]);
        
        foreach ($roles as $role) {
            $user = User::firstOrCreate(
                ['email' => $role.'@example.com'],
                [
                    'name' => $role,
                    'password' => bcrypt('password'),
                ]
            );
            $user->syncRoles([$role]);
        }

        // Add default mobile admin & kasir users
        $adminMobile = User::firstOrCreate(
            ['email' => 'admin@skyrental.id'],
            [
                'name' => 'Admin SKYRental',
                'password' => bcrypt('password123'),
            ]
        );
        $adminMobile->syncRoles(['super-admin']);

        $kasirMobile = User::firstOrCreate(
            ['email' => 'kasir@skyrental.id'],
            [
                'name' => 'Budi Kasir',
                'password' => bcrypt('password123'),
            ]
        );
        $kasirMobile->syncRoles(['admin']);

        if (ModelsGallery::count() == 0) {
            ModelsGallery::factory(10)->create();
        }
        if (Iphones::count() == 0) {
            Iphones::factory(10)->create();
        }
        if (Revenue::count() == 0) {
            Revenue::factory()->count(5)->create();
        }
        if (Payment::count() == 0) {
            Payment::factory()->count(5)->create();
        }
    }
}
