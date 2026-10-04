<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Models\AccountMember;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->route('account');

        if (! $account instanceof Account) {
            abort(404);
        }

        $membership = AccountMember::query()
            ->where('account_id', $account->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($membership === null) {
            abort(404);
        }

        if ($membership->status !== 'active') {
            abort(403);
        }

        if ($account->status !== 'active') {
            abort(403);
        }

        $permissionRegistrar = app(PermissionRegistrar::class);
        $previousTeamId = $permissionRegistrar->getPermissionsTeamId();

        try {
            $permissionRegistrar->setPermissionsTeamId($account->id);

            return $next($request);
        } finally {
            $permissionRegistrar->setPermissionsTeamId($previousTeamId);
        }
    }
}
