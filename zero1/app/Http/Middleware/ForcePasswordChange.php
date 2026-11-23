<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enquanto o usuário tiver must_change_password = true (senha temporária gerada pelo ADM),
 * toda navegação é redirecionada para a tela de troca de senha. Exceções: o próprio formulário
 * de troca, logout, verificação de e-mail e health check.
 */
class ForcePasswordChange
{
    private const ALLOWED = ['password.change', 'password.change.update', 'logout', 'verification.*', 'health'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->must_change_password) {
            $route = $request->route()?->getName();
            foreach (self::ALLOWED as $pattern) {
                if ($route && (str_contains($pattern, '*') ? \Illuminate\Support\Str::is($pattern, $route) : $route === $pattern)) {
                    return $next($request);
                }
            }
            if ($request->expectsJson() || $request->isMethod('post') || $request->isMethod('put') || $request->isMethod('delete')) {
                abort(423, 'Defina uma nova senha antes de continuar.');
            }

            return redirect()->route('password.change')
                ->with('status', 'Esta é uma senha temporária. Defina sua nova senha para continuar.');
        }

        return $next($request);
    }
}
