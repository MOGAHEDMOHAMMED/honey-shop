<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdministrator extends Command
{
    protected $signature = 'app:admin-create';

    protected $description = 'Create the first administrator using the existing Administrator role';

    public function handle(): int
    {
        $role = Role::query()->where('name', 'Administrator')->firstOrFail();

        if (DB::table('user_roles')->where('role_id', $role->getKey())->exists()) {
            $this->error(__('auth.admin_exists'));

            return self::FAILURE;
        }

        $username = trim((string) $this->ask(__('auth.username')));
        $displayName = trim((string) $this->ask(__('auth.display_name')));
        $password = (string) $this->secret(__('catalog.fields.password'));
        $passwordConfirmation = (string) $this->secret(__('auth.password_confirmation'));

        $validator = Validator::make([
            'username' => $username,
            'display_name' => $displayName,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'username' => ['required', 'string', 'max:255', 'unique:app_users,username'],
            'display_name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)->max(72)],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $created = DB::transaction(function () use ($role, $username, $displayName, $password): bool {
            $lockedRole = Role::query()->whereKey($role->getKey())->lockForUpdate()->firstOrFail();

            if (DB::table('user_roles')->where('role_id', $lockedRole->getKey())->exists()) {
                return false;
            }

            $user = User::query()->create([
                'username' => $username,
                'display_name' => $displayName,
                'password_hash' => Hash::make($password),
                'is_active' => true,
            ]);

            DB::table('user_roles')->insert([
                'user_id' => $user->getKey(),
                'role_id' => $lockedRole->getKey(),
            ]);

            return true;
        });

        if (! $created) {
            $this->error(__('auth.admin_exists'));

            return self::FAILURE;
        }

        Log::info('Initial administrator created', [
            'actor_user_id' => DB::table('user_roles')
                ->where('role_id', $role->getKey())
                ->value('user_id'),
        ]);

        $this->info(__('auth.admin_created'));

        return self::SUCCESS;
    }
}
