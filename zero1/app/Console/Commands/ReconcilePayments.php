<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\Club\SubscriptionService;
use Illuminate\Console\Command;

/** Recupera webhooks perdidos consultando o gateway. */
class ReconcilePayments extends Command
{
    protected $signature = 'zero1:reconcile-payments {--all : inclui assinaturas encerradas há até 60 dias}';

    protected $description = 'Concilia assinaturas e pagamentos com o gateway';

    public function handle(SubscriptionService $service): int
    {
        $query = Subscription::query()->whereNotNull('gateway_subscription_id');
        $this->option('all')
            ? $query->where('updated_at', '>', now()->subDays(60))
            : $query->live();

        $n = 0;
        $query->each(function (Subscription $s) use ($service, &$n) {
            rescue(fn () => $service->reconcile($s), report: true);
            $n++;
        });
        $this->info("{$n} assinatura(s) conciliada(s).");

        return self::SUCCESS;
    }
}
