<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Configurações editáveis pelo painel (chave/valor), com cache.
 * Campos sem fonte verificada ficam vazios: a interface oculta o que não existe.
 */
class SiteSettings
{
    public const CACHE_KEY = 'site_settings.all';

    /** Chaves conhecidas e tipo de cada uma (string | bool | int | text). */
    public const SCHEMA = [
        // Empresa
        'business.name' => 'string',
        'business.legal_name' => 'string',
        'business.document' => 'string', // CNPJ, se a empresa quiser exibir
        'business.street' => 'string',
        'business.number' => 'string',
        'business.district' => 'string',
        'business.city' => 'string',
        'business.state' => 'string',
        'business.postal_code' => 'string',
        'business.country' => 'string',
        'business.phone' => 'string',
        'business.whatsapp' => 'string', // somente dígitos com DDI, ex.: 5521973496399
        'business.whatsapp_message' => 'string',
        'business.email' => 'string',
        'business.instagram' => 'string',
        'business.maps_url' => 'string',
        'business.latitude' => 'string',
        'business.longitude' => 'string',
        'business.hours_confirmed' => 'bool',
        'business.hours_note' => 'string',
        // Marca
        'brand.accent_color' => 'string',
        'brand.logo_media_id' => 'int',
        'brand.tagline' => 'string',
        // SEO
        'seo.title' => 'string',
        'seo.description' => 'text',
        'seo.og_media_id' => 'int',
        'seo.indexable' => 'bool',
        // Políticas de agenda (padrões técnicos — a barbearia ajusta no painel)
        'booking.slot_interval' => 'int',
        'booking.min_advance_minutes' => 'int',
        'booking.max_advance_days' => 'int',
        'booking.cancel_min_hours' => 'int',
        'booking.late_cancel_forfeits' => 'bool',
        'booking.no_show_consumes_credit' => 'bool',
        'booking.assignment_policy' => 'string',
        'booking.max_open_per_customer' => 'int',
        'booking.unpaid_policy' => 'string',
        'booking.reminder_hours' => 'string',
        'booking.walk_in_past_hours' => 'int',
        // Marketing (só carregado após consentimento)
        'marketing.ga4_id' => 'string',
        'marketing.meta_pixel_id' => 'string',
        // Mercado Pago (configurado no painel; chaves nunca por mensagem)
        'mp.enabled' => 'bool',
        'mp.env' => 'string',          // sandbox | production
        'mp.public_key' => 'string',
        'mp.access_token' => 'string',
        'mp.webhook_secret' => 'string',
        // Programa de indicação (editado no painel do ADM)
        'referral.enabled' => 'bool',
        'referral.referrer_percent' => 'int',   // 0–100
        'referral.referred_percent' => 'int',   // 0–100
        'referral.credit_valid_days' => 'int',  // 0 = sem validade
        'referral.min_referrer_balance' => 'int', // centavos (impede créditos irrelevantes)
        'referral.max_percent_per_visit' => 'int', // teto em % por atendimento
        'referral.message' => 'text',           // texto visível para o indicado
    ];

    protected ?array $values = null;

    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        try {
            if (! Schema::hasTable('site_settings')) {
                return $this->values = [];
            }
        } catch (\Throwable) {
            return $this->values = [];
        }

        return $this->values = Cache::rememberForever(self::CACHE_KEY, function () {
            return SiteSetting::query()->pluck('value', 'key')->all();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $raw = $this->all()[$key] ?? null;
        if ($raw === null || $raw === '') {
            return $default;
        }

        return match (self::SCHEMA[$key] ?? 'string') {
            'bool' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $raw,
            default => $raw,
        };
    }

    public function set(array $values): void
    {
        foreach ($values as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            SiteSetting::query()->updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);
        }
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->values = null;
    }

    public function businessName(): string
    {
        return $this->get('business.name', 'Zero 1 Barbearia');
    }

    public function addressLine(): ?string
    {
        $street = trim(implode(', ', array_filter([$this->get('business.street'), $this->get('business.number')])));
        $parts = array_filter([
            $street ?: null,
            $this->get('business.district'),
            trim(implode(' - ', array_filter([$this->get('business.city'), $this->get('business.state')]))) ?: null,
        ]);

        return $parts ? implode(', ', $parts) : null;
    }

    public function fullAddress(): ?string
    {
        $line = $this->addressLine();
        if (! $line) {
            return null;
        }
        $cep = $this->get('business.postal_code');

        return $cep ? "{$line}, CEP {$cep}" : $line;
    }

    public function whatsappUrl(?string $message = null): ?string
    {
        $number = preg_replace('/\D/', '', (string) $this->get('business.whatsapp'));
        if (! $number) {
            return null;
        }
        $message ??= $this->get('business.whatsapp_message');

        return 'https://wa.me/'.$number.($message ? '?text='.rawurlencode($message) : '');
    }

    public function phoneHref(): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $this->get('business.phone'));

        return $digits ? 'tel:+55'.ltrim($digits, '0') : null;
    }

    public function directionsUrl(): ?string
    {
        if ($address = $this->fullAddress()) {
            return 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($this->businessName().', '.$address);
        }

        return $this->get('business.maps_url');
    }

    public function mapEmbedUrl(): ?string
    {
        $address = $this->fullAddress();

        return $address
            ? 'https://www.google.com/maps?output=embed&q='.rawurlencode($this->businessName().', '.$address)
            : null;
    }

    public function accentColor(): string
    {
        $c = (string) $this->get('brand.accent_color', '#FF4D2E');

        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : '#FF4D2E';
    }

    /** Cor de texto sobre o acento: a que tiver maior contraste WCAG (preto profundo ou branco). */
    public function accentInk(): string
    {
        $lum = function (string $hex): float {
            $c = array_map(fn ($v) => $v / 255, sscanf($hex, '#%02x%02x%02x'));
            $c = array_map(fn ($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $c);

            return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
        };
        $l = $lum($this->accentColor());
        $onDark = ($l + 0.05) / ($lum('#0b0503') + 0.05);
        $onLight = ($lum('#ffffff') + 0.05) / ($l + 0.05);

        return $onDark >= $onLight ? '#0b0503' : '#ffffff';
    }
}
