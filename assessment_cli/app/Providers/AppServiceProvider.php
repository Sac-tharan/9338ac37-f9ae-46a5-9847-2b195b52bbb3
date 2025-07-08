<?php

namespace App\Providers;

use App\Services\ReportService;
use App\Services\DataLoaderService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ReportService::class);
        $this->app->singleton(DataLoaderService::class);

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
