<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q'));
        $customers = Customer::query()->with('user:id,email_verified_at,last_login_at')
            ->when($term !== '', function ($q) use ($term) {
                $digits = preg_replace('/\D/', '', $term);
                $q->where(function ($w) use ($term, $digits) {
                    $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%");
                    if ($digits !== '') {
                        $w->orWhere('phone', 'like', "%{$digits}%")->orWhere('cpf', 'like', "%{$digits}%");
                    }
                });
            })
            ->latest()->paginate(30)->withQueryString();

        return view('admin.customers.index', ['customers' => $customers]);
    }

    public function show(Customer $customer): View
    {
        return view('admin.customers.show', ['customer' => $customer->load('user')]);
    }

    /**
     * Gera uma senha temporária para um cliente que perdeu o acesso.
     *
     * O admin informa o cliente pessoalmente/WhatsApp; no próximo login o middleware
     * `ForcePasswordChange` leva à tela de troca. A senha só é exibida uma vez, na
     * mensagem flash — nunca gravada em claro.
     */
    public function resetPassword(Customer $customer, AuditLogger $audit): RedirectResponse
    {
        abort_unless($customer->user_id, 404, 'Este cliente não tem login na área do cliente.');
        $temp = Str::password(10, letters: true, numbers: true, symbols: false);
        $customer->user()->update([
            'password' => $temp,
            'must_change_password' => true,
            'password_set_at' => now(),
        ]);
        $audit->log('auth.temporary_password_issued', $customer->user, ['customer_id' => $customer->id]);

        return redirect()->route('admin.customers.show', $customer)
            ->with('temp_password', $temp)
            ->with('status', 'Senha temporária gerada. Informe ao cliente para usá-la apenas no próximo login.');
    }
}
