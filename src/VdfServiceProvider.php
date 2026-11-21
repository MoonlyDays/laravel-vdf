<?php

declare(strict_types=1);

namespace MoonlyDays\LaravelVdf;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Client\Response;
use Illuminate\Support\ServiceProvider;

class VdfServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VdfService::class);

        Response::macro('vdf', function (?string $key = null, mixed $default = null) {
            $decoded = app(VdfService::class)->decode($this->body());

            if ($key === null) {
                return $decoded;
            }

            return data_get($decoded, $key, $default);
        });

        Filesystem::macro('vdf', fn (string $path) => app(VdfService::class)->decode($this->get($path)));

        FilesystemAdapter::macro('vdf', fn (string $path) => app(VdfService::class)->decode($this->get($path)));
    }
}
