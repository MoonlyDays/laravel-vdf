<?php

namespace MoonlyDays\LaravelVDF;

/*
|-----------------------------------------------------------------------
| Copyright (c) Rossen Popov, 2015-2016
|-----------------------------------------------------------------------
| This class uses modified code from:
| https://github.com/rossengeorgiev/vdf-parser/blob/master/vdf.php
|
| License:
| https://github.com/rossengeorgiev/vdf-parser/blob/master/LICENSE
|-----------------------------------------------------------------------
*/

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Fluent;
use Illuminate\Support\Traits\EnumeratesValues;

class Service
{
    use EnumeratesValues;

    /**
     * @throws VDFException
     */
    public function decode(string $text): Fluent
    {
        // detect and convert utf-16, utf-32 and convert to utf8
        if (str_starts_with($text, "\xFE\xFF")) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-16BE');
        } else {
            if (str_starts_with($text, "\xFF\xFE")) {
                $text = mb_convert_encoding($text, 'UTF-8', 'UTF-16LE');
            } else {
                if (str_starts_with($text, "\x00\x00\xFE\xFF")) {
                    $text = mb_convert_encoding($text, 'UTF-8', 'UTF-32BE');
                } else {
                    if (str_starts_with($text, "\xFF\xFE\x00\x00")) {
                        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-32LE');
                    }
                }
            }
        }

        // strip BOM
        $text = ltrim($text, "\xfe\xef\xbb\xff\xbf");

        $lines = preg_split('/\n/', $text);

        $arr = array();
        $stack = array(0 => &$arr);
        $expect_bracket = false;

        $re_keyvalue = '~^("(?P<qkey>(?:\\\\.|[^\\\\"])+)"|(?P<key>[a-z0-9\\-\\_]+))'.
            '([ \t]*('.
            '"(?P<qval>(?:\\\\.|[^\\\\"])*)(?P<vq_end>")?'.
            '|(?P<val>[a-z0-9\\-\\_]+)'.
            '))?~iu';

        $j = count($lines);
        for ($i = 0; $i < $j; $i++) {
            $line = trim($lines[$i]);

            // skip empty and comment lines
            if ($line == "" || $line[0] == '/') {
                continue;
            }

            // one level deeper
            if ($line[0] == "{") {
                $expect_bracket = false;
                continue;
            }

            if ($expect_bracket) {
                throw new VDFException("Invalid syntax, expected a '}' on line ".($i + 1));
            }

            // one level back
            if ($line[0] == "}") {
                array_pop($stack);
                continue;
            }

            // necessary for multiline values
            while (true) {
                preg_match($re_keyvalue, $line, $m);

                if (!$m) {
                    throw new VDFException("invalid syntax on line ".($i + 1));
                }

                $key = (isset($m['key']) && $m['key'] !== "")
                    ? $m['key']
                    : $m['qkey'];
                $val = (isset($m['qval']) && (!isset($m['vq_end']) || $m['vq_end'] !== ""))
                    ? $m['qval']
                    : ($m['val'] ?? false);

                if ($val === false) {
                    // chain (merge*) duplicate key
                    if (!isset($stack[count($stack) - 1][$key])) {
                        $stack[count($stack) - 1][$key] = array();
                    }
                    $stack[] = &$stack[count($stack) - 1][$key];
                    $expect_bracket = true;
                } else {
                    // if you don't match a closing quote for value, we consume one more line, until we find it
                    if (!isset($m['vq_end']) && isset($m['qval'])) {
                        $line .= "\n".$lines[++$i];
                        continue;
                    }

                    $stack[count($stack) - 1][$key] = $val;
                }
                break;
            }
        }

        if (count($stack) !== 1) {
            throw new VDFException("Open parentheses somewhere");
        }

        return fluent($arr);
    }

    public function encode(Arrayable|array $arr, bool $pretty = false): ?string
    {
        return $this->encode_step($this->getArrayableItems($arr), $pretty, 0);
    }

    private function encode_step(array $arr, bool $pretty, int $level): ?string
    {
        $buf = "";
        $line_indent = ($pretty) ? str_repeat("\t", $level) : "";

        foreach ($arr as $k => $v) {
            if (is_string($v)) {
                $buf .= "$line_indent\"$k\" \"$v\"\n";
            } else {
                $res = $this->encode_step($v, $pretty, $level + 1);
                if ($res === null) {
                    return null;
                }

                $buf .= "$line_indent\"$k\"\n$line_indent{\n$res$line_indent}\n";
            }
        }

        return $buf;
    }
}