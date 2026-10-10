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

        $guard = 'web';

        $permissions = [
            'schedule.manage-own',
            'schedule.check-in',
            'schedule.view-coverage',
            'schedule.lock-week',
            'schedule.assign',
            'agents.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, $guard);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $admin = Role::findOrCreate('admin', $guard);
        $admin->syncPermissions(
            Permission::query()
                ->where('guard_name', $guard)
                ->whereIn('name', $permissions)
                ->get()
        );

        $agent = Role::findOrCreate('agent', $guard);
        $agent->syncPermissions([
            'schedule.manage-own',
            'schedule.check-in',
        ]);
    }
}
