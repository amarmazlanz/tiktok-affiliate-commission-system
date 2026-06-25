<?php

namespace App\Providers;

use App\Models\Affiliate;
use App\Services\AffiliateReferralService;
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
        Affiliate::created(function (Affiliate $affiliate): void {
            app(AffiliateReferralService::class)->ensureFor($affiliate);
        });
    }
}
