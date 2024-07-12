<?php

namespace MoonlyDays\LaravelVDF;

use Illuminate\Support\Facades\Facade as LaravelFacade;

class Facade extends LaravelFacade
{
    protected static function getFacadeAccessor(): string
    {
        return Service::class;
    }
}
