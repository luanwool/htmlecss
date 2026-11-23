<?php

namespace App\Services\Booking;

use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;

/** Dados de uma reserva vinda de qualquer canal (site, painel, barbeiro, balcão). */
final class BookingRequest
{
    public function __construct(
        public Customer $customer,
        public Service $service,
        public ?int $barberId,             // null = "qualquer barbeiro disponível"
        public CarbonImmutable $start,     // instante do início (qualquer fuso)
        public string $mode = 'avulso',    // avulso | plan
        public string $origin = 'online',
        public ?User $actor = null,
        public ?string $notes = null,
        public bool $staff = false,        // criada pela equipe: dispensa antecedência mínima e limites do cliente
        public bool $walkIn = false,       // encaixe/lançamento de balcão: aceita horário recém-passado
    ) {}
}
