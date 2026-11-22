@php
    $selected = old('services', $plan->exists ? $plan->services->pluck('id')->all() : []);
    $uses = $plan->exists ? $plan->services->mapWithKeys(fn ($s) => [$s->id => $s->pivot->uses_per_cycle])->all() : [];
    $weekdays = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];
    $mode = old('usage_mode', $plan->usage_mode?->value);
@endphp
<x-layouts.admin :title="$plan->exists ? 'Editar plano' : 'Novo plano'">
    @if ($plan->exists)
        <p class="mb-5 text-sm text-stone">Versão atual: v{{ $plan->version }}. Mudanças de preço, franquia, regras ou serviços criam a próxima versão.</p>
    @endif
    <form method="POST" action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" class="grid gap-6 xl:grid-cols-3" data-loading>
        @csrf
        @if ($plan->exists) @method('PUT') @endif
        <div class="space-y-6 xl:col-span-2">
            <section class="card space-y-5">
                <h2 class="font-semibold">Comercial</h2>
                <x-form.input name="name" label="Nome do plano" :value="$plan->name" required />
                <x-form.textarea name="description" label="Descrição comercial" :value="$plan->description" rows="3" />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.input name="price" label="Preço mensal (R$)" inputmode="decimal" required :value="$plan->price_cents ? \App\Support\Money::format($plan->price_cents, false) : null" hint="Cobrança mensal em BRL." />
                    <x-form.input name="sort_order" type="number" min="0" label="Ordem" :value="$plan->sort_order ?? 0" />
                </div>
                <x-form.textarea name="benefits" label="Vantagens adicionais (uma por linha)" :value="implode(PHP_EOL, $plan->benefits ?? [])" rows="3" />
            </section>

            <section class="card space-y-5">
                <h2 class="font-semibold">Franquia de uso</h2>
                <fieldset class="grid gap-3 sm:grid-cols-3">
                    <legend class="sr-only">Modo de franquia</legend>
                    @foreach (\App\Enums\PlanUsageMode::cases() as $m)
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-black/10 p-4 has-[:checked]:border-ink has-[:checked]:bg-paper">
                            <input type="radio" name="usage_mode" value="{{ $m->value }}" class="mt-1 accent-ink" @checked($mode === $m->value)>
                            <span class="text-sm font-medium">{{ $m->label() }}</span>
                        </label>
                    @endforeach
                </fieldset>
                <div data-show-when="usage_mode=limited_global">
                    <x-form.input name="uses_per_cycle" type="number" min="1" max="100" label="Usos por ciclo mensal" :value="$plan->uses_per_cycle" class="max-w-xs" />
                </div>
                <div>
                    <p class="form-label">Serviços cobertos <span class="text-red-700">*</span></p>
                    @error('services')<p class="form-error mb-2">{{ $message }}</p>@enderror
                    <div class="divide-y divide-black/[.06] rounded-xl border border-black/10">
                        @forelse ($services as $s)
                            <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                                <label class="flex flex-1 items-center gap-3 text-sm">
                                    <input type="checkbox" name="services[]" value="{{ $s->id }}" class="form-check" @checked(in_array($s->id, $selected))>
                                    {{ $s->name }} @unless ($s->is_plan_eligible)<span class="badge-amber">não marcado como elegível</span>@endunless
                                </label>
                                <label data-show-when="usage_mode=limited_per_service" class="flex items-center gap-2 text-xs text-stone">
                                    usos/mês
                                    <input type="number" min="1" max="100" name="service_uses[{{ $s->id }}]" value="{{ old('service_uses.'.$s->id, $uses[$s->id] ?? '') }}" class="form-input min-h-9 w-20 py-1">
                                </label>
                            </div>
                        @empty
                            <p class="p-4 text-sm text-stone">Cadastre serviços primeiro.</p>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="card space-y-5">
                <h2 class="font-semibold">Limites e regras <span class="text-sm font-normal text-stone">(vazio = sem limite)</span></h2>
                <div class="grid gap-5 sm:grid-cols-3">
                    <x-form.input name="max_future_bookings" type="number" min="1" label="Agendamentos futuros simultâneos" :value="$plan->max_future_bookings" />
                    <x-form.input name="max_per_day" type="number" min="1" label="Máximo por dia" :value="$plan->max_per_day" />
                    <x-form.input name="max_per_week" type="number" min="1" label="Máximo por semana" :value="$plan->max_per_week" />
                    <x-form.input name="min_days_between_uses" type="number" min="0" label="Dias mínimos entre usos" :value="$plan->min_days_between_uses" />
                    <x-form.input name="min_advance_minutes" type="number" min="0" label="Antecedência mínima (min)" :value="$plan->min_advance_minutes" />
                    <x-form.input name="max_advance_days" type="number" min="1" label="Janela máxima (dias)" :value="$plan->max_advance_days" />
                    <x-form.input name="min_commitment_months" type="number" min="1" label="Permanência mínima (meses)" :value="$plan->min_commitment_months" />
                    <x-form.input name="grace_days" type="number" min="0" label="Carência (dias)" :value="$plan->grace_days" />
                </div>
                <div>
                    <p class="form-label">Dias permitidos <span class="font-normal text-stone">(nenhum marcado = todos)</span></p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($weekdays as $n => $l)
                            <label class="cursor-pointer rounded-full border border-black/15 px-4 py-2 text-sm has-[:checked]:border-ink has-[:checked]:bg-ink has-[:checked]:text-paper">
                                <input type="checkbox" name="allowed_weekdays[]" value="{{ $n }}" class="sr-only" @checked(in_array($n, old('allowed_weekdays', $plan->allowed_weekdays ?? [])))>{{ $l }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="grid max-w-md gap-5 sm:grid-cols-2">
                    <x-form.input name="allowed_time_start" type="time" label="Horário permitido — início" :value="$plan->allowed_time_start ? substr($plan->allowed_time_start, 0, 5) : null" />
                    <x-form.input name="allowed_time_end" type="time" label="Horário permitido — fim" :value="$plan->allowed_time_end ? substr($plan->allowed_time_end, 0, 5) : null" />
                </div>
            </section>

            <section class="card space-y-5">
                <h2 class="font-semibold">Políticas e termos</h2>
                <x-form.textarea name="cancellation_policy" label="Cancelamento/reagendamento e reposição de créditos" :value="$plan->cancellation_policy" rows="3" />
                <x-form.textarea name="no_show_policy" label="Faltas (no-show) e atrasos" :value="$plan->no_show_policy" rows="3" />
                <x-form.textarea name="guest_policy" label="Convidados (se houver)" :value="$plan->guest_policy" rows="2" />
                <x-form.textarea name="terms" label="Condições específicas do plano" :value="$plan->terms" rows="5"
                                 hint="Ficam registradas na adesão. Cancelamento impede a próxima renovação; o período pago segue válido até o fim do ciclo, salvo estorno/fraude." />
            </section>
        </div>

        <div class="space-y-6">
            <section class="card space-y-4">
                <h2 class="font-semibold">Publicação</h2>
                <x-form.checkbox name="is_published" label="Publicado no site" :checked="$plan->is_published" hint="Exige preço real maior que zero." />
                <x-form.checkbox name="is_featured" label="Destacar plano" :checked="$plan->is_featured" />
            </section>
            <section class="card space-y-3">
                <h2 class="font-semibold">Profissionais</h2>
                <x-form.checkbox name="all_barbers" label="Todos os profissionais podem atender" :checked="$plan->all_barbers" />
                <div data-show-when="all_barbers=">
                    @foreach ($barbers as $b)
                        <x-form.checkbox name="barbers[]" :value="$b->id" :label="e($b->display_name)" class="mt-3" :checked="$plan->exists && $plan->barbers->contains($b)" />
                    @endforeach
                </div>
            </section>
            <div class="flex gap-3">
                <button class="btn-dark flex-1">Salvar plano</button>
                <a href="{{ route('admin.plans.index') }}" class="btn-ghost-dark">Cancelar</a>
            </div>
        </div>
    </form>
    @if ($plan->exists)
        <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" class="mt-8" data-confirm="Arquivar o plano? Ele sai do site; assinaturas existentes não são afetadas.">
            @csrf @method('DELETE')<button class="text-sm font-semibold text-red-700 underline">Arquivar plano</button>
        </form>
    @endif
</x-layouts.admin>
