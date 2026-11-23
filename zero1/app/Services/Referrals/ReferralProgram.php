<?php

namespace App\Services\Referrals;

use App\Enums\ReferralStatus;
use App\Models\Customer;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\ReferralCredit;
use App\Models\ReferralCreditUse;
use App\Models\ServiceVisit;
use App\Services\Audit\AuditLogger;
use App\Support\SiteSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Programa "Indique e ganhe". Fluxo:
 *  1. Cliente logado gera (ou pega) o próprio código.
 *  2. Visitante com link /i/CODIGO recebe um cookie; ao se cadastrar, vira "indicado" daquele cliente.
 *  3. Ao concluir o primeiro atendimento avulso, o programa emite créditos para ambos
 *     (% configurado sobre o valor final), com validade opcional.
 *  4. O painel do barbeiro aplica o crédito disponível ao concluir o próximo atendimento avulso.
 *
 * Nada acontece se o programa estiver desligado em Configurações.
 */
class ReferralProgram
{
    public const COOKIE = 'z1_ref'; // chave também usada em session()

    public function __construct(private SiteSettings $settings, private AuditLogger $audit) {}

    public function isEnabled(): bool
    {
        return (bool) $this->settings->get('referral.enabled', false);
    }

    /** Configuração vigente usada tanto na UI quanto no cálculo. */
    public function config(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'referrer_percent' => (int) $this->settings->get('referral.referrer_percent', 10),
            'referred_percent' => (int) $this->settings->get('referral.referred_percent', 30),
            'credit_valid_days' => (int) $this->settings->get('referral.credit_valid_days', 90),
            'min_referrer_balance' => (int) $this->settings->get('referral.min_referrer_balance', 0),
            'max_percent_per_visit' => (int) $this->settings->get('referral.max_percent_per_visit', 50),
            'message' => $this->settings->get('referral.message'),
        ];
    }

    /** Devolve (ou cria) o código do cliente. */
    public function codeFor(Customer $customer): ReferralCode
    {
        return ReferralCode::firstOrCreate(
            ['customer_id' => $customer->id],
            ['code' => $this->generateCode($customer), 'is_active' => true]
        );
    }

    private function generateCode(Customer $customer): string
    {
        $base = Str::of($customer->name)->ascii()->upper()->replaceMatches('/[^A-Z]/', '')->substr(0, 4);
        if (strlen($base) < 2) {
            $base = 'ZERO1';
        }
        for ($i = 0; $i < 6; $i++) {
            $code = $base.strtoupper(Str::random(4));
            if (! ReferralCode::where('code', $code)->exists()) {
                return $code;
            }
        }

        return strtoupper(Str::random(12));
    }

    /** Resolve o código vindo de /i/<code>: valida, incrementa cliques e retorna o indicador. */
    public function trackClick(string $code): ?ReferralCode
    {
        $rc = ReferralCode::where('code', strtoupper($code))->where('is_active', true)->first();
        if ($rc) {
            $rc->increment('visits_count');
        }

        return $rc;
    }

    /** Vincula um cliente recém-cadastrado ao indicador do cookie, se ambos existirem e o programa estiver ativo. */
    public function linkFromCookie(Customer $customer, ?string $cookieCode): ?Referral
    {
        if (! $this->isEnabled() || ! $cookieCode) {
            return null;
        }
        $rc = ReferralCode::where('code', strtoupper($cookieCode))->where('is_active', true)->first();
        if (! $rc || $rc->customer_id === $customer->id || $customer->referralReceived) {
            return null;
        }

        return DB::transaction(function () use ($rc, $customer) {
            $customer->update(['referred_by_customer_id' => $rc->customer_id, 'referral_cookie_code' => $rc->code]);
            $rc->increment('signups_count');
            $ref = Referral::create([
                'referrer_customer_id' => $rc->customer_id, 'referred_customer_id' => $customer->id,
                'code' => $rc->code, 'status' => ReferralStatus::Invited, 'snapshot' => $this->config(),
            ]);
            $this->audit->log('referral.invited', $ref);

            return $ref;
        });
    }

    /**
     * Executado após um atendimento concluído. Se for o primeiro do indicado e as condições baterem,
     * emite os créditos para ambos.
     */
    public function onVisitCompleted(ServiceVisit $visit): void
    {
        if (! $this->isEnabled()) {
            return;
        }
        $customer = $visit->customer;
        $ref = $customer?->referralReceived;
        logger()->info("onVisit", ["visit" => $visit->id, "ref" => $ref?->id, "status" => $ref?->status?->value]); if (! $ref || $ref->status !== ReferralStatus::Invited) {
            return;
        }
        if ($visit->mode !== 'avulso' || $visit->final_cents <= 0) {
            return; // só atendimentos pagos qualificam
        }

        DB::transaction(function () use ($ref, $visit) {
            $cfg = $ref->snapshot;
            $amountReferred = (int) floor($visit->final_cents * ($cfg['referred_percent'] ?? 0) / 100);
            $amountReferrer = (int) floor($visit->final_cents * ($cfg['referrer_percent'] ?? 0) / 100);
            $expiresOn = ($cfg['credit_valid_days'] ?? 0) > 0 ? now()->addDays((int) $cfg['credit_valid_days'])->toDateString() : null;

            if ($amountReferred > 0) {
                ReferralCredit::create(['customer_id' => $ref->referred_customer_id, 'referral_id' => $ref->id,
                    'role' => 'referred', 'amount_cents' => $amountReferred, 'expires_on' => $expiresOn,
                    'note' => 'Bônus por aceitar a indicação']);
            }
            if ($amountReferrer >= (int) ($cfg['min_referrer_balance'] ?? 0) && $amountReferrer > 0) {
                ReferralCredit::create(['customer_id' => $ref->referrer_customer_id, 'referral_id' => $ref->id,
                    'role' => 'referrer', 'amount_cents' => $amountReferrer, 'expires_on' => $expiresOn,
                    'note' => 'Indicação concluída']);
            }
            $ref->update(['status' => ReferralStatus::Rewarded, 'first_visit_at' => $visit->performed_at, 'rewarded_at' => now()]);
            $this->audit->log('referral.rewarded', $ref, [], ['visit' => $visit->id,
                'referred_cents' => $amountReferred, 'referrer_cents' => $amountReferrer]);
        });
    }

    /** Saldo disponível (soma dos créditos ativos) em centavos. */
    public function balance(Customer $customer): int
    {
        return (int) ReferralCredit::query()->where('customer_id', $customer->id)->active()
            ->get()->sum(fn (ReferralCredit $c) => $c->balance());
    }

    /**
     * Aplica o saldo disponível numa cobrança de atendimento avulso. Respeita o teto por atendimento
     * (max_percent_per_visit) e o saldo disponível. Idempotente por service_visit_id.
     */
    public function applyToVisit(ServiceVisit $visit): int
    {
        if ($visit->mode !== 'avulso' || $visit->final_cents <= 0 || ! $this->isEnabled()) {
            return 0;
        }
        if (ReferralCreditUse::query()->where('service_visit_id', $visit->id)->exists()) {
            return 0;
        }
        $cfg = $this->config();
        $cap = (int) floor($visit->final_cents * $cfg['max_percent_per_visit'] / 100);
        $remaining = min($cap, $visit->final_cents);
        if ($remaining <= 0) {
            return 0;
        }

        $applied = 0;
        DB::transaction(function () use ($visit, &$applied, $remaining) {
            $credits = ReferralCredit::query()->where('customer_id', $visit->customer_id)->active()
                ->orderBy('expires_on')->orderBy('id')->lockForUpdate()->get();
            foreach ($credits as $c) {
                if ($remaining <= 0) {
                    break;
                }
                $use = min($c->balance(), $remaining);
                if ($use <= 0) {
                    continue;
                }
                $c->increment('used_cents', $use);
                ReferralCreditUse::create(['referral_credit_id' => $c->id, 'service_visit_id' => $visit->id, 'amount_cents' => $use]);
                $applied += $use;
                $remaining -= $use;
            }
            if ($applied > 0) {
                $visit->update([
                    'discount_cents' => $visit->discount_cents + $applied,
                    'final_cents' => max(0, $visit->final_cents - $applied),
                ]);
            }
        });

        return $applied;
    }
}
