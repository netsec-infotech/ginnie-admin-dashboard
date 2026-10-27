<?php

namespace Database\Seeders;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\SellerPermissionEnum;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SeedSellerPermissionsForAmitVermaSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = SellerPermissionEnum::values();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => GuardNameEnum::SELLER(),
            ]);
        }

        $sellerRole = Role::firstOrCreate([
            'name' => DefaultSystemRolesEnum::SELLER(),
            'guard_name' => GuardNameEnum::SELLER(),
        ]);

        $sellerRole->syncPermissions($permissions);

        $user = User::where('email', 'infinitie.munaf@gmail.com')->firstOrFail();

        $user->assignRole($sellerRole);
        $user->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
