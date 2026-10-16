<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ImpersonationService
{
    public const SESSION_KEY = 'impersonator_id';

    public function start(User $target): void
    {
        $current = Auth::user();
        $impersonator = $this->impersonator();
        $actor = $impersonator ?? $current;

        abort_unless($actor !== null && ($actor->can('agents.manage') || $actor->hasRole('admin')), 403);
        abort_if($actor->id === $target->id, 422);

        $this->ensureTargetCanManageShifts($target);

        if (! Session::has(self::SESSION_KEY)) {
            Session::put(self::SESSION_KEY, $actor->id);
        }

        Auth::login($target);
    }

    public function leave(): User
    {
        $impersonatorId = Session::pull(self::SESSION_KEY);

        abort_if($impersonatorId === null, 403);

        $admin = User::query()->findOrFail($impersonatorId);

        Auth::login($admin);

        return $admin;
    }

    public function isImpersonating(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    public function impersonator(): ?User
    {
        $impersonatorId = Session::get(self::SESSION_KEY);

        if (! $impersonatorId) {
            return null;
        }

        return User::query()->find($impersonatorId);
    }

    protected function ensureTargetCanManageShifts(User $target): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $manageOwn = Permission::findOrCreate('schedule.manage-own', 'web');
        $checkIn = Permission::findOrCreate('schedule.check-in', 'web');

        $agentRole = Role::findOrCreate('agent', 'web');
        if (! $agentRole->hasPermissionTo($manageOwn) || ! $agentRole->hasPermissionTo($checkIn)) {
            $agentRole->givePermissionTo([$manageOwn, $checkIn]);
        }

        if (! $target->hasRole('agent')) {
            $target->assignRole($agentRole);
        }

        if (! $target->supports_phone) {
            $target->forceFill(['supports_phone' => true])->save();
        }

        $target->unsetRelation('roles')->unsetRelation('permissions');
    }
}
