<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Equipe e acessos (somente proprietário). */
class UserController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $role = $request->query('papel');
        $users = User::with('roles')
            ->when($role && Role::tryFrom($role), fn ($q) => $q->role($role))
            ->when(! $role, fn ($q) => $q->role([Role::Owner->value, Role::Manager->value, Role::Barber->value]))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->q.'%')->orWhere('email', 'like', '%'.$request->q.'%')))
            ->orderBy('name')->paginate(30)->withQueryString();

        return view('admin.users.index', ['users' => $users, 'roles' => Role::cases()]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User(['is_active' => true]), 'roles' => Role::cases(), 'currentRole' => Role::Barber->value]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $this->validated($request);
        $user = User::create($v + ['password' => Str::password(32)]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->syncRoles([$request->input('role')]);

        // O próprio usuário define a senha pelo link — o proprietário nunca conhece a senha.
        PasswordBroker::sendResetLink(['email' => $user->email]);
        $this->audit->log('user.created', $user, [], ['email' => $user->email, 'role' => $request->input('role')]);

        return redirect()->route('admin.users.index')->with('status', 'Usuário criado. Enviamos um link para ele definir a senha.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', ['user' => $user, 'roles' => Role::cases(), 'currentRole' => $user->roles->first()?->name]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $v = $this->validated($request, $user);
        $newRole = $request->input('role');
        $oldRole = $user->roles->first()?->name;

        $removingOwner = $oldRole === Role::Owner->value && ($newRole !== Role::Owner->value || ! $v['is_active']);
        if ($removingOwner && User::role(Role::Owner->value)->where('is_active', true)->count() <= 1) {
            throw ValidationException::withMessages(['role' => 'É necessário manter ao menos um proprietário ativo.']);
        }
        if ($user->is($request->user()) && ! $v['is_active']) {
            throw ValidationException::withMessages(['is_active' => 'Você não pode desativar o próprio acesso.']);
        }

        $original = $user->getOriginal();
        $user->update($v);
        $user->syncRoles([$newRole]);
        $this->audit->logChanges('user.updated', $user, $original);
        if ($oldRole !== $newRole) {
            $this->audit->log('user.role_changed', $user, ['role' => $oldRole], ['role' => $newRole]);
        }

        return redirect()->route('admin.users.index')->with('status', 'Usuário atualizado.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user()) || $user->isOwner()) {
            throw ValidationException::withMessages(['user' => 'Não é possível remover este usuário.']);
        }
        $user->update(['is_active' => false]);
        $user->delete();
        $this->audit->log('user.deleted', $user);

        return back()->with('status', 'Acesso removido.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'lowercase', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        return [
            'name' => $v['name'],
            'email' => $v['email'],
            'phone' => isset($v['phone']) ? preg_replace('/\D/', '', $v['phone']) : null,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
