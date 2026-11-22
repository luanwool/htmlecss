<?php

namespace Tests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Banco de teste recebe a base de produção (papéis, permissões, CMS), nunca a demo. */
    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function userWithRole(Role $role, array $attrs = []): User
    {
        return User::factory()->role($role->value)->create($attrs);
    }
}
