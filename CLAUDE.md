# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Laravel package for Valve's VDF / KeyValues text format, published as `moonlydays/laravel-vdf` on Packagist. Requires PHP 8.5+, `ext-mbstring` and Laravel 13. Its main real-world consumer decodes TF2's `items_game.txt` (~8 MB) and Source material files (`.vmt`).

## Commands

- `composer test`: run the Pest 5 suite (`tests/Unit` + `tests/Feature`, on Orchestra Testbench). Filter with `vendor/bin/pest --filter='merges duplicate block keys'`.
- `composer lint`: Laravel Pint (parallel), then Rector (`rector.php`). Run it after every change. The `pint.json` preset is strict and includes **`mb_str_functions`**: Pint rewrites `strlen`/`substr`/`strpos`/`ltrim`/… to their character-based `mb_*` versions.

## Architecture

- **`VdfService`** (`src/VdfService.php`): the public API, bound as a singleton. `decode()` converts UTF-16/UTF-32 (by BOM) to UTF-8, strips BOM bytes, and hands the text to `Parser`. `encode()` writes nested arrays, quoting and escaping (`addcslashes`) every key and value.
- **`Parser`** (`src/Parser.php`, `@internal`): a byte-level tokenizer (`next()`, `quoted()`) plus a recursive-descent `parseBlock()`, following `tier1/KeyValues.cpp`. The README's "Supported syntax" section lists the rules. Errors are raised as `VdfException` with a line number. `line()` computes it only on the error path.
- **`VdfServiceProvider`**: registers the singleton and the `vdf()` macros on `Illuminate\Http\Client\Response`, `Illuminate\Filesystem\Filesystem` and `Illuminate\Filesystem\FilesystemAdapter`.
- **`Facades\Vdf`**: facade for `VdfService`, aliased as `Vdf`.

### Things to know when editing

- **Output compatibility matters.** Values stay strings (never cast), keys stay case-sensitive, duplicate block keys merge and duplicate scalar keys overwrite. A change to the parser must leave `items_game.txt` decoding to an identical array. Check against a real copy (`tf/scripts/items/items_game.txt` in a TF2 install) with a throwaway script outside the repo, comparing against the previous release, and don't commit it.
- **Performance:** the parser scans the byte string with `strspn`/`strcspn` and anchored `preg_match` at an offset. Don't split the input into characters or lines, and don't build token arrays for the whole file.
- **Byte offsets** use `mb_strlen($s, '8bit')`, `mb_substr(..., '8bit')` and `mb_strpos(..., '8bit')`, because Pint rewrites the plain functions. For byte trimming, use a non-`/u` `preg_replace` (`ltrim` would become `mb_ltrim`, which does not handle the BOM bytes).
- A bare token ends only at whitespace, `"`, `{` or `}`, as in Valve's parser, so `2//x` is one token. Only `//` at the start of a token begins a comment.
- Backslash escapes other than `\"`, `\\`, `\n`, `\t` stay literal, so Windows paths in VMTs survive.
