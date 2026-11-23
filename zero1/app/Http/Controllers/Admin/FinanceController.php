<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Models\CommissionLedgerEntry;
use App\Models\CommissionRule;
use App\Models\FinancialEntry;
use App\Models\Service;
use App\Models\ServiceVisit;
use App\Services\Audit\AuditLogger;
use App\Support\AgendaPeriod;
use App\Support\BusinessTime;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $period = AgendaPeriod::resolve($request->query('periodo', 'mes'), $request->query('inicio'));
        $entries = FinancialEntry::query()->whereBetween('occurred_at', [$period->fromUtc(), $period->toUtc()])
            ->when($request->filled('tipo'), fn ($q) => $q->where('type', $request->query('tipo')))
            ->latest('occurred_at')->paginate(50)->withQueryString();

        $commissions = CommissionLedgerEntry::query()->whereNull('paid_out_at')
            ->selectRaw('barber_id, SUM(amount_cents) total, COUNT(*) n')->groupBy('barber_id')->get()
            ->map(fn ($r) => ['barber' => Barber::withTrashed()->find($r->barber_id), 'total' => (int) $r->total, 'n' => $r->n]);

        return view('admin.finance.index', [
            'period' => $period,
            'entries' => $entries,
            'rules' => CommissionRule::with(['barber', 'service'])->get(),
            'commissions' => $commissions,
            'barbers' => Barber::where('status', '!=', 'inactive')->orderBy('display_name')->get(),
            'services' => Service::active()->orderBy('name')->get(),
            'categories' => FinancialEntry::EXPENSE_CATEGORIES,
            'paymentMethods' => ServiceVisit::PAYMENT_METHODS,
        ]);
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'kind' => ['required', 'in:expense,income'],
            'category' => ['required_if:kind,expense', 'nullable', Rule::in(array_keys(FinancialEntry::EXPENSE_CATEGORIES))],
            'amount' => ['required', 'regex:/^\d{1,7}([.,]\d{1,2})?$/'],
            'date' => ['required', 'date_format:Y-m-d'],
            'payment_method' => ['nullable', Rule::in(array_keys(ServiceVisit::PAYMENT_METHODS))],
            'description' => ['required', 'string', 'max:255'],
        ]);
        $e = FinancialEntry::create([
            'type' => $v['kind'], 'category' => $v['kind'] === 'expense' ? $v['category'] : 'outros',
            'amount_cents' => Money::parse($v['amount']), 'occurred_at' => CarbonImmutable::parse($v['date'].' 12:00', BusinessTime::tz())->utc(),
            'payment_method' => $v['payment_method'] ?? null, 'status' => 'paid', 'description' => $v['description'], 'created_by' => $request->user()->id,
        ]);
        $this->audit->log('finance.entry_created', $e, [], $e->toArray());

        return back()->with('status', 'Lançamento registrado.');
    }

    public function destroyEntry(FinancialEntry $entry): RedirectResponse
    {
        // Lançamentos automáticos (atendimento/pagamento) só mudam pela origem — preserva a conciliação.
        abort_if($entry->source_type !== null, 422, 'Lançamento automático não pode ser excluído manualmente.');
        $this->audit->log('finance.entry_deleted', $entry, $entry->toArray());
        $entry->delete();

        return back()->with('status', 'Lançamento excluído.');
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'barber_id' => ['nullable', 'integer', 'exists:barbers,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'percent' => ['required', 'numeric', 'between:0,100'],
            'plan_visit' => ['nullable', 'regex:/^\d{1,5}([.,]\d{1,2})?$/'],
        ]);
        $rule = CommissionRule::updateOrCreate(
            ['barber_id' => $v['barber_id'] ?? null, 'service_id' => $v['service_id'] ?? null],
            ['percent' => $v['percent'], 'plan_visit_cents' => Money::parse($v['plan_visit'] ?? '0') ?? 0]
        );
        $this->audit->log('finance.commission_rule_saved', $rule, [], $rule->toArray());

        return back()->with('status', 'Regra de comissão salva (vale para os próximos atendimentos).');
    }

    public function destroyRule(CommissionRule $rule): RedirectResponse
    {
        $this->audit->log('finance.commission_rule_deleted', $rule, $rule->toArray());
        $rule->delete();

        return back()->with('status', 'Regra removida.');
    }

    public function payOut(Request $request, Barber $barber): RedirectResponse
    {
        $total = (int) CommissionLedgerEntry::where('barber_id', $barber->id)->whereNull('paid_out_at')->sum('amount_cents');
        if ($total > 0) {
            CommissionLedgerEntry::where('barber_id', $barber->id)->whereNull('paid_out_at')->update(['paid_out_at' => now()]);
            $e = FinancialEntry::create(['type' => 'expense', 'category' => 'despesa:salarios', 'amount_cents' => $total, 'occurred_at' => now(),
                'status' => 'paid', 'description' => 'Comissões pagas — '.$barber->display_name, 'created_by' => $request->user()->id]);
            $this->audit->log('finance.commission_paid', $e, [], ['barber' => $barber->id, 'total' => $total]);
        }

        return back()->with('status', 'Comissões de '.$barber->display_name.' marcadas como pagas ('.Money::format($total).').');
    }
}
