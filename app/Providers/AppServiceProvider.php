<?php

namespace App\Providers;

use App\Models\DashboardApiClient;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        RateLimiter::for('otp-send', function (Request $request): array {
            $limits = [Limit::perMinute(3)->by('otp-ip:'.$request->ip())];

            if ($request->user()) {
                $limits[] = Limit::perMinute(3)->by('otp-user:'.$request->user()->getAuthIdentifier());
            }

            return $limits;
        });

        RateLimiter::for('management-dashboard', function (Request $request): Limit {
            $client = $request->user();

            return Limit::perMinute(60)->by(
                $client instanceof DashboardApiClient
                    ? 'dashboard-client:'.$client->getKey()
                    : 'dashboard-ip:'.$request->ip(),
            );
        });

        LanguageSwitch::configureUsing(function (LanguageSwitch $switch): void {
            $switch
                ->locales(['en', 'zh_CN'])
                ->labels([
                    'en' => 'English',
                    'zh_CN' => '中文',
                ]);
        });

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
