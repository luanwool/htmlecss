<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Cria o primeiro proprietário em produção sem senha padrão no código ou no seed.
 * Uso: php artisan zero1:create-owner
 */
class CreateOwner extends Command
{
    protected $signature = 'zero1:create-owner {--name=} {--email=}';

    protected $description = 'Cria (ou promove) um usuário Proprietário/Admin';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Nome');
        $email = strtolower((string) ($this->option('email') ?: $this->ask('E-mail')));
        $existing = User::where('email', $email)->first();

        if ($existing) {
            if (! $this->confirm("Usuário {$email} já existe. Promover a proprietário?")) {
                return self::FAILURE;
            }
            $existing->syncRoles([Role::Owner->value]);
            $this->info('Usuário promovido a proprietário.');

            return self::SUCCESS;
        }

        $password = $this->secret('Senha (mín. 8, letras e números)');
        $v = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'max:120'],
            'email' => ['required', 'email'],
            'password' => ['required', Password::min(8)->letters()->numbers()],
        ]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }

        $user = User::create(['name' => $name, 'email' => $email, 'password' => $password, 'is_active' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole(Role::Owner->value);
        $this->info("Proprietário {$email} criado.");

        return self::SUCCESS;
    }
}
