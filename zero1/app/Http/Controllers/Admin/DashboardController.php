<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Models\Customer;
use App\Models\DataRequest;
use App\Models\Faq;
use App\Models\MediaAsset;
use App\Models\MembershipPlan;
use App\Models\Page;
use App\Models\Service;
use App\Support\SiteSettings;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(SiteSettings $settings): View
    {
        $stats = [
            'Serviços ativos' => Service::active()->count(),
            'Profissionais ativos' => Barber::active()->count(),
            'Planos publicados' => MembershipPlan::published()->count(),
            'Clientes' => Customer::count(),
        ];

        // Lista do que ainda falta o proprietário informar — nada disso é inventado pelo sistema.
        $pending = array_filter([
            ! $settings->get('brand.logo_media_id') ? ['Enviar o logo oficial', route('admin.settings.edit').'#marca'] : null,
            ! $settings->get('business.hours_confirmed') ? ['Confirmar o horário de funcionamento (cadastro público: seg–sáb 09h–19h)', route('admin.settings.edit').'#horarios'] : null,
            Barber::count() === 0 ? ['Cadastrar os profissionais (nome, foto, apresentação)', route('admin.barbers.create')] : null,
            Service::count() === 0 ? ['Cadastrar serviços com preços e durações reais', route('admin.services.create')] : null,
            MembershipPlan::count() === 0 ? ['Definir os planos do Clube Zero 1 (preço, franquia, regras)', route('admin.plans.create')] : null,
            MediaAsset::where('collection', 'gallery')->count() === 0 ? ['Enviar fotos reais do espaço para a galeria', route('admin.media.index')] : null,
            Page::where('is_published', false)->exists() ? ['Revisar e publicar termos, privacidade e políticas', route('admin.pages.index')] : null,
            Faq::published()->count() === 0 ? ['Responder as perguntas frequentes', route('admin.faqs.index')] : null,
            ! $settings->get('business.instagram') ? ['Informar o Instagram oficial', route('admin.settings.edit').'#empresa'] : null,
            ! config('services.mercadopago.access_token') ? ['Credenciais de teste do Mercado Pago (Fase 3, via .env — nunca por mensagem)', null] : null,
        ]);

        return view('admin.dashboard', [
            'stats' => $stats,
            'pending' => $pending,
            'openDataRequests' => DataRequest::where('status', 'open')->count(),
            'demoData' => Service::where('name', 'like', '[DEMO]%')->exists(),
        ]);
    }
}
