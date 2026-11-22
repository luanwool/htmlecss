<x-layouts.public :schema="$schema">
    @foreach ($sections as $key => $section)
        @includeIf('public.sections.'.$key, ['section' => $section])
    @endforeach
</x-layouts.public>
