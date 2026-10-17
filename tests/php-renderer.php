<?php
namespace Joomla\Registry {
    class Registry { public function __construct(private array $values = []) {} public function get($key, $default = null) { return $this->values[$key] ?? $default; } }
}
namespace Joomla\Database { interface DatabaseInterface {} }
namespace Joomla\String { class StringHelper { public static function strtolower($value) { return strtolower($value); } public static function substr($value, $start, $length) { return substr($value, $start, $length); } } }
namespace Joomla\CMS\Language { class Text { public static function _($key) { return $key; } } }
namespace Joomla\CMS\Component { class ComponentHelper { public static function isEnabled($name) { return true; } } }
namespace SuperSoft\Component\Smartbrowser\Administrator\Support {
    class SelectionFieldSupport {
        public static bool $readOnly = false;
        public static bool $unavailable = false;
        public static function configuration($settings) { return $settings; }
        public static function prepare($raw, $settings, $resolver = null, $readOnly = null) {
            self::$readOnly = $readOnly;
            $value = json_decode($raw, true);
            foreach ($value['items'] as &$entry) {
                $entry['usage'] ??= [];
                $entry['resource'] = ['id' => $entry['selection']['id'], 'title' => 'Article ' . substr($entry['selection']['id'], 8), 'metadata' => ['kept' => true]];
                $entry['unavailable'] = self::$unavailable;
                $id = $entry['usage']['visual.thumbnailOverride']['id'] ?? '';
                $entry['effectiveThumbnail'] = $id === 'missing' ? null : $id;
                $entry['effectiveIcon'] = 'fas fa-book';
            }
            return $value;
        }
    }
    class SelectionOutputRenderer {
        public static function render($selected, $options) {
            $id = (int) substr($selected['selection']['id'], 8);
            \Joomla\CMS\Layout\LayoutHelper::$calls[] = [$options['layout'], [
                'item' => (object) ['id' => $id], 'selected' => $selected,
                'sources_usage' => $selected['usage'], 'purpose' => $options['purpose'],
            ], null];
            return '<div data-id="' . $id . '">' . htmlspecialchars($selected['usage']['article.displayedName'] ?? $selected['resource']['title'], ENT_QUOTES, 'UTF-8') . '</div>';
        }
    }
}
namespace Joomla\CMS\Plugin {
    class PluginHelper {
        public static function isEnabled(...$args) { return true; }
        public static function getPlugin(...$args) { return (object) ['params' => []]; }
    }
}
namespace Joomla\CMS\Log {
    class Log { public const WARNING = 1; public static function add(...$args) {} }
}
namespace Joomla\CMS {
    class Factory {
        public static $db;
        public static string $view = 'article';
        public static function getApplication() { return new class {
            public function getIdentity() { return new class { public function getAuthorisedViewLevels() { return [1, 2]; } }; }
            public function getLanguageFilter() { return true; }
            public function getLanguage() { return new class { public function getTag() { return 'el-GR'; } }; }
            public function isClient($client) { return $client === 'site'; }
            public function getTemplate() { return 'fixture'; }
            public function bootPlugin(...$args) {}
            public function bootComponent(...$args) {}
            public function getInput() { return new class { public function getCmd($key) { return $key === 'view' ? Factory::$view : ''; } }; }
            public function getDocument() { return new class {
                public function getType() { return 'html'; }
                public function getWebAssetManager() { return new class { public function registerAndUseStyle(...$args) {} public function useStyle(...$args) {} }; }
            }; }
        }; }
        public static function getContainer() { return new class { public function get($name) { return Factory::$db; } }; }
        public static function getDate() { return new class { public function toSql() { return '2026-10-14 12:00:00'; } }; }
    }
}
namespace Joomla\CMS\Layout {
    class FileLayout {
        public function __construct(private $id, $base, $options) {
            if ($base !== null) throw new \RuntimeException('High-priority layout base path');
        }
        public function getDefaultIncludePaths() { return []; }
        public function setIncludePaths($paths) { return $this; }
        public function render($displayData) {
            ob_start();
            include dirname(__DIR__) . '/package/plugins/content/sources/layouts/sources/item.php';
            return ob_get_clean();
        }
    }
    class LayoutHelper {
        public static $defaultBasePath = '';
        public static array $calls = [];
        public static function render($layout, $data, $path = null) {
            self::$calls[] = [$layout, $data, $path];
            return '<div data-id="' . $data['item']->id . '">' . htmlspecialchars($data['item']->title, ENT_QUOTES, 'UTF-8') . '</div>';
        }
    }
}
namespace Joomla\CMS\HTML {
    class HTMLHelper { public static function _($name, $text, ...$args) { return $text; } }
}
namespace {
    use Joomla\CMS\Factory;
    use Joomla\CMS\Layout\LayoutHelper;
    use Joomla\Registry\Registry;
    use SuperSoft\Plugin\Content\Sources\Support\InlineConfig;
    use SuperSoft\Plugin\Content\Sources\Support\InlineRenderer;
    define('JPATH_SITE', __DIR__ . '/fixture');
    define('_JEXEC', 1);
    require __DIR__ . '/../package/plugins/content/sources/src/Support/InlineConfig.php';
    require __DIR__ . '/../package/plugins/content/sources/src/Support/InlineTokens.php';
    require __DIR__ . '/../package/plugins/content/sources/src/Support/InlineRenderer.php';
    class Query {
        public array $clauses = [];
        public function __call($name, $args) { $this->clauses[] = $name . ':' . implode('|', $args); return $this; }
        public function __toString() { return implode(' ', $this->clauses); }
    }
    class Database {
        public $query;
        public array $queries = [];
        public function getQuery($new) { return new Query(); }
        public function quoteName($name, $alias = null) { return $name . ($alias ? ' AS ' . $alias : ''); }
        public function quote($value) { return "'" . $value . "'"; }
        public function getNullDate() { return '0000-00-00 00:00:00'; }
        public function setQuery($query, $offset = 0, $limit = 0) { check($limit <= 500, 'Bounded queries'); $this->query = (string) $query; $this->queries[] = $this->query; return $this; }
        public function loadObjectList($key = null) {
            $ids = str_contains($this->query, 'contentitem_tag_map') ? [4, 8] : [4, 11];
            $items = [];
            foreach ($ids as $id) { $item = (object) ['id' => $id, 'title' => 'Article ' . $id, 'introtext' => '', 'fulltext' => '', 'images' => '{}']; if ($key) $items[$id] = $item; else $items[] = $item; }
            return $items;
        }
    }
    function check($condition, $message) { if (!$condition) throw new \RuntimeException($message); }
    Factory::$db = new Database();
    $renderer = new InlineRenderer(new Registry(['heading_tag' => 'script']));
    $config = InlineConfig::decode('{"version":2,"display":"separate","presentation":{"title":"Merged","layout":"list","limit":0},"blocks":[{"type":"articles","title":"<unsafe>","articles":[11,4,99],"filters":{"limit":0},"presentation":{"layout":"cards"}},{"type":"tags","title":"Dynamic","filters":{"tags":[8,12],"categories":[14],"limit":0}},{"type":"articles","title":"Second","articles":[4],"presentation":{"layout":"contents"}}]}');
    $result = $renderer->resolve($config['blocks'][0]);
    check(array_column($result, 'id') === [11, 4], 'Manual order and unavailable omission');
    check(array_column($renderer->resolve($config['blocks'][1]), 'id') === [4,8], 'Tags produce dynamic sources with category restriction');
    $sql = implode(' ', Factory::$db->queries);
    foreach (['a.state = 1', 'c.published = 1', 'a.access IN (1,2)', 'c.access IN (1,2)', 'publish_up', 'publish_down', 'ancestor.access', 'a.language', 'c.language', 'COUNT(DISTINCT m.tag_id) = 2', 'a.catid IN (14)', 'a.publish_up DESC, a.created DESC, a.id DESC'] as $guard) check(str_contains($sql, $guard), 'Missing selection guard: ' . $guard);
    $html = $renderer->render($config, (object) ['id' => 100]);
    check(str_contains($html, '&lt;unsafe&gt;') && !str_contains($html, '<script'), 'Escaped headings and trusted heading allowlist');
    check(count(LayoutHelper::$calls) === 5, 'Separate blocks preserve repeated references across independent lists');
    check(LayoutHelper::$calls[4][0] === 'smartbrowser.resource', 'Contents uses the same common resource identity');
    check(LayoutHelper::$calls[0][2] === null, 'No high-priority base path bypasses template overrides');
    LayoutHelper::$calls = [];
    $config['display'] = 'merged'; $config['presentation']['limit'] = 2;
    $mergedHtml = $renderer->render($config, (object) ['id' => 100]);
    check(array_map(fn($call) => $call[1]['item']->id, LayoutHelper::$calls) === [11,4], 'Merged first-occurrence ordering and container limit');
    check(str_contains($mergedHtml, 'sources-item--list'), 'Merged uses container presentation');
    $config['ordering'] = ['mode' => 'global', 'order' => 'title-desc']; $config['presentation']['limit'] = 0;
    LayoutHelper::$calls = []; $renderer->render($config, (object) ['id' => 100]);
    check(array_map(fn($call) => $call[1]['item']->id, LayoutHelper::$calls) === [8,4,11], 'Common ordering overrides block/manual order after dedupe');
    $limited = $config['blocks'][0]; $limited['filters']['limit'] = 1;
    check(array_column($renderer->resolve($limited, true, 'title-desc'), 'id') === [4,11], 'Explicit manual selection is never truncated by a block limit');
    $category = InlineConfig::decode('{"version":2,"display":"separate","blocks":[{"type":"categories","filters":{"categories":[19,14],"order":"ordering-asc"}}]}')['blocks'][0];
    $before = count(Factory::$db->queries); $renderer->resolve($category);
    check(count(Factory::$db->queries) === $before + 1, 'Categories alone enable dynamic selection');
    $last = end(Factory::$db->queries);
    check(str_contains($last, 'a.catid IN (19,14)') && str_contains($last, 'CASE a.catid WHEN 19 THEN 0 WHEN 14 THEN 1') && str_contains($last, 'a.ordering ASC'), 'Category/article order uses Builder category order without a grouping checkbox');
    $category['filters']['order'] = 'created-asc'; $renderer->resolve($category);
    check(!str_contains(end(Factory::$db->queries), 'CASE a.catid'), 'Other order choices do not group categories');
    $category['filters']['limit'] = 1;
    check(count($renderer->resolve($category)) === 1 && count($renderer->resolve($category, true, null, false)) === 2, 'Merged candidate resolution ignores per-block limits');
    $category['filters']['order'] = 'ordering-asc';
    $renderer->resolve($category, true, 'ordering-asc', false);
    $last = end(Factory::$db->queries);
    check(!str_contains($last, 'CASE a.catid') && str_contains($last, 'c.lft ASC, a.ordering ASC'), 'Common Joomla ordering uses site category sequence');
    $renderer->resolve($category, true, 'published-desc');
    $last = end(Factory::$db->queries);
    check(!str_contains($last, 'CASE a.catid') && str_contains($last, 'a.publish_up DESC'), 'Common order overrides internal category groups and sort');
    $small = $config; $small['blocks'] = [$config['blocks'][1]]; $small['blocks'][0]['filters']['limit'] = 1;
    foreach (['blocks', 'global'] as $mode) {
        $small['ordering']['mode'] = $mode; LayoutHelper::$calls = []; $renderer->render($small, (object) ['id' => 100]);
        check(count(LayoutHelper::$calls) === 2, 'Both merged modes ignore block limits');
    }
    $small['presentation']['limit'] = 1; LayoutHelper::$calls = []; $renderer->render($small, (object) ['id' => 100]);
    check(count(LayoutHelper::$calls) === 1, 'Final merged limit is still applied after combining');
    $small['display'] = 'separate'; LayoutHelper::$calls = []; $renderer->render($small, (object) ['id' => 100]);
    check(count(LayoutHelper::$calls) === 1, 'Separate retains dynamic block limits');
    $category['filters']['categories'] = []; $before = count(Factory::$db->queries);
    check($renderer->resolve($category) === [] && count(Factory::$db->queries) === $before, 'Empty primary categories never query every article');
    $list = $renderer->render($config, (object) ['id' => 100], 'list_full');
    check(str_contains($list, '<ul class="sources-preview-list">') && str_contains($list, '<li>'), 'Editor preview uses semantic lists');
    check(!str_contains($list, '<div>Four</div>'), 'Editor preview does not use bare title rows');
    $config['blocks'] = [$config['blocks'][1]];
    $manual = $renderer->render($config, (object) ['id' => 100], 'list_manual');
    check(str_contains($manual, 'sources-preview-criteria') && str_contains($manual, '#8, #12'), 'Dynamic-only blocks retain criteria in manual summary');
    check(LayoutHelper::$defaultBasePath === '', 'Nested layout context is restored');
    $renderField = static function ($value, $context = 'com_content.article') {
        $field = (object) ['value' => $value]; $item = (object) ['id' => 100];
        ob_start(); include __DIR__ . '/../package/plugins/fields/sources/tmpl/sources.php';
        return [ob_get_clean(), $field->value];
    };
    $raw = json_encode($config, JSON_UNESCAPED_UNICODE);
    $usageConfig = InlineConfig::decode('{"version":2,"display":"separate","blocks":[{"type":"articles","articles":[{"selection":{"adapter":"articles","id":"article:11"},"usage":{"article.displayedName":"<Local title>","visual.thumbnailOverride":{"adapter":"media","id":"images/override.jpg#joomlaImage://local-images/override.jpg?crop=10,10,80,80"},"visual.iconOverride":"fas fa-book","visual.presentation":"both","ext.smartcrop.crop":{"ratio":"1:1"}}},4]}]}');
    $local = $renderer->resolve($usageConfig['blocks'][0]);
    check($local[0]->title === '<Local title>' && $local[1]->title === 'Article 4', 'Usage applies only to its article reference');
    check($local[0]->images === '{}', 'Sources does not rewrite article images or interpret crop data');
    check($local[0]->sources_usage['visual.presentation'] === 'both', 'Opaque usage retained for common rendering');
    check(\SuperSoft\Component\Smartbrowser\Administrator\Support\SelectionFieldSupport::$readOnly === true, 'Rendering resolves stored resources through the public readonly SmartBrowser path');
    check($renderer->resolve(InlineConfig::decode('{"version":2,"display":"separate","blocks":[{"type":"articles","articles":[11]}]}')['blocks'][0])[0]->title === 'Article 11', 'Overrides never mutate the original article');
    check(str_contains($renderer->render($usageConfig, (object) ['id' => 100], 'list_full'), '&lt;Local title&gt;'), 'List preview escapes the effective displayed name');
    LayoutHelper::$calls = []; $renderer->render($usageConfig, (object) ['id' => 100]);
    check(LayoutHelper::$calls[0][1]['sources_usage']['visual.iconOverride'] === 'fas fa-book', 'Template overrides receive the full selection usage');
    $usageConfig['blocks'][0]['presentation']['layout'] = 'details';
    check(str_contains($renderer->render($usageConfig, (object) ['id' => 100]), 'sources-layout--details'), 'Rendered Details carries an explicit layout marker independent of template wrappers');
    \SuperSoft\Component\Smartbrowser\Administrator\Support\SelectionFieldSupport::$unavailable = true;
    check($renderer->render($usageConfig, (object) ['id' => 100]) === '', 'All unavailable prepared candidates produce no block or heading');
    \SuperSoft\Component\Smartbrowser\Administrator\Support\SelectionFieldSupport::$unavailable = false;
    $usageConfig['blocks'][0]['articles'][0]['usage']['visual.thumbnailOverride']['id'] = 'javascript:alert(1)';
    $unsafe = $renderer->resolve($usageConfig['blocks'][0]);
    check(!isset(json_decode($unsafe[0]->images, true)['image_intro']), 'Unsafe resolved thumbnails never enter article presentation');
    [$output, $stored] = $renderField($raw);
    check(str_contains($output, 'data-id=') && !str_contains($output, '"version"'), 'Custom field delegates to the shared renderer, never showing raw JSON');
    check($stored === $raw, 'Field rendering does not change stored JSON');
    check($renderField($raw, 'com_content.category')[0] === '', 'Category/blog preparation does not expose field configuration');
    check($renderField('{"version":999}')[0] === '', 'Malformed or future field JSON fails closed');
    check($renderField('')[0] === '', 'An empty field emits nothing');
    Factory::$view = 'form';
    check($renderField($raw)[0] === '', 'Editing contexts are never rendered as frontend output');
    Factory::$view = 'category';
    check($renderField($raw)[0] === '', 'Normalized Joomla field contexts do not render full Sources in blog views');
    Factory::$view = 'article';
    echo 'PHP renderer assertions passed';
}
