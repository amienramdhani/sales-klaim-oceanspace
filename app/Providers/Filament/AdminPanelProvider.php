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
use Filament\View\PanelsRenderHook;
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
            ->login(\App\Filament\Pages\Auth\Login::class)
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
                NavigationGroup::make('PERSETUJUAN KLAIM')
                    ->icon('heroicon-o-check-badge'),
                NavigationGroup::make('PENGAJUAN KLAIM')
                    ->icon('heroicon-o-currency-dollar'),
                NavigationGroup::make('MENU FINANCE')
                    ->icon('heroicon-o-banknotes'),
                NavigationGroup::make('DOKUMEN')
                    ->icon('heroicon-o-folder'),
                NavigationGroup::make('REKAPAN BIAYA')
                    ->icon('heroicon-o-chart-bar-square'),
                NavigationGroup::make('MASTER DATA')
                    ->icon('heroicon-o-circle-stack'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->widgets([
                ClaimStatsOverview::class,
                \App\Filament\Widgets\RecentClaimsWidget::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<link rel="stylesheet" href="' . asset('css/filament-mobile.css') . '?v=' . (file_exists(public_path('css/filament-mobile.css')) ? filemtime(public_path('css/filament-mobile.css')) : time()) . '">',
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.components.mobile-bottom-nav'),
            )
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
                \App\Http\Middleware\EnsurePinIsChanged::class,
            ]);
    }
}
