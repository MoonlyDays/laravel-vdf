<?php

namespace MoonlyDays\LaravelVDF;

/*
|-----------------------------------------------------------------------
| Copyright (c) Rossen Popov, 2015-2016
|-----------------------------------------------------------------------
| This class uses modified code from:
| https://github.com/rossengeorgiev/vdf-parser/blob/master/vdf.php
|
| License: https://github.com/rossengeorgiev/vdf-parser/blob/master/LICENSE
|-----------------------------------------------------------------------
*/

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Fluent;
use Illuminate\Support\Traits\EnumeratesValues;

require_once 'vdf.php';

class Service
{
    use EnumeratesValues;

    public function decode(string $text)
    {
        return vdf_decode($text);
    }

    public function encode(Arrayable|array $arr, bool $pretty = false): string|false
    {
        return $this->encode_step($this->getArrayableItems($arr), $pretty, 0);
    }

    private function encode_step(array $arr, bool $pretty, int $level): string|false
    {
        $buf = "";
        $line_indent = ($pretty) ? str_repeat("\t", $level) : "";

        foreach ($arr as $k => $v) {
            if (is_string($v)) {
                $buf .= "$line_indent\"$k\" \"$v\"\n";
            } else {
                $res = $this->encode_step($v, $pretty, $level + 1);
                if ($res === false) {
                    return false;
                }

                $buf .= "$line_indent\"$k\"\n$line_indent{\n$res$line_indent}\n";
            }
        }

        return $buf;
    }
}