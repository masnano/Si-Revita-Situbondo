<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RpdPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $actions = ['view', 'create', 'update', 'delete', 'export', 'post'];
        $rootRole = Role::where('name', 'root')->first();
        $adminRole = Role::where('name', 'admin')->first();
        $userRole = Role::where('name', 'user')->first();

        foreach ($actions as $action) {
            $perm = Permission::firstOrCreate(
                ['name' => "rpd.{$action}"],
                [
                    'module' => 'rpd',
                    'action' => $action,
                    'display_name' => ucfirst($action) . ' Rpd',
                    'description' => "Izin untuk {$action} pada modul rpd (pecah bahan BKU)",
                ]
            );

            if ($rootRole) {
                $rootRole->permissions()->syncWithoutDetaching([$perm->id]);
            }
            if ($adminRole) {
                $adminRole->permissions()->syncWithoutDetaching([$perm->id]);
            }
            if ($userRole && in_array($action, ['view', 'export'])) {
                $userRole->permissions()->syncWithoutDetaching([$perm->id]);
            }
        }
    }
}

