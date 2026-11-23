<?php

use App\Enums\Permission as P;
use App\Http\Controllers\Account;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth;
use App\Http\Controllers\Barber;
use App\Http\Controllers\Public;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Site público
|--------------------------------------------------------------------------
*/
Route::get('/', Public\HomeController::class)->name('home');
Route::get('/p/{page:slug}', Public\PageController::class)->name('pages.show');
Route::get('/sitemap.xml', Public\SitemapController::class)->name('sitemap');
Route::get('/i/{code}', Public\ReferralLinkController::class)
    ->whereAlphaNumeric('code')->middleware('throttle:30,1')->name('referral.link');
Route::get('/robots.txt', Public\RobotsController::class)->name('robots');
// Agendamento: qualquer visitante vê horários; confirmar exige login.
Route::get('/agendar', [Public\BookingController::class, 'create'])->name('booking.start');
Route::post('/agendar', [Public\BookingController::class, 'store'])->middleware(['auth', 'throttle:20,1'])->name('booking.store');

// Webhooks do gateway (sem CSRF; autenticidade validada pelo adaptador).
Route::post('/webhooks/pagamentos/{gateway}', Public\PaymentWebhookController::class)
    ->middleware('throttle:120,1')->name('webhooks.payments');

/*
|--------------------------------------------------------------------------
| Autenticação
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/entrar', [Auth\LoginController::class, 'create'])->name('login');
    Route::post('/entrar', [Auth\LoginController::class, 'store'])->name('login.store');
    Route::get('/cadastro', [Auth\RegisterController::class, 'create'])->name('register');
    Route::post('/cadastro', [Auth\RegisterController::class, 'store'])->middleware('throttle:register')->name('register.store');
    Route::get('/esqueci-a-senha', [Auth\PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/esqueci-a-senha', [Auth\PasswordResetController::class, 'email'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/redefinir-senha/{token}', [Auth\PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/redefinir-senha', [Auth\PasswordResetController::class, 'update'])->middleware('throttle:password-reset')->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/sair', [Auth\LoginController::class, 'destroy'])->name('logout');
    // Troca forçada de senha (após login com senha temporária gerada pelo ADM).
    Route::get('/trocar-senha', [Account\ProfileController::class, 'showForcedChange'])->name('password.change');
    Route::put('/trocar-senha', [Account\ProfileController::class, 'updateForcedChange'])
        ->middleware('throttle:10,1')->name('password.change.update');
    Route::get('/email/verificar', [Auth\EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verificar/{id}/{hash}', [Auth\EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/reenviar', [Auth\EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')->name('verification.send');

    /*
    | Área do cliente
    */
    // Clube: adesão (e-mail verificado), retorno do checkout e simulador de pagamento.
    Route::get('/clube/{plan:slug}/assinar', [Public\SubscribeController::class, 'create'])->middleware('verified')->name('plans.subscribe');
    Route::post('/clube/{plan:slug}/assinar', [Public\SubscribeController::class, 'store'])->middleware(['verified', 'throttle:10,1'])->name('plans.subscribe.store');
    Route::get('/assinatura/retorno', [Public\SubscribeController::class, 'returned'])->name('subscription.return');
    Route::get('/simulador/checkout/{externalId}', [Public\SimulatorController::class, 'show'])->name('simulator.checkout');
    Route::post('/simulador/checkout/{externalId}', [Public\SimulatorController::class, 'pay'])->middleware('throttle:20,1')->name('simulator.pay');

    Route::get('/agendamento/{uid}', [Account\AppointmentController::class, 'show'])->name('account.appointments.show');
    Route::get('/agendamento/{uid}/calendario.ics', [Account\AppointmentController::class, 'ics'])->name('account.appointments.ics');
    Route::get('/agendamento/{uid}/reagendar', [Account\AppointmentController::class, 'editTime'])->name('account.appointments.reschedule');
    Route::post('/agendamento/{uid}/reagendar', [Account\AppointmentController::class, 'reschedule'])->middleware('throttle:20,1')->name('account.appointments.reschedule.store');
    Route::post('/agendamento/{uid}/cancelar', [Account\AppointmentController::class, 'cancel'])->name('account.appointments.cancel');

    Route::prefix('minha-conta')->name('account.')->group(function () {
        Route::get('/', Account\DashboardController::class)->name('dashboard');
        Route::get('/agendamentos', [Account\AppointmentController::class, 'index'])->name('appointments');
        Route::get('/assinatura', [Account\SubscriptionController::class, 'show'])->name('subscription');
        Route::post('/assinatura/cancelar', [Account\SubscriptionController::class, 'cancel'])->name('subscription.cancel');
        Route::post('/assinatura/pagar/{cycle}', [Account\SubscriptionController::class, 'pay'])->name('subscription.pay');
        Route::get('/perfil', [Account\ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/perfil', [Account\ProfileController::class, 'update'])->name('profile.update');
        Route::put('/senha', [Account\ProfileController::class, 'password'])->name('password.update');
        Route::get('/indicar', Account\ReferralController::class)->name('referrals');
        Route::get('/privacidade', [Account\PrivacyController::class, 'index'])->name('privacy');
        Route::get('/privacidade/exportar', [Account\PrivacyController::class, 'export'])->middleware('throttle:5,1')->name('privacy.export');
        Route::post('/privacidade/solicitacao', [Account\PrivacyController::class, 'request'])->middleware('throttle:5,1')->name('privacy.request');
    });

    /*
    | Painel do barbeiro (somente a própria agenda — Fase 2)
    */
    Route::prefix('barbeiro')->name('barber.')->middleware('role:barber')->group(function () {
        Route::get('/', Barber\DashboardController::class)->name('dashboard');
        Route::get('/atendimento/novo', [Barber\VisitController::class, 'create'])->name('visits.create');
        Route::post('/atendimento', [Barber\VisitController::class, 'store'])->name('visits.store');
        Route::get('/producao', [Barber\VisitController::class, 'index'])->name('visits.index');
        Route::get('/escala', [Barber\ScheduleController::class, 'index'])->name('schedule.index');
        Route::post('/escala', [Barber\ScheduleController::class, 'store'])->middleware('throttle:10,1')->name('schedule.store');
        Route::post('/escala/{scheduleRequest}/cancelar', [Barber\ScheduleController::class, 'cancel'])->name('schedule.cancel');
        Route::post('/agendamentos/{appointment}/{action}', [Barber\DashboardController::class, 'action'])
            ->whereIn('action', ['iniciar', 'concluir', 'ausente'])->name('appointments.action');
    });

    /*
    | Painel gerencial
    */
    Route::prefix('painel')->name('admin.')->middleware('role:owner|manager')->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::middleware('permission:'.P::AgendaViewAll->value)->group(function () {
            Route::get('agenda', [Admin\AgendaController::class, 'index'])->name('agenda.index');
            Route::get('agenda/{appointment}', [Admin\AgendaController::class, 'show'])->whereNumber('appointment')->name('agenda.show');
        });
        Route::middleware('permission:'.P::AgendaManage->value)->group(function () {
            Route::get('agenda/novo', [Admin\AgendaController::class, 'create'])->name('agenda.create');
            Route::post('agenda', [Admin\AgendaController::class, 'store'])->name('agenda.store');
            Route::get('agenda/{appointment}/reagendar', [Admin\AgendaController::class, 'editTime'])->name('agenda.reschedule');
            Route::post('agenda/{appointment}/reagendar', [Admin\AgendaController::class, 'reschedule'])->name('agenda.reschedule.store');
            Route::post('agenda/{appointment}/{action}', [Admin\AgendaController::class, 'action'])
                ->whereIn('action', ['iniciar', 'concluir', 'ausente', 'cancelar'])->name('agenda.action');
            Route::get('atendimentos', [Admin\VisitController::class, 'index'])->name('visits.index');
            Route::get('atendimentos/novo', [Admin\VisitController::class, 'create'])->name('visits.create');
            Route::post('atendimentos', [Admin\VisitController::class, 'store'])->name('visits.store');
            Route::post('atendimentos/{visit}/pago', [Admin\VisitController::class, 'markPaid'])->name('visits.paid');
        });
        Route::middleware('permission:'.P::ScheduleReview->value)->group(function () {
            Route::get('escala/pedidos', [Admin\ScheduleRequestController::class, 'index'])->name('schedule-requests.index');
            Route::post('escala/pedidos/{scheduleRequest}/aprovar', [Admin\ScheduleRequestController::class, 'approve'])->name('schedule-requests.approve');
            Route::post('escala/pedidos/{scheduleRequest}/rejeitar', [Admin\ScheduleRequestController::class, 'reject'])->name('schedule-requests.reject');
        });
        Route::middleware('permission:'.P::ReferralsManage->value)->group(function () {
            Route::get('indicacoes', [Admin\ReferralController::class, 'index'])->name('referrals.index');
        });
        Route::middleware('permission:'.P::SchedulesManage->value)->group(function () {
            Route::get('escalas', [Admin\ScheduleController::class, 'index'])->name('schedules.index');
            Route::put('escalas/{barber}', [Admin\ScheduleController::class, 'updateWeekly'])->name('schedules.weekly');
            Route::post('escalas/excecoes', [Admin\ScheduleController::class, 'storeException'])->name('schedules.exceptions.store');
            Route::delete('escalas/excecoes/{exception}', [Admin\ScheduleController::class, 'destroyException'])->name('schedules.exceptions.destroy');
            Route::post('escalas/bloqueios', [Admin\ScheduleController::class, 'storeBlock'])->name('schedules.blocks.store');
            Route::delete('escalas/bloqueios/{block}', [Admin\ScheduleController::class, 'destroyBlock'])->name('schedules.blocks.destroy');
        });
        Route::middleware('permission:'.P::SubscriptionsManage->value)->group(function () {
            Route::get('assinaturas', [Admin\SubscriptionController::class, 'index'])->name('subscriptions.index');
            Route::get('assinaturas/{subscription}', [Admin\SubscriptionController::class, 'show'])->name('subscriptions.show');
            Route::post('assinaturas/{subscription}/cancelar', [Admin\SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
            Route::post('assinaturas/{subscription}/conciliar', [Admin\SubscriptionController::class, 'reconcile'])->name('subscriptions.reconcile');
            Route::post('assinaturas/{subscription}/simular', [Admin\SubscriptionController::class, 'simulate'])->name('subscriptions.simulate');
            Route::post('assinaturas/rotina', [Admin\SubscriptionController::class, 'tick'])->name('subscriptions.tick');
        });
        Route::middleware('permission:'.P::FinanceView->value)->group(function () {
            Route::get('financeiro', [Admin\FinanceController::class, 'index'])->name('finance.index');
        });
        Route::middleware('permission:'.P::FinanceManage->value)->group(function () {
            Route::post('financeiro/despesas', [Admin\FinanceController::class, 'storeExpense'])->name('finance.expenses.store');
            Route::delete('financeiro/lancamentos/{entry}', [Admin\FinanceController::class, 'destroyEntry'])->name('finance.entries.destroy');
            Route::post('financeiro/comissoes', [Admin\FinanceController::class, 'storeRule'])->name('finance.rules.store');
            Route::delete('financeiro/comissoes/{rule}', [Admin\FinanceController::class, 'destroyRule'])->name('finance.rules.destroy');
            Route::post('financeiro/comissoes/pagar/{barber}', [Admin\FinanceController::class, 'payOut'])->name('finance.payout');
        });
        Route::middleware('permission:'.P::ReportsView->value)->group(function () {
            Route::get('relatorios', [Admin\ReportController::class, 'index'])->name('reports.index');
            Route::get('relatorios/planos', [Admin\ReportController::class, 'plans'])->name('reports.plans');
        });
        Route::get('relatorios/exportar/{dataset}', [Admin\ReportController::class, 'export'])
            ->middleware('permission:'.P::ReportsExport->value)->whereIn('dataset', ['atendimentos', 'financeiro', 'agendamentos', 'assinaturas'])
            ->name('reports.export');

        Route::middleware('permission:'.P::ServicesManage->value)->group(function () {
            Route::resource('servicos', Admin\ServiceController::class)->except('show')
                ->parameters(['servicos' => 'service'])->names('services');
            Route::resource('categorias', Admin\ServiceCategoryController::class)->except(['show', 'create', 'edit'])
                ->parameters(['categorias' => 'category'])->names('categories');
        });

        Route::middleware('permission:'.P::BarbersManage->value)->group(function () {
            Route::resource('profissionais', Admin\BarberController::class)->except('show')
                ->parameters(['profissionais' => 'barber'])->names('barbers');
        });

        Route::middleware('permission:'.P::PlansManage->value)->group(function () {
            Route::resource('planos', Admin\PlanController::class)->except('show')
                ->parameters(['planos' => 'plan'])->names('plans');
        });

        Route::middleware('permission:'.P::CustomersView->value)->group(function () {
            Route::get('clientes', [Admin\CustomerController::class, 'index'])->name('customers.index');
            Route::get('clientes/{customer}', [Admin\CustomerController::class, 'show'])->name('customers.show');
            Route::post('clientes/{customer}/resetar-senha', [Admin\CustomerController::class, 'resetPassword'])
                ->middleware('throttle:10,1')->name('customers.reset-password');
        });

        Route::middleware('permission:'.P::ContentManage->value)->prefix('conteudo')->group(function () {
            Route::get('/', [Admin\SectionController::class, 'index'])->name('content.index');
            Route::get('secoes/{section}', [Admin\SectionController::class, 'edit'])->name('sections.edit');
            Route::put('secoes/{section}', [Admin\SectionController::class, 'update'])->name('sections.update');
            Route::get('midia', [Admin\MediaController::class, 'index'])->name('media.index');
            Route::post('midia', [Admin\MediaController::class, 'store'])->middleware('throttle:uploads')->name('media.store');
            Route::put('midia/{media}', [Admin\MediaController::class, 'update'])->name('media.update');
            Route::delete('midia/{media}', [Admin\MediaController::class, 'destroy'])->name('media.destroy');
            Route::resource('depoimentos', Admin\ReviewController::class)->except('show')
                ->parameters(['depoimentos' => 'review'])->names('reviews');
            Route::resource('faq', Admin\FaqController::class)->except('show')->names('faqs');
            Route::get('paginas', [Admin\PageController::class, 'index'])->name('pages.index');
            Route::get('paginas/{page}/editar', [Admin\PageController::class, 'edit'])->name('pages.edit');
            Route::put('paginas/{page}', [Admin\PageController::class, 'update'])->name('pages.update');
        });

        Route::middleware('permission:'.P::SettingsManage->value)->group(function () {
            Route::get('configuracoes', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('configuracoes', [Admin\SettingsController::class, 'update'])->name('settings.update');
        });

        Route::middleware('permission:'.P::UsersManage->value)->group(function () {
            Route::resource('usuarios', Admin\UserController::class)->except('show')
                ->parameters(['usuarios' => 'user'])->names('users');
            Route::get('cargos', [Admin\RoleController::class, 'edit'])->name('roles.edit');
            Route::put('cargos', [Admin\RoleController::class, 'update'])->name('roles.update');
        });

        Route::middleware('permission:'.P::PrivacyManage->value)->group(function () {
            Route::get('lgpd', [Admin\DataRequestController::class, 'index'])->name('data-requests.index');
            Route::put('lgpd/{dataRequest}', [Admin\DataRequestController::class, 'update'])->name('data-requests.update');
        });

        Route::get('auditoria', Admin\AuditLogController::class)
            ->middleware('permission:'.P::AuditView->value)->name('audit.index');
    });
});
