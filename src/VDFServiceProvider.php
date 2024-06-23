<?php

namespace MoonlyDays\LaravelVDF;

use Illuminate\Support\ServiceProvider;

class VDFServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Service::class);
    }
}