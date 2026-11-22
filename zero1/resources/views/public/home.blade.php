<x-layouts.public :schema="$schema" :motion="true">
    @foreach ($sections as $key => $section)
        @includeIf('public.sections.'.$key, ['section' => $section])
    @endforeach
</x-layouts.public>
