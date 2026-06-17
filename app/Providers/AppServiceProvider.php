<?php

namespace App\Providers;

use App\Contracts\YandexMapsShortUrlResolver;
use App\Services\YandexMapsHttpShortUrlResolver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(YandexMapsShortUrlResolver::class, YandexMapsHttpShortUrlResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
