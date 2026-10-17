<?php
namespace SuperSoft\Plugin\Content\Sources\Support;

final class SelectionUsage
{
    public static function selections($values, string $adapter): array
    {
        if (!is_array($values) || !array_is_list($values) || count($values) > 500) { throw new \InvalidArgumentException('Invalid Sources selection.'); }
        $prefix = ['articles' => 'article', 'categories' => 'category', 'tags' => 'tag'][$adapter];
        $result = [];
        foreach ($values as $value) {
            $usage = [];
            if (is_int($value) || is_float($value) && is_finite($value) && floor($value) === $value) { $id = $value; }
            else {
                if (!is_object($value) || array_diff(array_keys(get_object_vars($value)), ['selection', 'usage'])) { throw new \InvalidArgumentException('Invalid Sources entry.'); }
                $ref = $value->selection ?? null;
                if (!is_object($ref) || array_diff(array_keys(get_object_vars($ref)), ['adapter', 'id'])
                    || ($ref->adapter ?? null) !== $adapter || !is_string($ref->id ?? null) || !preg_match('/^' . $prefix . ':([1-9][0-9]*)$/D', $ref->id, $match)) { throw new \InvalidArgumentException('Invalid Sources reference.'); }
                $id = (float) $match[1];
                $usage = self::values($value->usage ?? (object) [], $adapter);
            }
            if ($id < 1 || $id > 2147483647) { throw new \InvalidArgumentException('Invalid Sources identifier.'); }
            $id = (int) $id;
            $result[$id] ??= $usage ? ['selection' => ['adapter' => $adapter, 'id' => $prefix . ':' . $id], 'usage' => $usage] : $id;
        }
        return array_values($result);
    }

    public static function values($value, string $adapter): array
    {
        $nodes = 0; self::jsonValue($value, 0, $nodes);
        if (!is_object($value) || count(get_object_vars($value)) > 100 || strlen(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) > 65536) { throw new \InvalidArgumentException('Invalid Sources usage.'); }
        $allowed = ['visual.presentation', 'visual.thumbnailOverride', 'visual.iconOverride'];
        if ($adapter === 'articles') { $allowed[] = 'article.displayedName'; }
        $result = [];
        foreach (get_object_vars($value) as $key => $data) {
            if (!in_array($key, $allowed, true) && !preg_match('/^ext\.[a-zA-Z][a-zA-Z0-9_-]*(?:\.[a-zA-Z][a-zA-Z0-9_-]*)*$/D', $key)) { throw new \InvalidArgumentException('Unsupported Sources usage.'); }
            if ($data === null) { if (str_starts_with($key, 'ext.')) { $result[$key] = null; } continue; }
            $valid = match ($key) {
                'article.displayedName' => is_string($data) && preg_match_all('/./us', $data) <= 255,
                'visual.presentation' => in_array($data, ['thumbnail', 'icon', 'both', ''], true),
                'visual.iconOverride' => is_string($data) && strlen($data) <= 160 && ($data === '' || preg_match('/^[a-zA-Z0-9_-]+(?: [a-zA-Z0-9_-]+)*$/D', $data)),
                'visual.thumbnailOverride' => is_object($data) && !array_diff(array_keys(get_object_vars($data)), ['adapter', 'id'])
                    && ($data->adapter ?? null) === 'media' && is_string($data->id ?? null) && str_contains($data->id, ':') && strlen($data->id) <= 2048 && !preg_match('/[\x00-\x1f\x7f]/', $data->id),
                'ext.address' => is_string($data) && strlen($data) <= 2048 && !preg_match('/[\x00-\x1f\x7f]|^\s*(?:javascript|data|vbscript):/i', $data),
                default => true,
            };
            if (!$valid) { throw new \InvalidArgumentException('Invalid Sources usage property.'); }
            $result[$key] = $key === 'visual.thumbnailOverride' ? get_object_vars($data) : $data;
        }
        return $result;
    }

    public static function ids(array $values): array
    {
        return array_map(static fn($value) => is_int($value) ? $value : (int) substr($value['selection']['id'], strpos($value['selection']['id'], ':') + 1), $values);
    }

    private static function jsonValue($value, int $depth, int &$nodes): void
    {
        if (++$nodes > 8192 || $depth > 20) { throw new \InvalidArgumentException('Sources extension data is too complex.'); }
        if (is_array($value) || is_object($value)) {
            foreach ($value as $child) { self::jsonValue($child, $depth + 1, $nodes); }
        }
    }
}
