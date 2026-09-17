<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\ClaimStatsOverview;
use App\Filament\Widgets\OverBudgetAlertWidget;
use App\Filament\Widgets\PositionBudgetOverviewWidget;
use App\Filament\Widgets\RecentClaimPeriodsWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile(\App\Filament\Pages\EditProfile::class)
            ->brandName('PT. Media Selular Indonesia')
            ->brandLogo(asset('images/company-logo.png'))
            ->brandLogoHeight('2.6rem')
            ->favicon(asset('images/company-logo.png'))
            ->colors([
                'primary' => Color::Emerald,
                'gray' => Color::Slate,
            ])
            ->font('Inter')
            ->maxContentWidth(MaxWidth::Full)
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                NavigationGroup::make('PENGAJUAN KLAIM')
                    ->icon('heroicon-o-currency-dollar'),
                NavigationGroup::make('REKAPAN BIAYA')
                    ->icon('heroicon-o-chart-bar-square'),
                NavigationGroup::make('MASTER DATA')
                    ->icon('heroicon-o-circle-stack'),
                NavigationGroup::make('DOKUMEN')
                    ->icon('heroicon-o-folder'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->widgets([
                ClaimStatsOverview::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
