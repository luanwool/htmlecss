<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BarberStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Models\Service;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Media\MediaUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BarberController extends Controller
{
    public function __construct(private AuditLogger $audit, private MediaUploader $uploader) {}

    public function index(): View
    {
        return view('admin.barbers.index', [
            'barbers' => Barber::with(['photo', 'user'])->withCount('services')->orderBy('sort_order')->orderBy('display_name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.barbers.form', $this->formData(new Barber(['status' => BarberStatus::Active, 'is_public' => true, 'accepts_any_assignment' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $barber = DB::transaction(function () use ($request) {
            $data = $this->validated($request);
            $barber = Barber::create($data['attributes'] + ['slug' => $this->uniqueSlug($data['attributes']['display_name'])]);
            $barber->services()->sync($data['services']);
            $this->handlePhoto($request, $barber);

            return $barber;
        });
        $this->audit->log('barber.created', $barber, [], $barber->toArray());

        return redirect()->route('admin.barbers.index')->with('status', 'Profissional cadastrado.');
    }

    public function edit(Barber $barber): View
    {
        return view('admin.barbers.form', $this->formData($barber->load('services', 'photo')));
    }

    public function update(Request $request, Barber $barber): RedirectResponse
    {
        $original = $barber->getOriginal();
        DB::transaction(function () use ($request, $barber) {
            $data = $this->validated($request, $barber);
            $barber->update($data['attributes']);
            $barber->services()->sync($data['services']);
            $this->handlePhoto($request, $barber);
        });
        $this->audit->logChanges('barber.updated', $barber, $original);

        return redirect()->route('admin.barbers.index')->with('status', 'Profissional atualizado.');
    }

    public function destroy(Barber $barber): RedirectResponse
    {
        // Não apaga histórico: desativa e oculta (soft delete).
        $barber->update(['status' => BarberStatus::Inactive, 'is_public' => false]);
        $barber->delete();
        $this->audit->log('barber.deleted', $barber);

        return redirect()->route('admin.barbers.index')->with('status', 'Profissional removido do cadastro ativo.');
    }

    private function formData(Barber $barber): array
    {
        $linked = Barber::whereNotNull('user_id')->where('id', '!=', $barber->id ?? 0)->pluck('user_id');

        return [
            'barber' => $barber,
            'services' => Service::active()->orderBy('name')->get(),
            'users' => User::role(Role::Barber->value)->whereNotIn('id', $linked)->orderBy('name')->get(),
        ];
    }

    private function validated(Request $request, ?Barber $barber = null): array
    {
        $v = $request->validate([
            'display_name' => ['required', 'string', 'max:80'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'specialties' => ['nullable', 'string', 'max:300'],
            'status' => ['required', Rule::enum(BarberStatus::class)],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id'), Rule::unique('barbers', 'user_id')->ignore($barber?->id)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'services' => ['array'],
            'services.*' => ['integer', Rule::exists('services', 'id')],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('zero1.media.max_upload_kb'), 'dimensions:min_width=300,min_height=300'],
            'photo_alt' => ['nullable', 'string', 'max:150'],
        ]);

        return [
            'attributes' => [
                'display_name' => $v['display_name'],
                'bio' => $v['bio'] ?? null,
                'specialties' => collect(explode(',', (string) ($v['specialties'] ?? '')))->map(fn ($s) => trim($s))->filter()->values()->all() ?: null,
                'status' => $v['status'],
                'user_id' => $v['user_id'] ?? null,
                'is_public' => $request->boolean('is_public'),
                'accepts_any_assignment' => $request->boolean('accepts_any_assignment'),
                'sort_order' => (int) ($v['sort_order'] ?? 0),
            ],
            'services' => $v['services'] ?? [],
        ];
    }

    private function handlePhoto(Request $request, Barber $barber): void
    {
        if ($request->boolean('remove_photo') && $barber->photo) {
            $old = $barber->photo;
            $barber->update(['photo_media_id' => null]);
            $this->uploader->delete($old);
        }
        if ($request->hasFile('photo')) {
            $old = $barber->photo;
            $media = $this->uploader->store($request->file('photo'), 'barber', [
                'alt_text' => $request->input('photo_alt') ?: 'Foto de '.$barber->display_name,
            ]);
            $barber->update(['photo_media_id' => $media->id]);
            if ($old) {
                $this->uploader->delete($old);
            }
        } elseif ($barber->photo && $request->filled('photo_alt')) {
            $barber->photo->update(['alt_text' => $request->input('photo_alt')]);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'profissional';
        $slug = $base;
        $i = 2;
        while (Barber::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
