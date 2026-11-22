<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class UpcomingModuleController extends Controller
{
    public const MODULES = [
        'agenda' => ['Agenda', 2, 'Visões Hoje / 3 / 7 / 15 dias / mês, colunas por profissional, criação de reservas por telefone/balcão, bloqueios, reagendamento e encaixe somente com disponibilidade real.'],
        'escalas' => ['Escalas e bloqueios', 2, 'Escala semanal por barbeiro com múltiplos períodos, almoço, folgas, férias, feriados, aberturas extraordinárias e bloqueios do salão ou individuais.'],
        'atendimentos' => ['Atendimentos', 4, 'Lançamentos de balcão e conclusão de agendamentos com vínculo único entre reserva, execução, consumo de franquia e financeiro.'],
        'assinaturas' => ['Assinaturas', 3, 'Adesões, ciclos de cobrança, ledger de franquia, status de pagamento sincronizado com o Mercado Pago, cancelamento e reconciliação.'],
        'financeiro' => ['Financeiro', 4, 'Receita reconhecida x caixa recebido x recorrente, taxas, estornos, despesas e comissões.'],
        'relatorios' => ['Relatórios', 4, 'Produção por barbeiro e serviço, uso de planos, MRR, cancelamentos, ocupação e exportação CSV.'],
    ];

    public function __invoke(string $module): View
    {
        [$title, $phase, $description] = self::MODULES[$module];

        return view('admin.upcoming', compact('title', 'phase', 'description'));
    }
}
