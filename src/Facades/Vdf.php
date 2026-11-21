<?php

declare(strict_types=1);

namespace MoonlyDays\LaravelVdf\Facades;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Facade;
use MoonlyDays\LaravelVdf\VdfService;

/**
 * @method static array decode(string $text)
 * @method static string encode(Arrayable|array $arr, bool $pretty = false)
 *
 * @see VdfService
 */
class Vdf extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return VdfService::class;
    }
}
