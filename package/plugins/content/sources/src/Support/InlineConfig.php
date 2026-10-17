<?php
namespace SuperSoft\Plugin\Content\Sources\Support;
require_once __DIR__ . '/SelectionUsage.php';

final class InlineConfig
{
    public const MAX_BYTES = 131072;
    public const MAX_BLOCKS = 30;
    public const MAX_ITEMS = 500;
    public const LAYOUTS = ['cards', 'details', 'list', 'icons', 'contents'];
    public const ORDERS = ['published-desc', 'published-asc', 'created-desc', 'created-asc', 'title-asc', 'title-desc', 'ordering-asc'];

    public static function decode(string $json): array
    {
        if (strlen($json) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Sources configuration is too large.');
        }
        $value = json_decode($json, false, 32, JSON_THROW_ON_ERROR);
        return self::validate($value);
    }

    public static function validate($value): array
    {
        $container = self::object($value, ['version', 'display', 'ordering', 'presentation', 'blocks']);
        if (!self::integer($container['version'] ?? null) || $container['version'] != 2 || !in_array($container['display'] ?? null, ['separate', 'merged'], true)) {
            throw new \InvalidArgumentException('Unsupported Sources version or display.');
        }
        $blocks = $container['blocks'] ?? null;
        if (!is_array($blocks) || !$blocks || count($blocks) > self::MAX_BLOCKS || !array_is_list($blocks)) {
            throw new \InvalidArgumentException('Sources requires 1 to 30 blocks.');
        }
        $ordering = self::object($container['ordering'] ?? (object) [], ['mode', 'order']);
        if (!in_array($ordering['mode'] ?? 'blocks', ['blocks', 'global'], true) || !in_array($ordering['order'] ?? 'published-desc', self::ORDERS, true)) {
            throw new \InvalidArgumentException('Invalid container ordering.');
        }
        $result = ['version' => 2, 'display' => $container['display'],
            'ordering' => ['mode' => $ordering['mode'] ?? 'blocks', 'order' => $ordering['order'] ?? 'published-desc'],
            'presentation' => self::presentation($container['presentation'] ?? (object) []), 'blocks' => []];
        foreach ($blocks as $block) {
            $block = self::object($block, ['type', 'name', 'title', 'articles', 'filters', 'presentation']);
            $type = $block['type'] ?? null;
            if (!in_array($type, ['articles', 'categories', 'tags'], true)) { throw new \InvalidArgumentException('Invalid Sources block type.'); }
            $filters = self::object($block['filters'] ?? (object) [], ['tags', 'categories', 'order', 'limit', 'groupByCategory']);
            $order = $filters['order'] ?? 'published-desc';
            if (!in_array($order, self::ORDERS, true) || !is_bool($filters['groupByCategory'] ?? false)) {
                throw new \InvalidArgumentException('Unsupported Sources ordering.');
            }
            $articles = SelectionUsage::selections($block['articles'] ?? [], 'articles');
            $tags = SelectionUsage::selections($filters['tags'] ?? [], 'tags');
            $categories = SelectionUsage::selections($filters['categories'] ?? [], 'categories');
            if ($type === 'articles' ? $tags || $categories : $articles) { throw new \InvalidArgumentException('Mixed Sources blocks are not supported.'); }
            if ($type !== 'categories' && ($filters['groupByCategory'] ?? false)) { throw new \InvalidArgumentException('Category grouping requires a category block.'); }
            if (array_key_exists('name', $block)) { self::title($block['name']); }
            $result['blocks'][] = ['type' => $type, 'title' => self::title(($block['title'] ?? '') !== '' ? $block['title'] : ($block['name'] ?? '')),
                'articles' => $articles,
                'filters' => ['tags' => $tags, 'categories' => $categories, 'order' => $order, 'limit' => self::limit($filters['limit'] ?? 0)],
                'presentation' => self::presentation($block['presentation'] ?? (object) [], false)];
        }
        return $result;
    }

    private static function object($value, array $allowed): array
    {
        if (!is_object($value) || array_diff(array_keys(get_object_vars($value)), $allowed)) {
            throw new \InvalidArgumentException('Unknown or invalid Sources properties.');
        }
        return get_object_vars($value);
    }

    private static function title($value): string
    {
        if (!is_string($value) || strlen($value) > 2000) {
            throw new \InvalidArgumentException('Invalid Sources title.');
        }
        return $value;
    }

    private static function presentation($value, bool $container = true): array
    {
        $value = self::object($value, $container ? ['title', 'layout', 'limit'] : ['layout']);
        $layout = $value['layout'] ?? 'cards';
        if (!in_array($layout, self::LAYOUTS, true)) {
            throw new \InvalidArgumentException('Invalid Sources presentation.');
        }
        return $container ? ['title' => self::title($value['title'] ?? ''), 'layout' => $layout, 'limit' => self::limit($value['limit'] ?? 0)] : ['layout' => $layout];
    }

    private static function limit($value): int
    {
        if (!self::integer($value) || $value < 0 || $value > self::MAX_ITEMS) { throw new \InvalidArgumentException('Invalid Sources limit.'); }
        return (int) $value;
    }

    private static function integer($value): bool
    {
        return is_int($value) || (is_float($value) && is_finite($value) && floor($value) === $value);
    }

}
