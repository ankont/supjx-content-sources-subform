<?php
namespace Joomla\Registry {
    class Registry {
        public function __construct(private array $data = []) {}
        public function get($key, $default = null) { $v = $this->data; foreach (explode('.', $key) as $p) { if (!is_array($v) || !array_key_exists($p, $v)) return $default; $v = $v[$p]; } return $v; }
        public function set($key, $value) { $v =& $this->data; foreach (explode('.', $key) as $p) { $v =& $v[$p]; } $v = $value; }
        public function toArray() { return $this->data; }
    }
}
namespace Joomla\CMS\User { class User { public $id = 0; public function __construct($id = 0) { $this->id = $id; } public function getAuthorisedViewLevels() { return [1]; } } }
namespace Joomla\CMS\Component { class ComponentHelper {
    public static bool $smart = false;
    public static array $presentation = [];
    public static int $globalVisibility = 1;
    public static function isEnabled($name) { return $name === 'com_smartbrowser' && self::$smart; }
    public static function getParams($name) { return new \Joomla\Registry\Registry(['selection_show_unavailable' => self::$globalVisibility] + self::$presentation); }
} }
namespace SuperSoft\Component\Smartbrowser\Administrator\Support {
    class SelectionFieldSupport {
        public static function configuration($v) { return $v; }
        public static function prepare($raw, ...$args) {
            $data = json_decode($raw, true);
            foreach ($data['items'] as &$entry) { $entry['unavailable'] = true; }
            return $data;
        }
    }
}
namespace Joomla\CMS\Uri { class Uri { public static function root() { return 'https://example.test/site/'; } } }
namespace Joomla\CMS\Plugin {
    class PluginHelper { public static array $params = []; public static function getPlugin(...$args) { return (object) ['params' => self::$params]; } }
    class CMSPlugin { public function getApplication() { return \Joomla\CMS\Factory::getApplication(); } }
}
namespace Joomla\CMS\Language { class Text { public static function _($key) { return $key === 'PLG_CONTENT_SOURCES_EMAIL_READ_MORE' ? 'Read the full article by following its title.' : $key; } } }
namespace Joomla\Event {
    interface EventInterface { public function getArgument($key); }
    interface SubscriberInterface { public static function getSubscribedEvents(): array; }
    class Event implements EventInterface { public function __construct(private array $args) {} public function getArgument($key) { return $this->args[$key] ?? null; } }
}
namespace Joomla\Database { interface DatabaseInterface {} }
namespace Joomla\String { class StringHelper { public static function strtolower($v) { return strtolower($v); } public static function substr($v, $a, $b) { return substr($v, $a, $b); } } }
namespace Joomla\CMS {
    class Factory {
        public static $db; public static $identity = null; public static int $boots = 0;
        public static function getApplication() { return new class {
            public function getIdentity() { return Factory::$identity ?? new \Joomla\CMS\User\User(); }
            public function loadIdentity($identity) { Factory::$identity = $identity; }
            public function isClient($v) { return $v === 'site'; }
            public function getLanguageFilter() { return true; }
            public function getLanguage() { return new class { public function getTag() { return 'en-GB'; } public function load(...$args) {} }; }
            public function bootPlugin(...$args) { Factory::$boots++; }
            public function bootComponent(...$args) {}
        }; }
        public static function getContainer() { return new class { public function get($v) { return Factory::$db; } }; }
        public static function getDate() { return new class { public function toSql() { return '2026-10-26 12:00:00'; } }; }
    }
}
namespace Joomla\CMS\Layout {
    class FileLayout {
        public static $override = null;
        private array $paths = [];
        public function __construct(private string $name, ...$args) {}
        public function getDefaultIncludePaths() { return []; }
        public function setIncludePaths($paths) { $this->paths = $paths; }
        public function render($displayData) {
            if ($this->name === 'sources.email.item' && is_callable(self::$override)) { return (self::$override)($displayData); }
            foreach ($this->paths as $path) {
                $file = $path . '/' . str_replace('.', '/', $this->name) . '.php';
                if (is_file($file)) { ob_start(); include $file; return ob_get_clean(); }
            }
            throw new \RuntimeException('No email layout');
        }
    }
}
namespace {
    define('_JEXEC', 1);
    $root = dirname(__DIR__) . '/package';
    define('JPATH_PLUGINS', $root . '/plugins');
    define('JPATH_ADMINISTRATOR', $root);
    foreach (['SelectionUsage', 'InlineConfig', 'InlineTokens', 'RequestCache', 'SourceFieldData', 'InlineRenderer', 'EmailHtml', 'EmailRenderer', 'EmailPreview'] as $name) require $root . '/plugins/content/sources/src/Support/' . $name . '.php';
    require $root . '/plugins/newsletter/sourcesemail/src/Extension/SourcesEmail.php';
    use Joomla\CMS\Factory;
    use Joomla\Registry\Registry;
    use SuperSoft\Plugin\Content\Sources\Support\InlineConfig;
    use SuperSoft\Plugin\Content\Sources\Support\RequestCache;
    use SuperSoft\Plugin\Content\Sources\Support\EmailRenderer;
    use SuperSoft\Plugin\Newsletter\SourcesEmail\Extension\SourcesEmail;
    function check($v, $message) { if (!$v) throw new \RuntimeException($message); }
    class Query { public string $sql = ''; public function __call($key, $args) { $this->sql .= ' ' . $key . ':' . implode(' ', $args); return $this; } public function __toString() { return $this->sql; } }
    Factory::$db = new class {
        public int $queries = 0; public string $sql = ''; public array $articles = [];
        public function getQuery($new) { return new Query(); }
        public function quoteName($v, $alias = null) { return $v . ($alias ? ' AS ' . $alias : ''); }
        public function quote($v) { return "'" . $v . "'"; }
        public function getNullDate() { return '0000-00-00 00:00:00'; }
        public function setQuery($query, $offset = 0, $limit = 0) {
            check($limit > 0 && $limit <= InlineConfig::MAX_ITEMS, 'Query must retain shared selector bounds'); $this->queries++; $this->sql = (string) $query; return $this;
        }
        public function loadObjectList($key = null) {
            foreach (['a.state = 1', 'c.published = 1', 'a.access IN (1)', 'c.access IN (1)', 'ancestor.access', 'publish_up', 'publish_down', 'a.language', 'c.language'] as $guard) check(str_contains($this->sql, $guard), 'Missing public SQL guard ' . $guard);
            $rows = []; $ids = array_keys($this->articles);
            if (preg_match('/\ba\.id IN \(([0-9,]+)\)/', $this->sql, $m)) $ids = array_map('intval', explode(',', $m[1]));
            foreach ($ids as $id) { if (isset($this->articles[$id])) { $row = clone $this->articles[$id]; if ($key) $rows[$id] = $row; else $rows[] = $row; } }
            return $rows;
        }
    };
    Factory::$db->articles = [1 => (object) ['id' => 1, 'catid' => 2, 'title' => 'Public title', 'introtext' => '<p>Hello <b>world</b> {name}</p><script>bad()</script>', 'fulltext' => '', 'images' => '{"image_intro":"images/a.jpg"}']];
    $base = ['version' => 1, 'purpose' => 'preview', 'publicOnly' => true, 'rootUrl' => 'https://example.test/site/',
        'limits' => ['maxBytes' => 2000000, 'maxItems' => 60, 'maxTokens' => 8, 'maxDepth' => 3], 'usage' => ['items' => 0, 'tokens' => 0], 'errors' => ['previous'], 'unresolved' => []];
    $config = InlineConfig::decode('{"version":2,"display":"separate","blocks":[{"type":"articles","articles":[1]}]}');
    $token = static fn($c) => '{sources:' . json_encode($c) . '}';
    $provider = new SourcesEmail();
    $dispatch = static function ($html, $changes = []) use ($base, $provider) {
        RequestCache::reset(); $doc = new Registry(array_replace_recursive($base, $changes, ['html' => $html]));
        $provider->prepareEmail(new \Joomla\Event\Event(['context' => 'com_newsletterbridge.newsletter', 'document' => $doc])); return $doc;
    };
    $before = Factory::$db->queries; $doc = $dispatch('<p>Hello {name}</p>');
    check(Factory::$db->queries === $before && Factory::$boots === 0 && $doc->get('html') === '<p>Hello {name}</p>', 'No-token path must be zero-work');
    foreach (['cards', 'details', 'list', 'icons', 'contents'] as $layout) {
        $c = $config; $c['blocks'][0]['presentation']['layout'] = $layout;
        $doc = $dispatch('<p>Before {email}</p>' . $token($c) . '<p>After</p>');
        check($doc->get('errors') === ['previous'], 'Valid layout failed: ' . $layout);
        check(str_contains($doc->get('html'), 'Public title') && str_starts_with($doc->get('html'), '<p>Before {email}</p>') && str_ends_with($doc->get('html'), '<p>After</p>'), 'Unrelated HTML/placeholders changed');
        check(!str_contains($doc->get('html'), '<script') && !str_contains($doc->get('html'), 'bad()'), 'Unsafe article HTML');
        check($doc->get('usage.items') === 1 && $doc->get('usage.tokens') === 1, 'Counters');
        if ($layout === 'contents') check(str_contains($doc->get('html'), '<b>world</b> {name}'), 'Contents lost actual body');
        if (in_array('--artifacts', $argv, true)) {
            $directory = __DIR__ . '/artifacts'; if (!is_dir($directory)) mkdir($directory, 0755, true);
            file_put_contents($directory . '/email-' . $layout . '.html', $doc->get('html'));
        }
    }
    foreach (['{sources:{bad}}', '{sources:{"version":99}}', '<a title="' . htmlspecialchars($token($config), ENT_QUOTES) . '">bad</a>', '&#123;sources:bad&#125;'] as $bad) {
        $doc = $dispatch($bad); check(count($doc->get('errors')) === 2 && $doc->get('html') === $bad, 'Malformed/misplaced input must fail atomically');
    }
    $doc = $dispatch($token($config), ['version' => 2]); check($doc->get('usage.tokens') === 0, 'Future host protocol must be ignored');
    $doc = $dispatch($token($config), ['usage' => ['items' => 60, 'tokens' => 1]]); check(count($doc->get('errors')) === 1 && $doc->get('usage.items') === 61, 'No cumulative item cap');
    $doc = $dispatch(str_repeat($token($config), 10)); check(count($doc->get('errors')) === 1 && $doc->get('usage.tokens') === 10, 'No cumulative token cap');
    $originalArticles = Factory::$db->articles;
    for ($id = 2; $id <= 70; $id++) { $article = clone $originalArticles[1]; $article->id = $id; Factory::$db->articles[$id] = $article; }
    $many = $config; $many['blocks'][0]['articles'] = range(1, 70);
    $doc = $dispatch($token($many));
    check(count($doc->get('errors')) === 1 && $doc->get('usage.items') === 70, 'More than 60 actual articles must render without cumulative caps');
    Factory::$db->articles = $originalArticles;
    $doc = $dispatch($token($config), ['limits' => ['maxBytes' => 10]]); check(count($doc->get('errors')) === 2, 'Byte budget');
    foreach (['categories', 'tags'] as $type) {
        $c = InlineConfig::decode(json_encode(['version' => 2, 'display' => 'separate', 'blocks' => [['type' => $type, 'filters' => [$type => [2]]]]]));
        (new EmailRenderer(new Registry($base), $root . '/plugins/newsletter/sourcesemail/layouts'))->prepare($token($c));
        $doc = $dispatch($token($c)); check($doc->get('usage.items') === 1 && $doc->get('errors') === ['previous'], 'Dynamic ' . $type . ' ' . json_encode($doc->toArray()));
    }
    $usage = $config; $usage['blocks'][0]['articles'] = [['selection' => ['adapter' => 'articles', 'id' => 'article:1'], 'usage' => ['article.displayedName' => 'Custom email name']]];
    check(str_contains($dispatch($token($usage))->get('html'), 'Custom email name'), 'Displayed-name usage');
    $usage['blocks'][0]['articles'][0]['usage']['ext.smartcrop'] = ['crop' => true];
    check(count($dispatch($token($usage))->get('errors')) === 2, 'Unsupported extension usage must fail explicitly');
    $chain = $config; $chain['blocks'][0]['presentation']['layout'] = 'contents';
    foreach ([4, 3, 2, 1] as $id) {
        $next = $chain; $next['blocks'][0]['articles'] = [$id + 1];
        $article = clone Factory::$db->articles[1]; $article->id = $id; $article->introtext = $id < 4 ? $token($next) : 'End'; Factory::$db->articles[$id] = $article;
    }
    $doc = $dispatch($token($chain));
    check(count($doc->get('errors')) === 1 && $doc->get('usage.items') === 4 && !str_contains($doc->get('html'), 'End') && str_contains($doc->get('html'), 'Read the full article'), 'Fourth article is a linked header, not a depth error');
    if (in_array('--artifacts', $argv, true)) { file_put_contents(__DIR__ . '/artifacts/email-header.html', $doc->get('html')); }
    \Joomla\CMS\Plugin\PluginHelper::$params = ['contents_levels' => 2];
    $doc = $dispatch($token($chain));
    check($doc->get('usage.items') === 3 && count($doc->get('errors')) === 1, 'Shared configured depth controls header fallback');
    \Joomla\CMS\Plugin\PluginHelper::$params = [];
    Factory::$db->articles = [1 => (object) ['id' => 1, 'catid' => 2, 'title' => 'Public title', 'introtext' => 'Restored', 'fulltext' => '', 'images' => '{}']];
    $c = $config; $c['blocks'][0]['presentation']['layout'] = 'contents'; Factory::$db->articles[1]->fulltext = $token($c);
    $doc = $dispatch($token($c)); check(count($doc->get('errors')) === 1 && $doc->get('usage.items') === 2 && substr_count($doc->get('html'), 'Restored') === 1 && str_contains($doc->get('html'), 'Read the full article'), 'Cycle keeps linked header without repeating body'); Factory::$db->articles[1]->fulltext = '';
    $c = $config; $c['blocks'][] = $c['blocks'][0]; $c['display'] = 'merged';
    $doc = $dispatch($token($c)); check($doc->get('usage.items') === 1, 'Merged deduplication');
    $safe = \SuperSoft\Plugin\Content\Sources\Support\EmailHtml::clean('<p onclick="bad()">ok<a href="javascript:bad()">bad</a><img src="data:x"><iframe>x</iframe><a href="/safe">link</a></p>', $base['rootUrl']);
    check(!str_contains($safe, 'javascript') && !str_contains($safe, 'onclick') && !str_contains($safe, 'iframe') && str_contains($safe, 'https://example.test/safe'), 'Sanitisation/URL policy');
    foreach (['cards', 'details', 'list', 'icons', 'contents'] as $layout) {
        $previewConfig = $config; $previewConfig['blocks'][0]['presentation']['layout'] = $layout;
        $expected = (new EmailRenderer(new Registry($base), $root . '/plugins/newsletter/sourcesemail/layouts'))->prepare($token($previewConfig));
        $author = new \Joomla\CMS\User\User(42); Factory::$identity = $author;
        $actual = \SuperSoft\Plugin\Content\Sources\Support\EmailPreview::render($previewConfig, 'render');
        check($actual === $expected, 'Preview must use identical final email pipeline: ' . $layout);
        check(Factory::$identity === $author, 'Preview must restore the exact original identity'); Factory::$identity = null;
    }
    Factory::$identity = new \Joomla\CMS\User\User(42); $author = Factory::$identity;
    try { \SuperSoft\Plugin\Content\Sources\Support\EmailPreview::render($usage, 'render'); throw new \LogicException('Expected unsupported Usage failure'); }
    catch (\RuntimeException $error) { check(Factory::$identity === $author, 'Failed email preview leaked public identity'); }
    Factory::$identity = null;
    require dirname(__DIR__, 2) . '/pkg_smartbrowser/package/component/admin/src/Support/SelectionOutputRenderer.php';
    \Joomla\CMS\Component\ComponentHelper::$smart = true;
    foreach ([0, 1] as $global) foreach ([false, true] as $image) foreach ([false, true] as $icon)
    foreach (['cards', 'details', 'list', 'icons', 'contents'] as $layout) foreach (['preview', 'send'] as $purpose) {
        \Joomla\CMS\Component\ComponentHelper::$globalVisibility = $global;
        \Joomla\CMS\Component\ComponentHelper::$presentation = ['selection_unavailable_image_enabled' => (int) $image,
            'selection_unavailable_image' => 'images/placeholder.jpg', 'selection_unavailable_icon_enabled' => (int) $icon];
        foreach (['', '1', '0'] as $visibility) {
            $visible = $visibility === '' ? (bool) $global : $visibility === '1';
            \Joomla\CMS\Plugin\PluginHelper::$params = ['show_unavailable' => $visibility];
            $mixed = $config; $mixed['blocks'][0]['articles'] = [1, 999, 1];
            $mixed['blocks'][0]['presentation']['layout'] = $layout;
            $doc = $dispatch($token($mixed), ['purpose' => $purpose]);
            check($doc->get('errors') === ['previous'], 'Unavailable email failed');
            $html = $doc->get('html');
            check(str_contains($html, 'COM_SMARTBROWSER_SELECTION_UNAVAILABLE') === $visible, 'Email visibility lost');
            check(str_contains($html, 'placeholder.jpg') === ($visible && $image), 'Email configured image lost');
            check(!str_contains($html, 'fa-ban') && !str_contains($html, 'article:999') && !str_contains($html, '<script'), 'Unsafe email notice');
            check(strpos($html, 'Public title') !== false, 'Available item changed');
            \Joomla\CMS\Layout\FileLayout::$override = static function ($data) use ($layout) {
                check($data['layout'] === $layout, 'Email override received wrong view');
                if (empty($data['unavailable'])) { return 'AVAILABLE'; }
                check(!$data['header_only'] && $data['fallback_reason'] === null && $data['item']['url'] === '' && $data['item']['id'] === 0, 'Email neutral model leaked');
                return 'UNAVAILABLE';
            };
            $overridden = $dispatch($token($mixed), ['purpose' => $purpose]);
            check(str_contains($overridden->get('html'), 'UNAVAILABLE') === $visible, 'Email override bypassed');
            if ($visible) { check(strpos($overridden->get('html'), 'AVAILABLE') < strpos($overridden->get('html'), 'UNAVAILABLE'), 'Mixed order lost'); }
            \Joomla\CMS\Layout\FileLayout::$override = null;
        }
    }
    echo "Email provider fixtures passed; no mail transport used\n";
}
