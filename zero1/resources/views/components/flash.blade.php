@if (session('status'))
    <div {{ $attributes->merge(['class' => 'rounded-xl border border-emerald-600/20 bg-emerald-50 px-4 py-3 text-sm text-emerald-900']) }} role="status">
        {{ session('status') }}
    </div>
@endif
@php $generic = collect($errors->getMessages())->except('booking'); @endphp
@if ($generic->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'rounded-xl border border-red-600/20 bg-red-50 px-4 py-3 text-sm text-red-900']) }} role="alert">
        <p class="font-semibold">Revise os campos destacados.</p>
        @if ($generic->flatten()->count() <= 4)
            <ul class="mt-1 list-disc pl-5">@foreach ($generic->flatten() as $e)<li>{{ $e }}</li>@endforeach</ul>
        @endif
    </div>
@endif
