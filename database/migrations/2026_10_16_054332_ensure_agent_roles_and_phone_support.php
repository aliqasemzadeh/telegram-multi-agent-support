<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('users')) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

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

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $adminRole = Role::findOrCreate('admin', $guard);
        $adminRole->syncPermissions(
            Permission::query()
                ->where('guard_name', $guard)
                ->whereIn('name', $permissions)
                ->get()
        );

        $agentRole = Role::findOrCreate('agent', $guard);
        $agentRole->syncPermissions([
            'schedule.manage-own',
            'schedule.check-in',
        ]);

        User::query()
            ->doesntHave('roles')
            ->each(function (User $user) use ($agentRole): void {
                $user->assignRole($agentRole);
            });

        User::query()
            ->where('supports_phone', false)
            ->update(['supports_phone' => true]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
