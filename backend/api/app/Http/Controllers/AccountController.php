<?php

namespace App\Http\Controllers;

use App\Http\Resources\Account\AccessibleAccountResource;
use App\Models\Account;
use App\Models\User;
use Database\Seeders\AccountPermissionSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $accounts = Account::query()
            ->accessibleTo($user)
            ->orderBy('id')
            ->get();

        $roleNamesByAccountId = $this->roleNamesByAccountId($user, $accounts->pluck('id'));

        $accounts->each(function (Account $account) use ($roleNamesByAccountId): void {
            $account->setAttribute(
                'discovery_role',
                $roleNamesByAccountId->get($account->id),
            );
        });

        return AccessibleAccountResource::collection($accounts)->response();
    }

    /**
     * @param  Collection<int, int|string>  $accountIds
     * @return Collection<int, string|null>
     */
    private function roleNamesByAccountId(User $user, Collection $accountIds): Collection
    {
        if ($accountIds->isEmpty()) {
            return collect();
        }

        $rows = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->where('model_has_roles.model_id', $user->id)
            ->whereIn('model_has_roles.team_id', $accountIds->all())
            ->where('roles.guard_name', AccountPermissionSeeder::GUARD)
            ->orderBy('roles.id')
            ->get([
                'model_has_roles.team_id as account_id',
                'roles.name as role_name',
            ]);

        return $rows
            ->groupBy('account_id')
            ->mapWithKeys(function (Collection $group, int|string $accountId): array {
                $roleNames = $group->pluck('role_name')->unique()->values();

                $role = $roleNames->count() === 1 ? $roleNames->first() : null;

                return [(int) $accountId => $role];
            });
    }
}
