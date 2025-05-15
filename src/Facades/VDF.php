<?php

namespace MoonlyDays\LaravelVDF\Facades;

use Illuminate\Support\Facades\Facade;
use MoonlyDays\LaravelVDF\VDFService;

class VDF extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return VDFService::class;
    }
}
