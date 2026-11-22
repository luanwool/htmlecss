<?php

namespace App\Http\Middleware;

use App\Support\SiteSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self)');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if (config('zero1.security.hsts') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (config('zero1.security.csp') && ! Vite::isRunningHot()) {
            $response->headers->set('Content-Security-Policy', $this->csp());
        }

        return $response;
    }

    private function csp(): string
    {
        $nonce = Vite::cspNonce();
        $settings = app(SiteSettings::class);
        $script = ["'self'", "'nonce-{$nonce}'"];
        $connect = ["'self'"];
        $img = ["'self'", 'data:', 'blob:'];

        if ($settings->get('marketing.ga4_id')) {
            $script[] = 'https://www.googletagmanager.com';
            $connect[] = 'https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com';
            $img[] = 'https://*.google-analytics.com https://*.googletagmanager.com';
        }
        if ($settings->get('marketing.meta_pixel_id')) {
            $script[] = 'https://connect.facebook.net';
            $connect[] = 'https://www.facebook.com';
            $img[] = 'https://www.facebook.com';
        }
        // Mercado Pago (checkout oficial) será liberado aqui na Fase 3.

        return implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $script),
            "style-src 'self' 'unsafe-inline'",
            'img-src '.implode(' ', $img),
            "font-src 'self' data:",
            'connect-src '.implode(' ', $connect),
            'frame-src https://www.google.com https://maps.google.com',
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);
    }
}
