<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\AccountingStats;
use App\Filament\Widgets\CompanyInfoWidget;
use App\Filament\Widgets\DashboardStats;
use App\Filament\Widgets\LedgerWidget;
use App\Filament\Widgets\LowStockWidget;
use App\Filament\Widgets\MonthlyPurchaseTrendWidget;
use App\Filament\Widgets\PurchaseAnalyticsWidget;
use App\Filament\Widgets\TopVendorskWidget;
use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use AchyutN\FilamentLogViewer\FilamentLogViewer;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Red,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                    // Widgets\FilamentInfoWidget::class,
                CompanyInfoWidget::class,
                DashboardStats::class,
                LowStockWidget::class, // 👈 Add here
                PurchaseAnalyticsWidget::class,
                TopVendorskWidget::class,
                MonthlyPurchaseTrendWidget::class,

                AccountingStats::class,
                LedgerWidget::class,
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
            ->plugins([
                FilamentShieldPlugin::make(),
                \TomatoPHP\FilamentDocs\FilamentDocsPlugin::make(),
                FilamentLogViewer::make(),
                \TomatoPHP\FilamentLanguageSwitcher\FilamentLanguageSwitcherPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->databaseNotifications()
            //->databaseNotificationsPolling('30s')
            ->sidebarCollapsibleOnDesktop()
            ->collapsedSidebarWidth('9rem')
            ->sidebarWidth('16rem');
    }
}
