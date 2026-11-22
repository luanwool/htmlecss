<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\Page;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SiteSettings::class);
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Vite::useCspNonce();

        // Proprietário tem acesso completo.
        Gate::before(fn (User $user) => $user->hasRole(Role::Owner->value) ? true : null);

        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        RateLimiter::for('register', fn (Request $r) => Limit::perHour(10)->by($r->ip()));
        RateLimiter::for('password-reset', fn (Request $r) => Limit::perMinute(3)->by($r->ip()));
        RateLimiter::for('uploads', fn (Request $r) => Limit::perMinute(30)->by($r->user()?->id ?: $r->ip()));

        View::composer(['public.*', 'components.*', 'partials.*', 'admin.*', 'account.*', 'auth.*', 'barber.*'], function ($view) {
            $view->with('site', app(SiteSettings::class));
        });
        View::composer('components.layouts.public', function ($view) {
            static $pages;
            $pages ??= rescue(fn () => Page::published()->orderBy('title')->get(['slug', 'title']), collect(), false);
            $view->with('legalPages', $pages);
        });
    }
}
