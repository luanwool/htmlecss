<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlanUsageMode;
use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Models\MembershipPlan;
use App\Models\Service;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.plans.index', [
            'plans' => MembershipPlan::withCount('services')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.plans.form', $this->formData(new MembershipPlan(['usage_mode' => PlanUsageMode::LimitedGlobal, 'all_barbers' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $plan = DB::transaction(function () use ($data) {
            $plan = MembershipPlan::create($data['attributes'] + ['slug' => $this->uniqueSlug($data['attributes']['name'])]);
            $plan->services()->sync($data['services']);
            $plan->barbers()->sync($data['barbers']);

            return $plan;
        });
        $this->audit->log('plan.created', $plan, [], $plan->toArray());

        return redirect()->route('admin.plans.index')->with('status', 'Plano criado.');
    }

    public function edit(MembershipPlan $plan): View
    {
        return view('admin.plans.form', $this->formData($plan->load('services', 'barbers')));
    }

    public function update(Request $request, MembershipPlan $plan): RedirectResponse
    {
        $data = $this->validated($request);
        $original = $plan->getOriginal();
        $oldServices = $plan->services()->get()->mapWithKeys(fn ($s) => [$s->id => $s->pivot->uses_per_cycle])->all();

        DB::transaction(function () use ($plan, $data, $oldServices) {
            $plan->fill($data['attributes']);
            // Mudança na lista de serviços/franquias também é alteração comercial.
            $newServices = array_map(fn ($pivot) => $pivot['uses_per_cycle'], $data['services']);
            ksort($oldServices);
            ksort($newServices);
            if ($oldServices !== $newServices && ! $plan->isDirty(MembershipPlan::COMMERCIAL_FIELDS)) {
                $plan->version++;
            }
            $plan->save();
            $plan->services()->sync($data['services']);
            $plan->barbers()->sync($data['barbers']);
        });
        $this->audit->logChanges('plan.updated', $plan, $original);

        return redirect()->route('admin.plans.index')->with('status',
            'Plano atualizado. Assinaturas já vendidas mantêm as condições contratadas (snapshot da adesão).');
    }

    public function destroy(MembershipPlan $plan): RedirectResponse
    {
        $plan->update(['is_published' => false]);
        $plan->delete();
        $this->audit->log('plan.deleted', $plan);

        return redirect()->route('admin.plans.index')->with('status', 'Plano arquivado.');
    }

    private function formData(MembershipPlan $plan): array
    {
        return [
            'plan' => $plan,
            'services' => Service::active()->orderBy('name')->get(),
            'barbers' => Barber::where('status', '!=', 'inactive')->orderBy('display_name')->get(),
        ];
    }

    private function validated(Request $request): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'regex:/^\d{1,6}([.,]\d{1,2})?$/'],
            'usage_mode' => ['required', Rule::enum(PlanUsageMode::class)],
            'uses_per_cycle' => ['nullable', 'integer', 'min:1', 'max:100', Rule::requiredIf($request->input('usage_mode') === PlanUsageMode::LimitedGlobal->value)],
            'max_future_bookings' => ['nullable', 'integer', 'min:1', 'max:50'],
            'max_per_day' => ['nullable', 'integer', 'min:1', 'max:10'],
            'max_per_week' => ['nullable', 'integer', 'min:1', 'max:30'],
            'min_days_between_uses' => ['nullable', 'integer', 'min:0', 'max:60'],
            'min_advance_minutes' => ['nullable', 'integer', 'min:0', 'max:43200'],
            'max_advance_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'allowed_weekdays' => ['nullable', 'array'],
            'allowed_weekdays.*' => ['integer', 'between:1,7'],
            'allowed_time_start' => ['nullable', 'date_format:H:i', 'required_with:allowed_time_end'],
            'allowed_time_end' => ['nullable', 'date_format:H:i', 'after:allowed_time_start', 'required_with:allowed_time_start'],
            'min_commitment_months' => ['nullable', 'integer', 'min:1', 'max:24'],
            'grace_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'guest_policy' => ['nullable', 'string', 'max:2000'],
            'no_show_policy' => ['nullable', 'string', 'max:2000'],
            'cancellation_policy' => ['nullable', 'string', 'max:2000'],
            'benefits' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:10000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['integer', Rule::exists('services', 'id')],
            'service_uses' => ['nullable', 'array'],
            'service_uses.*' => ['nullable', 'integer', 'min:1', 'max:100'],
            'barbers' => ['nullable', 'array'],
            'barbers.*' => ['integer', Rule::exists('barbers', 'id')],
        ], [
            'services.required' => 'Selecione ao menos um serviço coberto pelo plano.',
            'uses_per_cycle.required' => 'Informe a quantidade de usos por ciclo.',
        ]);

        $priceCents = Money::parse($v['price']);
        if ($request->boolean('is_published') && $priceCents <= 0) {
            throw ValidationException::withMessages(['price' => 'Um plano publicado precisa de preço real maior que zero.']);
        }

        $services = [];
        foreach ($v['services'] as $id) {
            $uses = $v['service_uses'][$id] ?? null;
            if ($v['usage_mode'] === PlanUsageMode::LimitedPerService->value && ! $uses) {
                throw ValidationException::withMessages(['services' => 'Na franquia por serviço, informe os usos de cada serviço selecionado.']);
            }
            $services[(int) $id] = ['uses_per_cycle' => $v['usage_mode'] === PlanUsageMode::LimitedPerService->value ? (int) $uses : null];
        }

        $allBarbers = $request->boolean('all_barbers');

        return [
            'attributes' => [
                'name' => $v['name'],
                'description' => $v['description'] ?? null,
                'price_cents' => $priceCents,
                'is_featured' => $request->boolean('is_featured'),
                'is_published' => $request->boolean('is_published'),
                'usage_mode' => $v['usage_mode'],
                'uses_per_cycle' => $v['usage_mode'] === PlanUsageMode::LimitedGlobal->value ? (int) $v['uses_per_cycle'] : null,
                'max_future_bookings' => $v['max_future_bookings'] ?? null,
                'max_per_day' => $v['max_per_day'] ?? null,
                'max_per_week' => $v['max_per_week'] ?? null,
                'min_days_between_uses' => $v['min_days_between_uses'] ?? null,
                'min_advance_minutes' => $v['min_advance_minutes'] ?? null,
                'max_advance_days' => $v['max_advance_days'] ?? null,
                'allowed_weekdays' => ! empty($v['allowed_weekdays']) ? array_map('intval', $v['allowed_weekdays']) : null,
                'allowed_time_start' => isset($v['allowed_time_start']) ? $v['allowed_time_start'].':00' : null,
                'allowed_time_end' => isset($v['allowed_time_end']) ? $v['allowed_time_end'].':00' : null,
                'all_barbers' => $allBarbers,
                'min_commitment_months' => $v['min_commitment_months'] ?? null,
                'grace_days' => $v['grace_days'] ?? null,
                'guest_policy' => $v['guest_policy'] ?? null,
                'no_show_policy' => $v['no_show_policy'] ?? null,
                'cancellation_policy' => $v['cancellation_policy'] ?? null,
                'benefits' => collect(preg_split('/\r?\n/', (string) ($v['benefits'] ?? '')))->map(fn ($l) => trim($l))->filter()->values()->all() ?: null,
                'terms' => $v['terms'] ?? null,
                'sort_order' => (int) ($v['sort_order'] ?? 0),
            ],
            'services' => $services,
            'barbers' => $allBarbers ? [] : ($v['barbers'] ?? []),
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'plano';
        $slug = $base;
        $i = 2;
        while (MembershipPlan::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
