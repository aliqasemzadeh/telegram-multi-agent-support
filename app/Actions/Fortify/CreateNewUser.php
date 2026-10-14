<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        if (class_exists(Role::class)) {
            $role = Role::firstOrCreate(['name' => 'agent', 'guard_name' => 'web']);
            if ($role->permissions()->doesntExist()) {
                $role->givePermissionTo([
                    Permission::firstOrCreate(['name' => 'schedule.manage-own', 'guard_name' => 'web']),
                    Permission::firstOrCreate(['name' => 'schedule.check-in', 'guard_name' => 'web']),
                ]);
            }
            $user->assignRole($role);
        }

        return $user;
    }
}
