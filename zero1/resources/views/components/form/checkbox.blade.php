@props(['name', 'label', 'checked' => false, 'hint' => null, 'value' => '1'])
@php
    $id = 'f_'.str_replace(['.', '[', ']'], '_', $name).(str_ends_with($name, '[]') ? '_'.$value : '');
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $isChecked = old() ? (bool) old(rtrim($key, '.')) : $checked;
    if (str_ends_with($name, '[]')) {
        $isChecked = old() ? in_array($value, (array) old(rtrim($key, '.'), [])) : $checked;
    }
@endphp
<div {{ $attributes->only('class')->merge(['class' => 'flex items-start gap-3']) }}>
    <input id="{{ $id }}" type="checkbox" name="{{ $name }}" value="{{ $value }}" class="form-check mt-0.5 shrink-0" @checked($isChecked)>
    <label for="{{ $id }}" class="text-sm">
        <span class="font-medium text-ink">{!! $label !!}</span>
        @if ($hint)<span class="mt-0.5 block text-xs text-stone">{{ $hint }}</span>@endif
        @error(rtrim($key, '.'))<span class="form-error block">{{ $message }}</span>@enderror
    </label>
</div>
