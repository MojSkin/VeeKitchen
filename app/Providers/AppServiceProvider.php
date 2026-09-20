<?php

namespace App\Providers;

use App\Services\Contracts\CashShiftGuard;
use App\Services\ShiftService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The phase-3 payment guard: every payment resolves its open shift
        // through this contract.
        $this->app->bind(CashShiftGuard::class, ShiftService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
