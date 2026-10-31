<?php
namespace SuperSoft\Plugin\Content\Sources\Support;

/** Narrow opt-in boundary: other Joomla fields and content plugins still run normally. */
final class SourceFieldData
{
    private static int $dataOnly = 0;
    private static array $active = [];

    public static function withoutRendering(callable $consumer)
    {
        self::$dataOnly++;
        try { return $consumer(); } finally { self::$dataOnly--; }
    }

    public static function withRendering(callable $consumer)
    {
        $previous = self::$dataOnly; self::$dataOnly = 0;
        try { return $consumer(); } finally { self::$dataOnly = $previous; }
    }

    public static function configuration(object $field): ?array
    {
        if (($field->type ?? 'sources') !== 'sources') { return null; }
        $raw = $field->rawvalue ?? $field->value ?? '';
        if (!is_string($raw) || trim($raw) === '') { return null; }
        try { return InlineConfig::decode($raw); } catch (\Throwable $error) { return null; }
    }

    public static function render(object $item, object $field, callable $render): string
    {
        if (self::$dataOnly || count(self::$active) >= 3) { return ''; }
        $key = hash('sha256', serialize([$item->id ?? 0, $field->id ?? $field->name ?? '',
            $field->rawvalue ?? $field->value ?? '']));
        if (isset(self::$active[$key])) { return ''; }
        self::$active[$key] = true;
        try { return (string) $render(); } finally { unset(self::$active[$key]); }
    }
}
