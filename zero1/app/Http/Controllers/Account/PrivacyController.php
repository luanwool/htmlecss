<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\DataRequest;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Direitos do titular (LGPD art. 18): cópia imediata e solicitações tratadas pela barbearia. */
class PrivacyController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.privacy', [
            'requests' => DataRequest::query()->where('user_id', $request->user()->id)->latest()->get(),
        ]);
    }

    public function export(Request $request, AuditLogger $audit): JsonResponse
    {
        $user = $request->user()->load('customer');
        $audit->log('privacy.export', $user);

        $payload = [
            'gerado_em' => now()->toIso8601String(),
            'conta' => $user->only(['name', 'email', 'phone', 'email_verified_at', 'created_at', 'last_login_at']),
            'cliente' => $user->customer?->only(['name', 'email', 'phone', 'origin', 'terms_accepted_at', 'privacy_accepted_at', 'marketing_consent_at', 'created_at']),
            'solicitacoes_lgpd' => DataRequest::query()->where('user_id', $user->id)->get(['type', 'status', 'created_at', 'handled_at']),
            // Agendamentos, assinaturas e pagamentos serão incluídos conforme os módulos forem ativados.
        ];

        return response()->json($payload, 200, [
            'Content-Disposition' => 'attachment; filename="meus-dados-zero1.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function request(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(DataRequest::TYPES))],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);
        $dr = DataRequest::create($data + ['user_id' => $request->user()->id]);
        $audit->log('privacy.request_created', $dr, [], $data);

        return back()->with('status', 'Solicitação registrada. A barbearia responderá pelo seu e-mail.');
    }
}
