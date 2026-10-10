<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'supports_phone' => true,
            'supports_chat' => true,
        ]);
        $admin->assignRole('admin');

        $agent = User::factory()->create([
            'name' => 'Test Agent',
            'email' => 'test@example.com',
            'supports_phone' => true,
            'supports_chat' => true,
        ]);
        $agent->assignRole('agent');
    }
}
