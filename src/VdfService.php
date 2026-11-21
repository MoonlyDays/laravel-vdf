<?php

declare(strict_types=1);

namespace MoonlyDays\LaravelVdf;

use Illuminate\Contracts\Support\Arrayable;

final class VdfService
{
    /**
     * Decode VDF / KeyValues text into a nested array. Scalar values are kept as strings.
     *
     * @throws VdfException
     */
    public function decode(string $text): array
    {
        // detect UTF-16 / UTF-32 by their BOM and convert to UTF-8
        if (str_starts_with($text, "\xFE\xFF")) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-16BE');
        } elseif (str_starts_with($text, "\xFF\xFE")) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-16LE');
        } elseif (str_starts_with($text, "\x00\x00\xFE\xFF")) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-32BE');
        } elseif (str_starts_with($text, "\xFF\xFE\x00\x00")) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-32LE');
        }

        // strip BOM bytes (a byte regex, since Pint would rewrite ltrim() to the character-based mb_ltrim())
        $text = preg_replace('/^[\xFE\xEF\xBB\xFF\xBF]+/', '', $text);

        return new Parser($text)->parse();
    }

    /**
     * Encode an array as VDF / KeyValues text.
     */
    public function encode(Arrayable|array $arr, bool $pretty = false): string
    {
        return $this->encodeStep($arr instanceof Arrayable ? $arr->toArray() : $arr, $pretty, 0);
    }

    private function encodeStep(array $arr, bool $pretty, int $level): string
    {
        $buf = '';
        $indent = $pretty ? str_repeat("\t", $level) : '';

        foreach ($arr as $key => $value) {
            $key = $this->quote((string) $key);

            if ($value instanceof Arrayable) {
                $value = $value->toArray();
            }

            if (is_array($value)) {
                $buf .= "{$indent}{$key}\n{$indent}{\n".$this->encodeStep($value, $pretty, $level + 1)."{$indent}}\n";
            } else {
                $buf .= "{$indent}{$key} ".$this->quote((string) $value)."\n";
            }
        }

        return $buf;
    }

    private function quote(string $value): string
    {
        return '"'.addcslashes($value, '"\\').'"';
    }
}
