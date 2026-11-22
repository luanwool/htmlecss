<x-layouts.barber title="Registrar atendimento">
    <form id="customer-search" method="GET"></form>
    <form method="POST" action="{{ route('barber.visits.store') }}" data-loading>
        @csrf
        @include('admin.visits._form')
    </form>
</x-layouts.barber>
