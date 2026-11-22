<?php

namespace Database\Seeders;

use App\Enums\Permission as P;
use App\Enums\Role as R;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (P::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (R::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        // Gestor: conjunto padrão apenas na criação; depois o proprietário ajusta pelo painel.
        $manager = Role::findByName(R::Manager->value, 'web');
        if ($manager->permissions()->count() === 0) {
            $manager->syncPermissions(array_map(fn (P $p) => $p->value, P::managerDefaults()));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
