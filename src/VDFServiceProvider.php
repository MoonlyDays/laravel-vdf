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
        $this->app->singleton(Service::class);

        Response::macro('vdf', function (?string $key = null, mixed $default = null) {
            /** @var Response $this */
            $body = $this->body();
            $decoded = app(Service::class)->decode($body);

            if (is_null($key)) {
                return $decoded;
            }

            return data_get($decoded, $key, $default);
        });

        Filesystem::macro('vdf', function (string $path) {
            /** @var Filesystem $this */
            return app(Service::class)->decode($this->get($path));
        });

        FilesystemAdapter::macro('vdf', function (string $path) {
            /** @var Filesystem $this */
            return app(Service::class)->decode($this->get($path));
        });
    }
}
