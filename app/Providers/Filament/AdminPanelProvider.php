<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            // The admin panel is Dutch only.
            ->bootUsing(function () {
                app()->setLocale('nl');
                \Illuminate\Support\Carbon::setLocale('nl');
                // Show and enter times in the time zone of the device (set by admin-drafts.js), which changes along the route.
                $timezone = request()->cookie('tz');
                if (is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)) {
                    \Filament\Support\Facades\FilamentTimezone::set($timezone);
                }
            })
            ->favicon(asset('brand/favicon.svg'))
            ->path('admin')
            ->login()
            // "Wachtwoord vergeten" on the login page (needs MAIL_* in .env, see DEPLOYMENT.md).
            ->passwordReset()
            ->brandName('Travel with Coen')
            // Saves form drafts in the browser, for writing on a weak connection.
            ->renderHook(PanelsRenderHook::BODY_END, fn () => Blade::render("@vite('resources/js/admin-drafts.js')"))
            ->colors([
                'primary' => Color::Green,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
