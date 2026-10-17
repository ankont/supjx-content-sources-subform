<?php
namespace SuperSoft\Plugin\Content\Sources\Support;

final class InlineTokens
{
    /** Locate complete JSON objects without interpreting braces inside strings. Offsets are bytes. */
    public static function scan(string $text): array
    {
        $tokens = [];
        $offset = 0;
        $budget = max(strlen($text), InlineConfig::MAX_BYTES) * 4;
        while (($start = strpos($text, '{sources:', $offset)) !== false) {
            $markup = self::skipMarkup($text, $offset, $start);
            if ($markup > $start) { $offset = $markup; continue; }
            $jsonStart = $start + 9;
            while (isset($text[$jsonStart]) && ctype_space($text[$jsonStart])) {
                $jsonStart++;
            }
            if (($text[$jsonStart] ?? '') !== '{') {
                $offset = $start + 9;
                continue;
            }
            $depth = 0;
            $string = false;
            $escaped = false;
            $end = null;
            $bound = min(strlen($text), $jsonStart + InlineConfig::MAX_BYTES + 1);
            for ($i = $jsonStart; $i < $bound; $i++) {
                if (--$budget <= 0) { break; }
                $char = $text[$i];
                if ($string) {
                    if ($escaped) { $escaped = false; }
                    elseif ($char === '\\') { $escaped = true; }
                    elseif ($char === '"') { $string = false; }
                    continue;
                }
                if ($char === '"') { $string = true; }
                elseif ($char === '{' || $char === '[') { $depth++; }
                elseif ($char === '}' || $char === ']') {
                    $depth--;
                    if ($depth === 0) { $end = $i + 1; break; }
                }
            }
            if ($budget <= 0 || $end === null) { break; }
            $close = $end;
            while ($close !== null && isset($text[$close]) && ctype_space($text[$close])) { $close++; }
            if ($end !== null && ($text[$close] ?? '') === '}') {
                $raw = substr($text, $start, $close + 1 - $start);
                $config = null;
                try { $config = InlineConfig::decode(substr($text, $jsonStart, $end - $jsonStart)); }
                catch (\Throwable $error) { /* Preserve invalid and future-version tokens verbatim. */ }
                $tokens[] = ['start' => $start, 'end' => $close + 1, 'raw' => $raw, 'config' => $config];
                $offset = $close + 1;
            } else {
                break;
            }
        }
        return $tokens;
    }

    private static function skipMarkup(string $text, int $from, int $until): int
    {
        while (($tag = strpos($text, '<', $from)) !== false && $tag < $until) {
            if (substr($text, $tag, 4) === '<!--') {
                $end = strpos($text, '-->', $tag + 4);
                $from = $end === false ? strlen($text) : $end + 3;
            } elseif (preg_match('/^<\/?([a-z][a-z0-9:-]*)\b/i', substr($text, $tag, 150), $match)) {
                $quote = null;
                $end = strlen($text) - 1;
                for ($i = $tag + 1, $length = strlen($text); $i < $length; $i++) {
                    $char = $text[$i];
                    if ($quote !== null) { if ($char === $quote) { $quote = null; } }
                    elseif ($char === '"' || $char === "'") { $quote = $char; }
                    elseif ($char === '>') { $end = $i; break; }
                }
                $from = $end + 1;
                if ($text[$tag + 1] !== '/' && in_array(strtolower($match[1]), ['script', 'style', 'pre', 'code', 'textarea'], true)) {
                    $close = stripos($text, '</' . $match[1], $from);
                    $from = $close === false ? strlen($text) : (strpos($text, '>', $close) ?: strlen($text) - 1) + 1;
                }
            } else { $from = $tag + 1; }
            if ($from > $until) { return $from; }
        }
        return $until;
    }

    public static function replace(string $text, callable $render): string
    {
        foreach (array_reverse(self::scan($text)) as $token) {
            if ($token['config'] !== null) {
                $text = substr_replace($text, $render($token['config']), $token['start'], $token['end'] - $token['start']);
            }
        }
        return $text;
    }
}
