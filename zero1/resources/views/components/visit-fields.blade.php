{{-- Campos de valor/pagamento de atendimento avulso (ocultos quando é plano). --}}
@props(['paymentMethods', 'price' => null, 'showWhen' => null])
<div @if ($showWhen) data-show-when="{{ $showWhen }}" @endif class="grid gap-4 sm:grid-cols-2">
    <x-form.input name="price" label="Valor (R$)" inputmode="decimal" :value="$price" placeholder="0,00" />
    <x-form.input name="discount" label="Desconto autorizado (R$)" inputmode="decimal" placeholder="0,00" />
    <x-form.select name="payment_method" label="Forma de pagamento" :options="$paymentMethods" placeholder="Selecione" />
    <x-form.select name="payment_status" label="Situação" :options="['paid' => 'Pago', 'pending' => 'Pendente']" value="paid" />
</div>
