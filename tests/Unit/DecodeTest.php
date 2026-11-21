<?php

declare(strict_types=1);

use MoonlyDays\LaravelVdf\VdfException;

it('decodes quoted keys, values and nested blocks', function (): void {
    expect(vdf(<<<'TEXT'
        "root"
        {
            "name" "value"
            "child"
            {
                "a" "1"
            }
        }
        TEXT))->toBe(['root' => ['name' => 'value', 'child' => ['a' => '1']]]);
});

it('keeps scalars as strings', function (): void {
    expect(vdf('"a" "1" "b" "0.5"'))->toBe(['a' => '1', 'b' => '0.5']);
});

it('decodes unquoted values with any non-delimiter characters', function (string $value): void {
    expect(vdf("\"root\"\n{\n\t\"key\" {$value}\n\t\"next\" \"x\"\n}"))
        ->toBe(['root' => ['key' => $value, 'next' => 'x']]);
})->with(['.01', '-1.5', 'models/foo/bar', 'models\\foo', 'a_b-c', '$var', 'http://example.com/x']);

it('decodes an unquoted bracketed vector as one value', function (): void {
    expect(vdf("\"\$color\" [1 1 1]\n\"\$alpha\" 1"))->toBe(['$color' => '[1 1 1]', '$alpha' => '1']);
});

it('decodes unquoted keys with $, % and .', function (): void {
    expect(vdf("\$basetexture \"models/x\"\n%keywords \"tf\"\nmy.key value"))
        ->toBe(['$basetexture' => 'models/x', '%keywords' => 'tf', 'my.key' => 'value']);
});

it('accepts braces that are not alone on their line', function (): void {
    expect(vdf('"Proxies" { "Sine" { "a" "b" } "Foo" {'."\n".'"c" "d" } }'))
        ->toBe(['Proxies' => ['Sine' => ['a' => 'b'], 'Foo' => ['c' => 'd']]]);
});

it('accepts several pairs on one line', function (): void {
    expect(vdf('"a" "1" "b" "2" c 3'))->toBe(['a' => '1', 'b' => '2', 'c' => '3']);
});

it('lets a pair span several lines', function (): void {
    expect(vdf("\"a\"\n\n   \"1\"\n\"b\"\n{\n}"))->toBe(['a' => '1', 'b' => []]);
});

it('ignores // comments outside quotes', function (): void {
    expect(vdf(<<<'TEXT'
        // leading comment
        "root" // after a key
        {
            "a" "1" // after a quoted value
            "b" 2 // after a bare value
            "c" 3//
            // "d" "4"
        } // after a brace
        TEXT))->toBe(['root' => ['a' => '1', 'b' => '2', 'c' => '3//']]);
});

it('ends a bare token only at whitespace, a quote or a brace, like Valve', function (): void {
    expect(vdf('"a" b//c "d" e"f" "g" block{"h" i}'))
        ->toBe(['a' => 'b//c', 'd' => 'e', 'f' => 'g', 'block' => ['h' => 'i']]);
});

it('keeps // inside a quoted value', function (): void {
    expect(vdf('"url" "http://example.com" // real comment'))->toBe(['url' => 'http://example.com']);
});

it('decodes escape sequences in quoted strings', function (): void {
    expect(vdf('"a" "say \"hi\"" "b" "back\\\\slash" "c" "line\\nbreak" "d" "tab\\there" "e\\"q" "x"'))
        ->toBe(['a' => 'say "hi"', 'b' => 'back\\slash', 'c' => "line\nbreak", 'd' => "tab\there", 'e"q' => 'x']);
});

it('keeps the backslash for unknown escape sequences', function (): void {
    expect(vdf('"$basetexture" "models\player\items\scout\hat"'))
        ->toBe(['$basetexture' => 'models\player\items\scout\hat']);
});

it('keeps newlines in multi-line quoted values', function (): void {
    expect(vdf("\"text\" \"first\nsecond\r\nthird\"\n\"next\" \"x\""))
        ->toBe(['text' => "first\nsecond\r\nthird", 'next' => 'x']);
});

it('ignores conditionals after a value or a block key', function (): void {
    expect(vdf(<<<'TEXT'
        "root"
        {
            "a" "1" [$X360]
            "b" "2" [!$WIN32]
            "c" 3 [$WIN32||$OSX]
            "block" [$WINDOWS&&!$X360]
            {
                "d" "4"
            }
        }
        TEXT))->toBe(['root' => ['a' => '1', 'b' => '2', 'c' => '3', 'block' => ['d' => '4']]]);
});

it('treats a quoted bracketed value as an ordinary value', function (): void {
    expect(vdf('"$color" "[1 1 1]" "$cond" "[$X360]"'))->toBe(['$color' => '[1 1 1]', '$cond' => '[$X360]']);
});

it('skips top-level #include and #base directives', function (): void {
    expect(vdf("#base \"base.vdf\"\n#include \"other.vdf\"\n#BASE unquoted.vdf\n\"a\" \"1\""))
        ->toBe(['a' => '1']);
});

it('merges duplicate block keys', function (): void {
    expect(vdf('"r" { "x" { "a" "1" "b" "1" } "x" { "b" "2" "c" "3" } }'))
        ->toBe(['r' => ['x' => ['a' => '1', 'b' => '2', 'c' => '3']]]);
});

it('lets the last duplicate scalar key win', function (): void {
    expect(vdf('"a" "1" "a" "2"'))->toBe(['a' => '2']);
});

it('keeps keys case-sensitive', function (): void {
    expect(vdf('"Key" "1" "key" "2"'))->toBe(['Key' => '1', 'key' => '2']);
});

it('decodes empty input and empty quoted strings', function (): void {
    expect(vdf(''))->toBe([])
        ->and(vdf("  \n// nothing\n"))->toBe([])
        ->and(vdf('"" ""'))->toBe(['' => '']);
});

it('converts UTF-16 and UTF-32 input with a BOM', function (string $encoding, string $bom): void {
    $text = $bom.mb_convert_encoding('"key" "välue"', $encoding, 'UTF-8');

    expect(vdf($text))->toBe(['key' => 'välue']);
})->with([
    'UTF-16LE' => ['UTF-16LE', "\xFF\xFE"],
    'UTF-16BE' => ['UTF-16BE', "\xFE\xFF"],
    'UTF-32BE' => ['UTF-32BE', "\x00\x00\xFE\xFF"],
]);

it('strips a UTF-8 BOM', function (): void {
    expect(vdf("\xEF\xBB\xBF\"a\" \"1\""))->toBe(['a' => '1']);
});

it('throws with a line number on malformed input', function (string $text, string $message): void {
    expect(fn (): array => vdf($text))->toThrow(VdfException::class, $message);
})->with([
    'unterminated quote' => ["\"a\" \"1\"\n\"b\" \"oops\n", 'Unterminated quoted string starting on line 2'],
    'unterminated after escape' => ['"a" "ends with \\', 'Unterminated quoted string starting on line 1'],
    'unexpected }' => ["\"a\" \"1\"\n}", "Unexpected '}' on line 2"],
    'unclosed block' => ["\"a\"\n{\n\"b\"\n{\n}", 'Unclosed block opened on line 2'],
    'key without value at EOF' => ["\"a\" \"1\"\n\"b\"", 'Key "b" on line 2 has no value'],
    'key followed by }' => ["\"a\"\n{\n\"b\"\n}", 'Key "b" on line 3 has no value'],
    'stray {' => ['{ "a" "1" }', "Unexpected '{', expected a key on line 1"],
    'directive without file' => ['#base', 'Directive "#base" on line 1 has no file name'],
]);

it('decodes a realistic VMT', function (): void {
    $vmt = <<<'VMT'
        "VertexlitGeneric"
        {
                "$baseTexture" "models/player/items/all_class/bdayhat"
                "$detailblendfactor" .01
                "$detailblendmode" 6
                $phong 1
                "$colortint_base" "{149 47 45}"   // comment after value
                "$colortint_tmp" "[0 0 0]"
                "Proxies"
                {
                        "ItemTintColor" { "resultVar" "$colortint_tmp" }
                        "SelectFirstIfNonZero"
                        {
                                "srcVar1" "$colortint_tmp"
                                "srcVar2" "$colortint_base"
                                "resultVar" "$color2"
                        }
                }
        }
        VMT;

    expect(vdf($vmt))->toBe([
        'VertexlitGeneric' => [
            '$baseTexture' => 'models/player/items/all_class/bdayhat',
            '$detailblendfactor' => '.01',
            '$detailblendmode' => '6',
            '$phong' => '1',
            '$colortint_base' => '{149 47 45}',
            '$colortint_tmp' => '[0 0 0]',
            'Proxies' => [
                'ItemTintColor' => ['resultVar' => '$colortint_tmp'],
                'SelectFirstIfNonZero' => [
                    'srcVar1' => '$colortint_tmp',
                    'srcVar2' => '$colortint_base',
                    'resultVar' => '$color2',
                ],
            ],
        ],
    ]);
});
