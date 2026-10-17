<?php
namespace SuperSoft\Plugin\Content\Sources\Support;

use Joomla\CMS\Factory;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Component\ComponentHelper;
use SuperSoft\Component\Smartbrowser\Administrator\Support\SelectionFieldSupport;
use SuperSoft\Component\Smartbrowser\Administrator\Support\SelectionOutputRenderer;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use Joomla\String\StringHelper;

final class InlineRenderer
{
    private $db;
    private $app;
    private static int $remainingItems = 2000;
    private static array $embedded = [];

    public function __construct(private Registry $params)
    {
        $this->app = Factory::getApplication();
        $this->db = Factory::getContainer()->get(DatabaseInterface::class);
    }

    public function render(array $config, object $article, string $mode = 'render', string $purpose = 'output'): string
    {
        $blocks = [];
        $remaining = $config['display'] === 'merged' ? 2000 : InlineConfig::MAX_ITEMS;
        $commonOrder = $config['display'] === 'merged' && $config['ordering']['mode'] === 'global' && $mode !== 'list_manual' ? $config['ordering']['order'] : null;
        foreach ($config['blocks'] as $block) {
            if ($remaining <= 0 || self::$remainingItems <= 0) { break; }
            $items = array_slice($this->resolve($block, $mode !== 'list_manual', $commonOrder, $config['display'] !== 'merged'), 0, min($remaining, self::$remainingItems));
            $remaining -= count($items);
            self::$remainingItems -= count($items);
            if ($items || $mode === 'list_manual') {
                $blocks[] = ['title' => $block['title'], 'layout' => $block['presentation']['layout'], 'items' => $items, 'filters' => $block['filters']];
            }
        }
        if ($config['display'] === 'merged' && $mode !== 'list_manual') {
            $unique = [];
            foreach ($blocks as $block) {
                foreach ($block['items'] as $item) { $unique[$item->id] ??= $item; }
            }
            $items = array_values($unique);
            if ($config['ordering']['mode'] === 'global') { $items = $this->sortItems($items, $config['ordering']['order']); }
            $limit = $config['presentation']['limit'];
            $items = array_slice($items, 0, $limit ?: InlineConfig::MAX_ITEMS);
            $blocks = $items ? [['title' => $config['presentation']['title'], 'layout' => $config['presentation']['layout'], 'items' => $items]] : [];
        }
        if (!$blocks) { return ''; }
        $html = '';
        foreach ($blocks as $block) {
            $prepared = $mode === 'render' ? $this->prepareOutput($block['items']) : [];
            if ($mode === 'render' && !$prepared) { continue; }
            $tag = (string) $this->params->get('heading_tag', 'h2');
            if (!in_array($tag, ['h2', 'h3', 'h4', 'h5', 'div', 'none'], true)) { $tag = 'h2'; }
            if ($block['title'] !== '' && $tag !== 'none') {
                $class = trim((string) $this->params->get('heading_class', '')) ?: 'sources-heading jl-h4 jl-margin-bottom';
                $html .= '<' . $tag . ' class="' . self::escape($class) . '">' . self::escape($block['title']) . '</' . $tag . '>';
            }
            $layout = $block['layout'];
            $html .= '<div class="sources-' . ($mode !== 'render' || $layout === 'list' ? 'list' : ($layout === 'contents' ? 'contents' : 'grid'))
                . ($mode === 'render' ? ' sources-layout--' . $layout : '') . '">';
            if ($mode !== 'render') { $html .= '<ul class="sources-preview-list">'; }
            foreach ($block['items'] as $item) {
                if ($mode !== 'render') {
                    $html .= '<li>' . self::escape($item->title) . '</li>';
                    continue;
                }
                $selected = $prepared['article:' . $item->id] ?? null;
                if ($selected === null || (int) $item->id === (int) ($article->id ?? 0) && $layout === 'contents') { continue; }
                $html .= $this->resourceItem($item, $layout, $selected, $purpose, (int) ($article->id ?? 0));
            }
            if ($mode !== 'render') { $html .= '</ul>'; }
            $html .= '</div>';
            if ($mode === 'list_manual') {
                $filters = $block['filters'];
                $limitSummary = $config['display'] === 'separate' ? ' · ' . self::escape(Text::_('PLG_CONTENT_SOURCES_CRITERIA_LIMIT')) . ': ' . $filters['limit'] : '';
                $html .= '<div class="sources-preview-criteria">' . self::escape(Text::_('PLG_CONTENT_SOURCES_CRITERIA_TAGS')) . ': '
                    . self::escape($this->filterTitles('tags', $filters['tags'])) . ' · ' . self::escape(Text::_('PLG_CONTENT_SOURCES_CRITERIA_CATEGORIES')) . ': '
                    . self::escape($this->filterTitles('categories', $filters['categories'])) . $limitSummary . '</div>';
            }
        }
        return $html === '' ? '' : '<section class="sources-section jl-margin-top">' . $html . '</section>';
    }

    private function baseQuery()
    {
        $levels = array_map('intval', $this->app->getIdentity()->getAuthorisedViewLevels());
        $access = implode(',', $levels ?: [0]);
        $now = $this->db->quote(Factory::getDate()->toSql());
        $null = $this->db->quote($this->db->getNullDate());
        $query = $this->db->getQuery(true)->select('a.*, c.title AS cat_title, c.alias AS cat_alias, c.lft AS category_lft')
            ->from($this->db->quoteName('#__content', 'a'))
            ->join('INNER', $this->db->quoteName('#__categories', 'c') . ' ON c.id = a.catid')
            ->where('a.state = 1')->where('c.published = 1')
            ->where('a.access IN (' . $access . ')')->where('c.access IN (' . $access . ')')
            ->where('(a.publish_up IS NULL OR a.publish_up = ' . $null . ' OR a.publish_up <= ' . $now . ')')
            ->where('(a.publish_down IS NULL OR a.publish_down = ' . $null . ' OR a.publish_down >= ' . $now . ')')
            ->where('NOT EXISTS (SELECT 1 FROM ' . $this->db->quoteName('#__categories') . ' AS ancestor WHERE ancestor.lft <= c.lft AND ancestor.rgt >= c.rgt AND ancestor.extension = ' . $this->db->quote('com_content') . ' AND (ancestor.published != 1 OR ancestor.access NOT IN (' . $access . ')))');
        if (method_exists($this->app, 'getLanguageFilter') && $this->app->getLanguageFilter()) {
            $language = $this->db->quote($this->app->getLanguage()->getTag());
            $query->where('a.language IN (' . $language . ',' . $this->db->quote('*') . ')')
                ->where('c.language IN (' . $language . ',' . $this->db->quote('*') . ')');
        }
        return $query;
    }

    /** Resolve every candidate through the public read-only policy, including dynamic articles. */
    private function prepareOutput(array $items): array
    {
        if (!$items || !ComponentHelper::isEnabled('com_smartbrowser')) { return []; }
        $this->app->bootComponent('com_smartbrowser');
        if (!class_exists(SelectionOutputRenderer::class)) { throw new \RuntimeException('SmartBrowser 3.16.0+ is required for Sources output.'); }
        $entries = array_map(static fn($item) => [
            'selection' => ['adapter' => 'articles', 'id' => 'article:' . (int) $item->id],
            'usage' => $item->sources_usage ?? [],
        ], $items);
        $settings = SelectionFieldSupport::configuration([
            'adapter' => 'articles', 'multiple' => true,
            'selection_profile' => '{"article.displayedName":{},"visual.thumbnailOverride":{},"visual.iconOverride":{"presentation":"secondary"},"visual.presentation":{"presentation":"secondary"}}',
        ]);
        $result = SelectionFieldSupport::prepare(json_encode(['version' => 1, 'items' => $entries], JSON_THROW_ON_ERROR), $settings, null, true);
        $prepared = [];
        if (empty($result['invalid'])) {
            foreach ($result['items'] ?? [] as $entry) {
                if (empty($entry['unavailable']) && empty($entry['resource']['unavailable']) && !empty($entry['resource']['id'])) {
                    $prepared[$entry['selection']['id']] = $entry;
                }
            }
        }
        return $prepared;
    }

    public function resolve(array $block, bool $includeAuto = true, ?string $orderOverride = null, bool $applyLimit = true): array
    {
        $items = [];
        if ($block['type'] === 'articles' && $block['articles']) {
            $ids = SelectionUsage::ids($block['articles']);
            $query = $this->baseQuery()->where('a.id IN (' . implode(',', $ids) . ')');
            $manual = $this->db->setQuery($query, 0, InlineConfig::MAX_ITEMS)->loadObjectList('id');
            foreach ($ids as $id) {
                if (isset($manual[$id])) { $items[$id] = $manual[$id]; }
            }
            $items = $this->applyUsage($items, $block['articles']);
        }
        $tags = SelectionUsage::ids($block['filters']['tags']);
        $categories = SelectionUsage::ids($block['filters']['categories']);
        $enabled = $block['type'] === 'categories' ? (bool) $categories : ($block['type'] === 'tags' && (bool) $tags);
        if ($includeAuto && $enabled) {
            $query = $this->baseQuery();
            // AND semantics without GROUP BY on full article rows (portable with ONLY_FULL_GROUP_BY).
            if ($tags) { $query->where('a.id IN (SELECT m.content_item_id FROM ' . $this->db->quoteName('#__contentitem_tag_map')
                . ' AS m WHERE m.type_alias = ' . $this->db->quote('com_content.article') . ' AND m.tag_id IN (' . implode(',', $tags)
                . ') GROUP BY m.content_item_id HAVING COUNT(DISTINCT m.tag_id) = ' . count($tags) . ')'); }
            if ($block['filters']['categories']) {
                $query->where('a.catid IN (' . implode(',', $categories) . ')');
            }
            if ($orderOverride === null && $block['type'] === 'categories' && $block['filters']['order'] === 'ordering-asc') {
                $cases = []; foreach ($categories as $index => $id) { $cases[] = 'WHEN ' . $id . ' THEN ' . $index; }
                $query->order('CASE a.catid ' . implode(' ', $cases) . ' ELSE ' . count($categories) . ' END ASC');
            }
            $query->order(self::sqlOrder($orderOverride ?? $block['filters']['order']));
            foreach ($this->db->setQuery($query, 0, InlineConfig::MAX_ITEMS)->loadObjectList() as $item) { $items[$item->id] ??= $item; }
        }
        $items = array_values($items);
        if ($orderOverride !== null) { $items = $this->sortItems($items, $orderOverride); }
        return array_slice($items, 0, !$applyLimit || $block['type'] === 'articles' ? InlineConfig::MAX_ITEMS : ($block['filters']['limit'] ?: InlineConfig::MAX_ITEMS));
    }

    private function applyUsage(array $items, array $entries): array
    {
        foreach ($entries as $entry) {
            if (!is_array($entry)) { continue; }
            $id = (int) substr($entry['selection']['id'], 8);
            if (!isset($items[$id])) { continue; }
            $item = clone $items[$id];
            $item->sources_usage = $entry['usage'];
            $name = $entry['usage']['article.displayedName'] ?? '';
            if (is_string($name) && trim($name) !== '') { $item->title = trim($name); }
            $items[$id] = $item;
        }
        return $items;
    }

    private static function sqlOrder(string $order): string
    {
        return match ($order) {
            'published-desc' => 'a.publish_up DESC, a.created DESC, a.id DESC',
            'published-asc' => 'a.publish_up ASC, a.created ASC, a.id ASC',
            'created-desc' => 'a.created DESC, a.id DESC', 'created-asc' => 'a.created ASC, a.id ASC',
            'title-asc' => 'a.title ASC, a.id ASC', 'title-desc' => 'a.title DESC, a.id DESC',
            'ordering-asc' => 'c.lft ASC, a.ordering ASC, a.id ASC',
            default => throw new \InvalidArgumentException('Invalid Sources ordering.'),
        };
    }

    private function sortItems(array $items, string $order): array
    {
        if (!in_array($order, InlineConfig::ORDERS, true)) { throw new \InvalidArgumentException('Invalid Sources ordering.'); }
        [$field, $direction] = explode('-', $order);
        $keys = match ($field) { 'published' => ['publish_up', 'created', 'id'], 'created' => ['created', 'id'], 'title' => ['title', 'id'], 'ordering' => ['category_lft', 'ordering', 'id'] };
        usort($items, static function ($left, $right) use ($keys, $direction) {
            foreach ($keys as $key) {
                $a = $left->$key ?? ''; $b = $right->$key ?? '';
                $difference = $key === 'title' ? strcmp(StringHelper::strtolower((string) $a), StringHelper::strtolower((string) $b)) : ($a <=> $b);
                if ($difference) { return $direction === 'desc' ? -$difference : $difference; }
            }
            return 0;
        });
        return $items;
    }

    private function resourceItem(object $item, string $layout, array $selected, string $purpose, int $parentArticleId = 0): string
    {
        if (isset(self::$embedded[$item->id]) || count(self::$embedded) >= 3
            || $layout === 'contents' && (int) $item->id === $parentArticleId) { return ''; }
        $renderer = new FileLayout('sources.item', null, ['component' => 'com_content', 'client' => 0]);
        $paths = $renderer->getDefaultIncludePaths();
        if (!$this->app->isClient('site')) {
            $query = $this->db->getQuery(true)->select('template')->from('#__template_styles')
                ->where('client_id = 0')->where('home != ' . $this->db->quote('0'))->order('id ASC');
            $template = basename((string) $this->db->setQuery($query, 0, 1)->loadResult());
            if ($template !== '') {
                array_unshift($paths, JPATH_SITE . '/templates/' . $template . '/html/layouts/com_content',
                    JPATH_SITE . '/templates/' . $template . '/html/layouts');
            }
        }
        // The packaged layout is the lowest-priority fallback, never an override bypass.
        $renderer->setIncludePaths([...$paths, dirname(__DIR__, 2) . '/layouts']);
        self::$embedded[$item->id] = true;
        $level = ob_get_level();
        try {
            return $renderer->render([
                'item' => clone $item, 'layout' => $layout, 'selected' => $selected,
                'purpose' => $purpose === 'preview' ? 'preview' : 'output', 'parent_article_id' => $parentArticleId,
            ]);
        } finally {
            unset(self::$embedded[$item->id]);
            while (ob_get_level() > $level) { ob_end_clean(); }
        }
    }


    private function filterTitles(string $type, array $ids): string
    {
        $ids = SelectionUsage::ids($ids);
        if (!$ids) { return '-'; }
        $levels = array_map('intval', $this->app->getIdentity()->getAuthorisedViewLevels());
        $query = $this->db->getQuery(true)->select('id, title')->from($this->db->quoteName($type === 'tags' ? '#__tags' : '#__categories'))
            ->where('id IN (' . implode(',', $ids) . ')')->where('published = 1')->where('access IN (' . implode(',', $levels ?: [0]) . ')');
        $titles = $this->db->setQuery($query, 0, InlineConfig::MAX_ITEMS)->loadObjectList('id');
        return implode(', ', array_map(static fn($id) => isset($titles[$id]) ? $titles[$id]->title : '#' . $id, $ids));
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
