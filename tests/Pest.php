<?php

declare(strict_types=1);

use MoonlyDays\LaravelVdf\VdfService;

pest()->extend(Tests\TestCase::class)->in('Feature', 'Unit');

function vdf(string $text): array
{
    return app(VdfService::class)->decode($text);
}
