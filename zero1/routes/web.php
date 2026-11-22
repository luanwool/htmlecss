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
Route::get('/robots.txt', Public\RobotsController::class)->name('robots');
// Destinos dos CTAs; o fluxo completo de agendamento entra na Fase 2 e o de assinatura na Fase 3.
Route::get('/agendar', [Public\BookingEntryController::class, 'booking'])->name('booking.start');
Route::get('/clube/{plan:slug}/assinar', [Public\BookingEntryController::class, 'subscribe'])->name('plans.subscribe');

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
    Route::get('/email/verificar', [Auth\EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verificar/{id}/{hash}', [Auth\EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/reenviar', [Auth\EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')->name('verification.send');

    /*
    | Área do cliente
    */
    Route::prefix('minha-conta')->name('account.')->group(function () {
        Route::get('/', Account\DashboardController::class)->name('dashboard');
        Route::get('/perfil', [Account\ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/perfil', [Account\ProfileController::class, 'update'])->name('profile.update');
        Route::put('/senha', [Account\ProfileController::class, 'password'])->name('password.update');
        Route::get('/privacidade', [Account\PrivacyController::class, 'index'])->name('privacy');
        Route::get('/privacidade/exportar', [Account\PrivacyController::class, 'export'])->middleware('throttle:5,1')->name('privacy.export');
        Route::post('/privacidade/solicitacao', [Account\PrivacyController::class, 'request'])->middleware('throttle:5,1')->name('privacy.request');
    });

    /*
    | Painel do barbeiro (somente a própria agenda — Fase 2)
    */
    Route::prefix('barbeiro')->name('barber.')->middleware('role:barber')->group(function () {
        Route::get('/', Barber\DashboardController::class)->name('dashboard');
    });

    /*
    | Painel gerencial
    */
    Route::prefix('painel')->name('admin.')->middleware('role:owner|manager')->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

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

        // Módulos das próximas fases: rota existe, com tela explicando o que será entregue.
        Route::get('modulo/{module}', Admin\UpcomingModuleController::class)
            ->whereIn('module', array_keys(Admin\UpcomingModuleController::MODULES))->name('upcoming');
    });
});
