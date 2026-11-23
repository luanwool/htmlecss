<x-layouts.admin title="Registrar atendimento de balcão">
    @error('booking')<div class="mb-4 rounded-xl bg-red-50 p-4 text-sm text-red-900" role="alert">{{ $message }}</div>@enderror
    <form id="customer-search" method="GET"></form>
    <form method="POST" action="{{ route('admin.visits.store') }}" class="max-w-3xl" data-loading>
        @csrf
        @include('admin.visits._form')
    </form>
</x-layouts.admin>
