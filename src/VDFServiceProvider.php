<?php

namespace MoonlyDays\LaravelVDF;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Client\Response;
use Illuminate\Support\ServiceProvider;

class VDFServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VDFService::class);

        Response::macro('vdf', function (?string $key = null, mixed $default = null) {
            $decoded = app(VDFService::class)->decode($this->body());

            if (is_null($key)) {
                return $decoded;
            }

            return data_get($decoded, $key, $default);
        });

        Filesystem::macro('vdf', function (string $path) {
            return app(VDFService::class)->decode($this->get($path));
        });

        FilesystemAdapter::macro('vdf', function (string $path) {
            return app(VDFService::class)->decode($this->get($path));
        });
    }
}
