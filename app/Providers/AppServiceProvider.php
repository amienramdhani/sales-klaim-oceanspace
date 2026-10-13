<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        \App\Models\Claim::observe(\App\Observers\ClaimObserver::class);

        \Filament\Forms\Components\DatePicker::configureUsing(function (\Filament\Forms\Components\DatePicker $picker): void {
            $picker->closeOnDateSelection();
        });

        \Filament\Forms\Components\DateTimePicker::configureUsing(function (\Filament\Forms\Components\DateTimePicker $picker): void {
            $picker->closeOnDateSelection();
        });

        // Konfigurasi session & cookie 'Ingat Saya' selama 7 hari (10080 menit)
        try {
            \Illuminate\Support\Facades\Auth::resolved(function ($auth) {
                if (method_exists($auth->guard('web'), 'setRememberDuration')) {
                    $auth->guard('web')->setRememberDuration((int)config('session.lifetime', 10080));
                }
            });
        } catch (\Throwable $e) {
            // Silently fallback if auth not yet initialized
        }
    }
}
