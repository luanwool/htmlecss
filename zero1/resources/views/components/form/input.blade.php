@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false, 'id' => null])
@php
    $id ??= 'f_'.str_replace(['.', '[', ']'], '_', $name);
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $hasError = $errors->has($key);
@endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="form-label">{{ $label }}@if ($required)<span class="text-red-700" aria-hidden="true"> *</span>@endif</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" @if ($type !== 'password') value="{{ old($key, $value) }}" @endif
           @if ($required) required @endif @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}_error" @elseif ($hint) aria-describedby="{{ $id }}_hint" @endif
           {{ $attributes->except('class')->merge(['class' => 'form-input']) }}>
    @if ($hint && ! $hasError)<p id="{{ $id }}_hint" class="form-hint">{{ $hint }}</p>@endif
    @error($key)<p id="{{ $id }}_error" class="form-error">{{ $message }}</p>@enderror
</div>
