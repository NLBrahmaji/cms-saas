<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Auth\RegisteredAccountResource;
use App\Http\Resources\Auth\RegisteredUserResource;
use App\Models\Account;
use App\Models\AccountMember;
use App\Models\User;
use Database\Seeders\AccountPermissionSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RegisterController extends Controller
{
    public function store(RegisterRequest $request): JsonResponse
    {
        [$user, $account] = DB::transaction(function () use ($request): array {
            $user = User::query()->create([
                'name' => $request->string('name')->toString(),
                'email' => $request->string('email')->toString(),
                'password' => $request->string('password')->toString(),
            ]);

            $account = Account::query()->create([
                'owner_id' => $user->id,
                'name' => $user->name,
                'status' => 'active',
            ]);

            AccountMember::query()->create([
                'account_id' => $account->id,
                'user_id' => $user->id,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $permissionRegistrar = app(PermissionRegistrar::class);
            $permissionRegistrar->setPermissionsTeamId($account->id);

            $ownerRole = Role::query()->create([
                'name' => 'owner',
                'guard_name' => AccountPermissionSeeder::GUARD,
                'team_id' => $account->id,
            ]);

            $ownerRole->givePermissionTo(AccountPermissionSeeder::PERMISSIONS);

            $user->assignRole($ownerRole);

            $permissionRegistrar->forgetCachedPermissions();

            return [$user, $account];
        });

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json([
            'user' => new RegisteredUserResource($user),
            'account' => new RegisteredAccountResource($account),
        ], 201);
    }
}
