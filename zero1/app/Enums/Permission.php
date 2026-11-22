<?php

namespace App\Enums;

/**
 * Permissões granulares. O proprietário recebe tudo via Gate::before;
 * o gestor recebe o conjunto padrão abaixo, ajustável pelo proprietário.
 */
enum Permission: string
{
    case DashboardView = 'dashboard.view';
    case AgendaViewAll = 'agenda.view_all';
    case AgendaManage = 'agenda.manage';
    case ServicesManage = 'services.manage';
    case PlansManage = 'plans.manage';
    case SubscriptionsManage = 'subscriptions.manage';
    case CustomersView = 'customers.view';
    case CustomersManage = 'customers.manage';
    case BarbersManage = 'barbers.manage';
    case SchedulesManage = 'schedules.manage';
    case FinanceView = 'finance.view';
    case FinanceManage = 'finance.manage';
    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';
    case ContentManage = 'content.manage';
    case SettingsManage = 'settings.manage';
    case UsersManage = 'users.manage';
    case AuditView = 'audit.view';
    case IntegrationsManage = 'integrations.manage';
    case PrivacyManage = 'privacy.manage';

    public function label(): string
    {
        return match ($this) {
            self::DashboardView => 'Ver visão geral',
            self::AgendaViewAll => 'Ver agenda de todos os profissionais',
            self::AgendaManage => 'Gerenciar agendamentos e bloqueios',
            self::ServicesManage => 'Gerenciar serviços',
            self::PlansManage => 'Gerenciar planos do clube',
            self::SubscriptionsManage => 'Gerenciar assinaturas',
            self::CustomersView => 'Ver clientes',
            self::CustomersManage => 'Editar clientes',
            self::BarbersManage => 'Gerenciar profissionais',
            self::SchedulesManage => 'Gerenciar escalas e horários',
            self::FinanceView => 'Ver financeiro',
            self::FinanceManage => 'Lançar movimentações financeiras',
            self::ReportsView => 'Ver relatórios',
            self::ReportsExport => 'Exportar relatórios',
            self::ContentManage => 'Editar conteúdo do site',
            self::SettingsManage => 'Alterar configurações',
            self::UsersManage => 'Gerenciar usuários e cargos',
            self::AuditView => 'Ver auditoria',
            self::IntegrationsManage => 'Gerenciar integrações (pagamentos)',
            self::PrivacyManage => 'Tratar solicitações LGPD',
        };
    }

    /** Somente o proprietário (Gate::before) — não atribuíveis ao gestor por padrão. */
    public static function ownerOnly(): array
    {
        return [self::UsersManage, self::IntegrationsManage, self::AuditView];
    }

    public static function managerDefaults(): array
    {
        return array_values(array_filter(self::cases(), fn (self $p) => ! in_array($p, self::ownerOnly(), true)));
    }
}
