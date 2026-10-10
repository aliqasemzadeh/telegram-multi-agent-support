<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'schedule.manage-own',
            'schedule.check-in',
            'schedule.view-coverage',
            'schedule.lock-week',
            'schedule.assign',
            'agents.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $admin = Role::findOrCreate('admin');
        $admin->syncPermissions($permissions);

        $agent = Role::findOrCreate('agent');
        $agent->syncPermissions([
            'schedule.manage-own',
            'schedule.check-in',
        ]);
    }
}
