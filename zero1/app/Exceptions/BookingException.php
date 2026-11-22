<?php

namespace App\Exceptions;

use RuntimeException;

/** Falha de regra de negócio da agenda/franquia, com mensagem pronta para o usuário. */
class BookingException extends RuntimeException
{
    /** @param array<int, array{time:string, barber_id:int|null}> $alternatives */
    public function __construct(string $message, public readonly array $alternatives = [], public readonly string $reason = 'rule')
    {
        parent::__construct($message);
    }
}
