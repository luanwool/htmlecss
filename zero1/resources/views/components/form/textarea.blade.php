@props(['name', 'label', 'value' => null, 'hint' => null, 'rows' => 4, 'required' => false])
@php
    $id = 'f_'.str_replace(['.', '[', ']'], '_', $name);
    $key = str_replace(['[', ']'], ['.', ''], $name);
@endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="form-label">{{ $label }}@if ($required)<span class="text-red-700" aria-hidden="true"> *</span>@endif</label>
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @if ($required) required @endif @error($key) aria-invalid="true" @enderror
              {{ $attributes->except('class')->merge(['class' => 'form-input py-2.5']) }}>{{ old($key, $value) }}</textarea>
    @if ($hint)<p class="form-hint">{{ $hint }}</p>@endif
    @error($key)<p class="form-error">{{ $message }}</p>@enderror
</div>
