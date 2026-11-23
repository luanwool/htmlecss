@props(['name', 'label', 'options' => [], 'value' => null, 'hint' => null, 'placeholder' => null, 'required' => false])
@php
    $id = 'f_'.str_replace(['.', '[', ']'], '_', $name);
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $current = (string) old($key, $value instanceof \BackedEnum ? $value->value : $value);
@endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="form-label">{{ $label }}@if ($required)<span class="text-red-700" aria-hidden="true"> *</span>@endif</label>
    <select id="{{ $id }}" name="{{ $name }}" @if ($required) required @endif @error($key) aria-invalid="true" @enderror {{ $attributes->except('class')->merge(['class' => 'form-input']) }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected($current === (string) $optValue)>{{ $optLabel }}</option>
        @endforeach
    </select>
    @if ($hint)<p class="form-hint">{{ $hint }}</p>@endif
    @error($key)<p class="form-error">{{ $message }}</p>@enderror
</div>
