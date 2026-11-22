@if (session('status'))
    <div {{ $attributes->merge(['class' => 'rounded-xl border border-emerald-600/20 bg-emerald-50 px-4 py-3 text-sm text-emerald-900']) }} role="status">
        {{ session('status') }}
    </div>
@endif
@if ($errors->any())
    <div {{ $attributes->merge(['class' => 'rounded-xl border border-red-600/20 bg-red-50 px-4 py-3 text-sm text-red-900']) }} role="alert">
        <p class="font-semibold">Revise os campos destacados.</p>
        @if ($errors->count() <= 4)
            <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        @endif
    </div>
@endif
