<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Barber = 'barber';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Proprietário/Admin',
            self::Manager => 'Gestor',
            self::Barber => 'Barbeiro',
            self::Customer => 'Cliente',
        };
    }

    /** Papéis com acesso ao painel gerencial (/painel). */
    public static function staff(): array
    {
        return [self::Owner->value, self::Manager->value];
    }
}
