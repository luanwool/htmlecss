<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $services = Service::query()->with('category')->withCount('barbers')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->q.'%'))
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')->orderBy('name')->paginate(30)->withQueryString();

        return view('admin.services.index', [
            'services' => $services,
            'categories' => ServiceCategory::withCount('services')->orderBy('sort_order')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.services.form', $this->formData(new Service(['is_active' => true, 'is_public' => true, 'is_bookable_standalone' => true, 'buffer_minutes' => 0])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $service = DB::transaction(function () use ($data) {
            $service = Service::create($data['attributes']);
            $service->barbers()->sync($data['barbers']);

            return $service;
        });
        $this->audit->log('service.created', $service, [], $service->toArray());

        return redirect()->route('admin.services.index')->with('status', 'Serviço criado.');
    }

    public function edit(Service $service): View
    {
        return view('admin.services.form', $this->formData($service->load('barbers')));
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $data = $this->validated($request, $service);
        $original = $service->getOriginal();
        DB::transaction(function () use ($service, $data) {
            $service->update($data['attributes']);
            $service->barbers()->sync($data['barbers']);
        });
        $this->audit->logChanges('service.updated', $service, $original);

        return redirect()->route('admin.services.index')->with('status', 'Serviço atualizado.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        // Soft delete: históricos que referenciam o serviço continuam íntegros.
        $service->delete();
        $this->audit->log('service.deleted', $service);

        return redirect()->route('admin.services.index')->with('status', 'Serviço removido.');
    }

    private function formData(Service $service): array
    {
        return [
            'service' => $service,
            'categories' => ServiceCategory::orderBy('sort_order')->get(),
            'barbers' => Barber::where('status', '!=', 'inactive')->orderBy('display_name')->get(),
        ];
    }

    private function validated(Request $request, ?Service $service = null): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'service_category_id' => ['nullable', 'exists:service_categories,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['nullable', 'regex:/^\d{1,6}([.,]\d{1,2})?$/'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
            'buffer_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'min_advance_minutes' => ['nullable', 'integer', 'min:0', 'max:43200'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'barbers' => ['array'],
            'barbers.*' => ['integer', Rule::exists('barbers', 'id')],
        ], ['price.regex' => 'Informe o preço no formato 45,00.']);

        $attributes = [
            'name' => $v['name'],
            'slug' => $service?->slug ?? $this->uniqueSlug($v['name']),
            'service_category_id' => $v['service_category_id'] ?? null,
            'description' => $v['description'] ?? null,
            'price_cents' => Money::parse($v['price'] ?? null),
            'price_is_from' => $request->boolean('price_is_from'),
            'duration_minutes' => (int) $v['duration_minutes'],
            'buffer_minutes' => (int) ($v['buffer_minutes'] ?? 0),
            'min_advance_minutes' => $v['min_advance_minutes'] ?? null,
            'is_public' => $request->boolean('is_public'),
            'is_bookable_standalone' => $request->boolean('is_bookable_standalone'),
            'is_plan_eligible' => $request->boolean('is_plan_eligible'),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($v['sort_order'] ?? 0),
        ];

        return ['attributes' => $attributes, 'barbers' => $v['barbers'] ?? []];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'servico';
        $slug = $base;
        $i = 2;
        while (Service::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
