<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class AccountPermissionSeeder extends Seeder
{
    public const GUARD = 'web';

    /**
     * @var list<string>
     */
    public const PERMISSIONS = [
        'account.view',
        'account.manage',
        'account.members.manage',
        'website.view',
        'website.create',
        'website.update',
        'website.delete',
        'page.view',
        'page.create',
        'page.update',
        'page.delete',
        'page.publish',
        'navigation.view',
        'navigation.create',
        'navigation.update',
        'navigation.delete',
        'navigation.publish',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => self::GUARD,
            ]);
        }
    }
}
