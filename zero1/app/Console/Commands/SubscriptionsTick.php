<?php

namespace App\Console\Commands;

use App\Services\Club\SubscriptionService;
use Illuminate\Console\Command;

class SubscriptionsTick extends Command
{
    protected $signature = 'zero1:subscriptions-tick';

    protected $description = 'Renovações, atrasos e encerramentos de assinaturas (idempotente)';

    public function handle(SubscriptionService $service): int
    {
        $s = $service->tick();
        $this->info("Renovadas: {$s['renewed']} · Em atraso: {$s['past_due']} · Encerradas: {$s['ended']}");

        return self::SUCCESS;
    }
}
