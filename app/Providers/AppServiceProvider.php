<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WhatsAppCloudApi::class, fn () => new WhatsAppCloudApi([
        'from_phone_number_id' => config('services.whatsapp.phone_number_id'),
        'access_token'         => config('services.whatsapp.token'),
        'business_id'          => config('services.whatsapp.business_id'), // untuk kelola template
    ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
