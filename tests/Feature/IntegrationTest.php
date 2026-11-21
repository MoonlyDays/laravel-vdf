<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use MoonlyDays\LaravelVdf\Facades\Vdf;
use MoonlyDays\LaravelVdf\VdfService;

it('resolves the service as a singleton', function (): void {
    expect(app(VdfService::class))->toBe(app(VdfService::class));
});

it('decodes and encodes through the facade', function (): void {
    expect(Vdf::decode('"a" { "b" "c" }'))->toBe(['a' => ['b' => 'c']])
        ->and(Vdf::encode(['a' => 'b']))->toBe("\"a\" \"b\"\n");
});

it('adds a vdf() macro to HTTP client responses', function (): void {
    Http::fake(['example.com/*' => Http::response('"root" { "a" { "b" "c" } }')]);

    $response = Http::get('https://example.com/file.vdf');

    expect($response->vdf())->toBe(['root' => ['a' => ['b' => 'c']]])
        ->and($response->vdf('root.a.b'))->toBe('c')
        ->and($response->vdf('root.missing', 'default'))->toBe('default');
});

it('adds a vdf() macro to Filesystem', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'vdf');
    file_put_contents($path, '"a" "1"');

    try {
        expect(new Filesystem()->vdf($path))->toBe(['a' => '1']);
    } finally {
        unlink($path);
    }
});

it('adds a vdf() macro to filesystem disks', function (): void {
    Storage::fake('local')->put('scripts/items.txt', '"items" { "1" { "name" "Hat" } }');

    expect(Storage::disk('local')->vdf('scripts/items.txt'))->toBe(['items' => ['1' => ['name' => 'Hat']]]);
});
