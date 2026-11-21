<?php

declare(strict_types=1);

namespace MoonlyDays\LaravelVdf;

/**
 * Tokenizer and recursive-descent parser for Valve's KeyValues text format,
 * following the rules of tier1/KeyValues.cpp.
 *
 * @internal
 */
final class Parser
{
    private const int EOF = 0;

    private const int QUOTED = 1;

    private const int BARE = 2;

    private const int OPEN = 3;

    private const int CLOSE = 4;

    private const int CONDITIONAL = 5;

    private const string WHITESPACE = " \t\r\n\f\v";

    /** Characters that end a bare (unquoted) token. */
    private const string BARE_END = " \t\r\n\f\v\"{}";

    /** A platform conditional such as `[$X360]`, `[!$WIN32]` or `[$WIN32||$OSX]`. */
    private const string CONDITIONAL_PATTERN = '/^\[!?\$\w+(?:\s*(?:&&|\|\|)\s*!?\$\w+)*\]$/';

    /** Escape sequences decoded inside quoted strings; any other `\x` stays literal. */
    private const array ESCAPES = ['\\"' => '"', '\\\\' => '\\', '\\n' => "\n", '\\t' => "\t"];

    private const array DIRECTIVES = ['#include', '#base'];

    private readonly int $length;

    private int $pos = 0;

    /** Byte offset where the last token started. */
    private int $start = 0;

    /** Text of the last quoted, bare or conditional token. */
    private string $value = '';

    public function __construct(private readonly string $text)
    {
        $this->length = mb_strlen($text, '8bit');
    }

    /**
     * @throws VdfException
     */
    public function parse(): array
    {
        $result = [];
        $this->parseBlock($result, null);

        return $result;
    }

    /**
     * Parse key/value pairs into $into until the closing brace (or EOF at the top level,
     * where $openedAt is null).
     *
     * @throws VdfException
     */
    private function parseBlock(array &$into, ?int $openedAt): void
    {
        while (true) {
            $type = $this->next();

            if ($type === self::CONDITIONAL) {
                continue;
            }

            if ($type === self::EOF) {
                if ($openedAt === null) {
                    return;
                }

                throw new VdfException('Unclosed block opened on line '.$this->line($openedAt));
            }

            if ($type === self::CLOSE) {
                if ($openedAt !== null) {
                    return;
                }

                throw new VdfException("Unexpected '}' on line ".$this->line($this->start));
            }

            if ($type === self::OPEN) {
                throw new VdfException("Unexpected '{', expected a key on line ".$this->line($this->start));
            }

            $key = $this->value;
            $keyAt = $this->start;

            if ($openedAt === null && $type === self::BARE && in_array(mb_strtolower($key), self::DIRECTIVES, true)) {
                $this->skipDirective($key, $keyAt);

                continue;
            }

            do {
                $type = $this->next();
            } while ($type === self::CONDITIONAL);

            if ($type === self::QUOTED || $type === self::BARE) {
                $into[$key] = $this->value;

                continue;
            }

            if ($type === self::OPEN) {
                // Duplicate block keys merge into the existing block.
                if (! isset($into[$key]) || ! is_array($into[$key])) {
                    $into[$key] = [];
                }

                $this->parseBlock($into[$key], $this->start);

                continue;
            }

            throw new VdfException(sprintf('Key "%s" on line %d has no value', $key, $this->line($keyAt)));
        }
    }

    /**
     * Consume the file name after an `#include` / `#base` directive. Files are not resolved.
     *
     * @throws VdfException
     */
    private function skipDirective(string $directive, int $at): void
    {
        $type = $this->next();

        if ($type !== self::QUOTED && $type !== self::BARE) {
            throw new VdfException(sprintf('Directive "%s" on line %d has no file name', $directive, $this->line($at)));
        }
    }

    /**
     * Read the next token, skipping whitespace and `//` comments.
     *
     * @throws VdfException
     */
    private function next(): int
    {
        $text = $this->text;

        while (true) {
            $this->pos += strspn($text, self::WHITESPACE, $this->pos);

            if ($this->pos >= $this->length) {
                return self::EOF;
            }

            if ($text[$this->pos] !== '/' || ($text[$this->pos + 1] ?? '') !== '/') {
                break;
            }

            $eol = mb_strpos($text, "\n", $this->pos, '8bit');
            $this->pos = $eol === false ? $this->length : $eol + 1;
        }

        $this->start = $this->pos;
        $char = $text[$this->pos];

        if ($char === '"') {
            return $this->quoted();
        }

        if ($char === '{') {
            $this->pos++;

            return self::OPEN;
        }

        if ($char === '}') {
            $this->pos++;

            return self::CLOSE;
        }

        // A bracketed token like `[$X360]` or `[1 1 1]` may contain spaces, but not newlines.
        if ($char === '[' && preg_match('/\G\[[^\]\r\n]*\]/', $text, $match, 0, $this->pos) === 1) {
            $this->value = $match[0];
            $this->pos += mb_strlen($match[0], '8bit');

            return preg_match(self::CONDITIONAL_PATTERN, $match[0]) === 1 ? self::CONDITIONAL : self::BARE;
        }

        $length = strcspn($text, self::BARE_END, $this->pos);
        $this->value = mb_substr($text, $this->pos, $length, '8bit');
        $this->pos += $length;

        return self::BARE;
    }

    /**
     * @throws VdfException
     */
    private function quoted(): int
    {
        $text = $this->text;
        $pos = $this->start + 1;

        while (true) {
            if ($pos >= $this->length) {
                throw new VdfException('Unterminated quoted string starting on line '.$this->line($this->start));
            }

            $end = $pos + strcspn($text, '"\\', $pos);

            if ($end >= $this->length) {
                throw new VdfException('Unterminated quoted string starting on line '.$this->line($this->start));
            }

            if ($text[$end] === '"') {
                break;
            }

            // Skip the backslash and the character it escapes.
            $pos = $end + 2;
        }

        $value = mb_substr($text, $this->start + 1, $end - $this->start - 1, '8bit');
        $this->value = str_contains($value, '\\') ? strtr($value, self::ESCAPES) : $value;
        $this->pos = $end + 1;

        return self::QUOTED;
    }

    private function line(int $offset): int
    {
        return mb_substr_count(mb_substr($this->text, 0, $offset, '8bit'), "\n", '8bit') + 1;
    }
}
