@php $s = fn ($k) => $settings->get($k); @endphp
<x-layouts.admin title="Configurações">
    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="max-w-5xl space-y-6" data-loading>
        @csrf @method('PUT')

        <section id="empresa" class="card scroll-mt-24">
            <h2 class="font-semibold">Empresa e contato</h2>
            <p class="mt-1 text-sm text-stone">Endereço e telefone foram pré-preenchidos com dados públicos para conferência dos proprietários.</p>
            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-6">
                <x-form.input name="s[business_name]" label="Nome" :value="$s('business.name')" required class="lg:col-span-3" />
                <x-form.input name="s[business_legal_name]" label="Razão social" :value="$s('business.legal_name')" class="lg:col-span-3" />
                <x-form.input name="s[business_document]" label="CNPJ (opcional, exibido no rodapé)" :value="$s('business.document')" class="lg:col-span-2" />
                <x-form.input name="s[business_street]" label="Logradouro" :value="$s('business.street')" class="lg:col-span-3" />
                <x-form.input name="s[business_number]" label="Número" :value="$s('business.number')" class="lg:col-span-1" />
                <x-form.input name="s[business_district]" label="Bairro" :value="$s('business.district')" class="lg:col-span-2" />
                <x-form.input name="s[business_city]" label="Cidade" :value="$s('business.city')" class="lg:col-span-2" />
                <x-form.input name="s[business_state]" label="UF" :value="$s('business.state')" maxlength="2" class="lg:col-span-1" />
                <x-form.input name="s[business_postal_code]" label="CEP" :value="$s('business.postal_code')" class="lg:col-span-1" />
                <x-form.input name="s[business_phone]" label="Telefone exibido" :value="$s('business.phone')" class="lg:col-span-2" />
                <x-form.input name="s[business_whatsapp]" label="WhatsApp (DDI+DDD+número)" :value="$s('business.whatsapp')" inputmode="numeric" class="lg:col-span-2" />
                <x-form.input name="s[business_email]" type="email" label="E-mail" :value="$s('business.email')" class="lg:col-span-2" />
                <x-form.input name="s[business_whatsapp_message]" label="Mensagem inicial do WhatsApp" :value="$s('business.whatsapp_message')" class="lg:col-span-6" />
                <x-form.input name="s[business_instagram]" type="url" label="Instagram (URL)" :value="$s('business.instagram')" placeholder="https://instagram.com/…" class="lg:col-span-3" />
                <x-form.input name="s[business_maps_url]" type="url" label="Link do Google Maps" :value="$s('business.maps_url')" class="lg:col-span-3" />
                <x-form.input name="s[business_latitude]" label="Latitude (opcional, SEO)" :value="$s('business.latitude')" class="lg:col-span-3" />
                <x-form.input name="s[business_longitude]" label="Longitude (opcional, SEO)" :value="$s('business.longitude')" class="lg:col-span-3" />
            </div>
        </section>

        <section id="horarios" class="card scroll-mt-24">
            <h2 class="font-semibold">Horário de funcionamento</h2>
            <p class="mt-1 text-sm text-stone">Horário público da barbearia. As escalas de cada profissional são configuradas separadamente (Fase 2) e podem ser diferentes.</p>
            <div class="mt-5 divide-y divide-black/[.06]">
                @foreach ($hours as $h)
                    <div class="grid grid-cols-2 items-center gap-3 py-3 sm:grid-cols-[10rem_7rem_1fr_1fr]">
                        <span class="font-medium">{{ $h->dayName() }}</span>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="hours[{{ $h->weekday }}][is_closed]" value="1" class="form-check" @checked(old("hours.{$h->weekday}.is_closed", $h->is_closed))> Fechado</label>
                        <label class="text-sm"><span class="sr-only">Abre</span><input type="time" name="hours[{{ $h->weekday }}][opens_at]" value="{{ old("hours.{$h->weekday}.opens_at", $h->opens_at ? substr($h->opens_at, 0, 5) : '') }}" class="form-input"></label>
                        <label class="text-sm"><span class="sr-only">Fecha</span><input type="time" name="hours[{{ $h->weekday }}][closes_at]" value="{{ old("hours.{$h->weekday}.closes_at", $h->closes_at ? substr($h->closes_at, 0, 5) : '') }}" class="form-input"></label>
                        @error("hours.{$h->weekday}.opens_at")<p class="form-error col-span-full">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <x-form.checkbox name="business_hours_confirmed" label="Horário confirmado pelos proprietários" :checked="(bool) $s('business.hours_confirmed')" hint="Enquanto desmarcado, o site informa que o horário está sujeito a confirmação." />
                <x-form.input name="s[business_hours_note]" label="Observação (feriados etc.)" :value="$s('business.hours_note')" />
            </div>
        </section>

        <section id="marca" class="card scroll-mt-24">
            <h2 class="font-semibold">Identidade visual</h2>
            <p class="mt-1 text-sm text-stone">A paleta atual é provisória (grafite + off-white + acento). Envie o logo e a cor oficial quando disponíveis.</p>
            <div class="mt-5 grid gap-6 sm:grid-cols-2">
                <div>
                    <p class="form-label">Logo oficial (PNG/WebP com fundo transparente)</p>
                    <div class="flex items-center gap-4">
                        <div class="flex h-20 w-40 items-center justify-center rounded-xl bg-ink p-3">
                            <img id="logo-preview" @if ($logo) src="{{ $logo->url(480) }}" @else hidden @endif alt="" class="max-h-full max-w-full">
                            @unless ($logo)<x-wordmark />@endunless
                        </div>
                        <input type="file" name="logo" accept="image/png,image/webp,image/jpeg" data-preview="logo-preview" class="text-sm">
                    </div>
                    @error('logo')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="space-y-5">
                    <div>
                        <label for="accent" class="form-label">Cor de acento</label>
                        <div class="flex items-center gap-3">
                            <input id="accent" type="color" name="s[brand_accent_color]" value="{{ old('s.brand_accent_color', $settings->accentColor()) }}" class="h-11 w-16 cursor-pointer rounded-lg border border-black/15">
                            <span class="text-sm text-stone">Usada em botões e destaques.</span>
                        </div>
                    </div>
                    <x-form.input name="s[brand_tagline]" label="Frase do rodapé" :value="$s('brand.tagline')" />
                </div>
            </div>
        </section>

        <section id="seo" class="card scroll-mt-24">
            <h2 class="font-semibold">SEO e compartilhamento</h2>
            <div class="mt-5 grid gap-5">
                <x-form.input name="s[seo_title]" label="Título (até 70 caracteres)" :value="$s('seo.title')" maxlength="70" data-count="70" />
                <x-form.textarea name="s[seo_description]" label="Descrição (até 170 caracteres)" :value="$s('seo.description')" rows="2" maxlength="170" data-count="170" />
                <div>
                    <p class="form-label">Imagem de compartilhamento (1200×630, foto real)</p>
                    <div class="flex items-center gap-4">
                        <img id="og-preview" @if ($ogImage) src="{{ $ogImage->url(480) }}" @else hidden @endif alt="" class="h-20 rounded-lg object-cover">
                        <input type="file" name="og_image" accept="image/jpeg,image/png,image/webp" data-preview="og-preview" class="text-sm">
                    </div>
                    @error('og_image')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <x-form.checkbox name="seo_indexable" label="Permitir indexação por buscadores (somente em produção)" :checked="(bool) $s('seo.indexable')" />
            </div>
        </section>

        <section id="marketing" class="card scroll-mt-24">
            <h2 class="font-semibold">Medição e marketing</h2>
            <p class="mt-1 text-sm text-stone">Opcional. Quando preenchidos, o site exibe o banner de consentimento e só carrega os scripts após o aceite.</p>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <x-form.input name="s[marketing_ga4_id]" label="Google Analytics 4 (G-XXXX)" :value="$s('marketing.ga4_id')" />
                <x-form.input name="s[marketing_meta_pixel_id]" label="Meta Pixel ID" :value="$s('marketing.meta_pixel_id')" inputmode="numeric" />
            </div>
        </section>

        <div class="sticky bottom-4 flex justify-end"><button class="btn-dark shadow-lg">Salvar configurações</button></div>
    </form>
</x-layouts.admin>
