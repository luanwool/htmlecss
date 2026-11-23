<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Referrals\ReferralProgram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Entrada pública do link de indicação: /i/<code>.
 *
 * Marca a sessão com o código (sobrevive ao cadastro e ao login na mesma sessão). Também grava
 * um cookie em texto puro de 30 dias como fallback (ex.: pessoa clica agora, se cadastra depois).
 * Nada é validado aqui — o `RegisterController` consome o código no cadastro.
 */
class ReferralLinkController extends Controller
{
    public function __invoke(Request $request, string $code, ReferralProgram $program): RedirectResponse
    {
        $rc = $program->trackClick($code);
        $target = $request->query('go', '/');
        if (! is_string($target) || ! str_starts_with($target, '/') || str_starts_with($target, '//')) {
            $target = '/';
        }
        if (! $rc) {
            return redirect($target);
        }
        $request->session()->put(ReferralProgram::COOKIE, $rc->code);
        $response = redirect($target)->with('status', 'Você recebeu uma indicação! Crie sua conta para ganhar o desconto.');
        // Cookie bruto de fallback (30 dias). Fica fora da lista de encryptCookies em bootstrap/app.php.
        $response->withCookie(cookie(ReferralProgram::COOKIE, $rc->code, 60 * 24 * 30, '/', null, false, false));

        return $response;
    }
}
