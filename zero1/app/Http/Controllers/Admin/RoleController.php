<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission as P;
use App\Enums\Role as R;
use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/** Permissões do cargo Gestor, definidas pelo proprietário. */
class RoleController extends Controller
{
    public function edit(): View
    {
        return view('admin.users.roles', [
            'permissions' => P::cases(),
            'granted' => Role::findByName(R::Manager->value, 'web')->permissions->pluck('name')->all(),
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $v = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(array_map(fn (P $p) => $p->value, P::cases()))],
        ]);
        $role = Role::findByName(R::Manager->value, 'web');
        $before = $role->permissions->pluck('name')->sort()->values()->all();
        $role->syncPermissions($v['permissions'] ?? []);
        $audit->log('roles.manager_permissions_updated', null, ['permissions' => $before], ['permissions' => collect($v['permissions'] ?? [])->sort()->values()->all()]);

        return back()->with('status', 'Permissões do gestor atualizadas.');
    }
}
