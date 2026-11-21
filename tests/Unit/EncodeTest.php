<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use MoonlyDays\LaravelVdf\VdfService;

it('encodes nested arrays', function (): void {
    $encoded = app(VdfService::class)->encode(['root' => ['a' => '1', 'child' => ['b' => '2']]]);

    expect($encoded)->toBe("\"root\"\n{\n\"a\" \"1\"\n\"child\"\n{\n\"b\" \"2\"\n}\n}\n");
});

it('indents with tabs when pretty', function (): void {
    $encoded = app(VdfService::class)->encode(['root' => ['a' => '1', 'child' => ['b' => '2']]], pretty: true);

    expect($encoded)->toBe("\"root\"\n{\n\t\"a\" \"1\"\n\t\"child\"\n\t{\n\t\t\"b\" \"2\"\n\t}\n}\n");
});

it('escapes quotes and backslashes', function (): void {
    expect(app(VdfService::class)->encode(['say "hi"' => 'C:\\path\\"x"']))
        ->toBe("\"say \\\"hi\\\"\" \"C:\\\\path\\\\\\\"x\\\"\"\n");
});

it('encodes Arrayable input and numeric scalars', function (): void {
    expect(app(VdfService::class)->encode(new Collection(['a' => new Collection(['b' => 1, 'c' => 1.5])])))
        ->toBe("\"a\"\n{\n\"b\" \"1\"\n\"c\" \"1.5\"\n}\n");
});

it('round-trips through decode', function (bool $pretty): void {
    $data = [
        'root' => [
            'plain' => 'value',
            'quote' => 'say "hi"',
            'backslash' => 'models\\player\\new',
            'escapes' => 'literal \\n and \\t and \\"',
            'newline' => "first\nsecond",
            'tab' => "a\tb",
            'comment' => 'http://example.com // not a comment',
            'braces' => '{1 2 3}',
            'brackets' => '[$X360]',
            'empty' => '',
            'key "quoted"' => 'x',
            'trailing\\' => 'y',
            'nested' => ['deeper' => ['a' => '1'], 'empty' => []],
        ],
    ];

    $service = app(VdfService::class);

    expect($service->decode($service->encode($data, $pretty)))->toBe($data);
})->with(['compact' => false, 'pretty' => true]);
