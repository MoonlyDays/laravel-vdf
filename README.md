# Laravel Vdf

Read and write Valve's VDF / KeyValues text format in Laravel: Source engine scripts such as TF2's
`items_game.txt`, material files (`.vmt`), Steam's `.vdf` and `.acf` files, and so on.

## Installation

```bash
composer require moonlydays/laravel-vdf
```

Requires PHP 8.5 and Laravel 13. The service provider and the `Vdf` facade are auto-discovered.

## Usage

```php
use MoonlyDays\LaravelVdf\Facades\Vdf;

$material = Vdf::decode(file_get_contents('bdayhat.vmt'));

$material['VertexlitGeneric']['$baseTexture']; // "models/player/items/all_class/bdayhat"

$text = Vdf::encode(['root' => ['key' => 'value']], pretty: true);
```

`decode()` returns nested arrays. Values are always strings, so `"5"` stays `"5"`. Keys are case-sensitive.
If a block key appears twice, the blocks are merged. If a value key appears twice, the last one wins. Invalid
input throws a `MoonlyDays\LaravelVdf\VdfException` whose message gives the line number.

`encode()` accepts an array or any `Arrayable` and escapes quotes and backslashes, so
`Vdf::decode(Vdf::encode($data)) === $data` holds for nested arrays of strings.

The package also adds a `vdf()` method to HTTP client responses, the `File` filesystem and storage disks:

```php
$schema = Storage::disk('tf')->vdf('scripts/items/items_game.txt');

$name = Http::get($url)->vdf('items_game.items.5021.name');
$config = File::vdf(base_path('config.vdf'));
```

## Supported syntax

The parser follows the text rules of Valve's `tier1/KeyValues.cpp`:

- **Tokens** are quoted strings (`"..."`), `{`, `}`, or bare words that run until whitespace, `"`, `{` or `}`.
  Bare keys and values may hold any other character: `$basetexture`, `%keywords`, `.01`, `-1.5`, `models/foo/bar`.
  A bare bracketed value on one line, like `[1 1 1]`, is read as one value.
- **Newlines carry no meaning.** A pair can span lines, a line can hold several pairs, and braces can share a
  line with anything else: `"Proxies" { "Sine" { "a" "b" } }`.
- **Comments** run from `//` to the end of the line, but only outside quotes.
- **Escape sequences** `\"`, `\\`, `\n` and `\t` are decoded inside quoted strings. Any other backslash is kept
  as written, so Windows paths such as `"models\player\hat"` survive unchanged.
- **Quoted values can span lines**, and their newlines are kept.
- **Platform conditionals** such as `[$X360]`, `[!$WIN32]` or `[$WIN32||$OSX]` after a value or block key are
  skipped and do not filter the pair. A quoted `"[$X360]"` is an ordinary value.
- **`#include` and `#base` directives** at the top level are skipped. The referenced files are not loaded.
- **UTF-16 and UTF-32** input with a byte order mark is converted to UTF-8, and a UTF-8 BOM is stripped.

## Development

```bash
composer test   # Pest
composer lint   # Pint, then Rector
```

## Credits

Earlier versions were based on [rossengeorgiev/vdf-parser](https://github.com/rossengeorgiev/vdf-parser)
([license](https://github.com/rossengeorgiev/vdf-parser/blob/master/LICENSE)).
