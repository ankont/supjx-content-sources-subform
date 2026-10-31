<?php
namespace Joomla\Registry {
    class Registry { public function __construct(private array $values = []) {} public function get($key, $default = null) { return $this->values[$key] ?? $default; } }
}
namespace Joomla\Database { interface DatabaseInterface {} }
namespace Joomla\String {
    class StringHelper { public static function substr($value, $start, $length) { return substr($value, $start, $length); } }
}
namespace Joomla\CMS\Component { class ComponentHelper {
    public static int $globalVisibility = 0;
    public static array $presentation = [];
    public static function isEnabled($name) { return true; }
    public static function getParams($name) { return new \Joomla\Registry\Registry(['selection_show_unavailable' => self::$globalVisibility] + self::$presentation); }
} }
namespace Joomla\CMS\HTML { class HTMLHelper { public static function _($name, $text, ...$args) { return $text; } } }
namespace Joomla\CMS\Language { class Text { public static function _($key) { return 'Read the full article by following its title.'; } } }
namespace SuperSoft\Component\Smartbrowser\Administrator\Support {
    class SelectionFieldSupport {
        public static function configuration($value) { return $value; }
        public static function prepare($raw, $config, $resolver, $readOnly) {
            if ($readOnly !== true) throw new \RuntimeException('Not readonly');
            $data = json_decode($raw, true);
            foreach ($data['items'] as &$entry) {
                $usage = $entry['usage'];
                $entry += ['resource' => ['id' => $entry['selection']['id'], 'type' => 'article',
                    'title' => 'Original title', 'metadata' => ['publicUrl' => '/article', 'retained' => 'metadata']],
                    'effectiveThumbnail' => 'images/original.jpg',
                    'effectiveImageAddress' => isset($usage['visual.thumbnailOverride']) ? 'images/override.jpg#joomlaImage://local-images/override.jpg?opaque=data' : 'images/original.jpg',
                    'displayedName' => $usage['article.displayedName'] ?? 'Original title',
                    'effectiveIcon' => $usage['visual.iconOverride'] ?? null,
                    'visualPresentation' => $usage['visual.presentation'] ?? null,
                    'unavailable' => $entry['selection']['id'] === 'article:4'];
            }
            return $data;
        }
    }
}
namespace {
    define('_JEXEC', 1);
    $smart = dirname(__DIR__, 2) . '/pkg_smartbrowser';
    require __DIR__ . '/fixtures/sources-output-joomla.php';
    require $smart . '/package/component/admin/src/Support/SelectionOutputRenderer.php';
    require dirname(__DIR__) . '/package/plugins/content/sources/src/Support/InlineConfig.php';
    require dirname(__DIR__) . '/package/plugins/content/sources/src/Support/InlineTokens.php';
    require dirname(__DIR__) . '/package/plugins/content/sources/src/Support/InlineRenderer.php';
    require_once dirname(__DIR__) . '/package/plugins/content/sources/src/Support/RequestCache.php';
    require_once dirname(__DIR__) . '/package/plugins/content/sources/src/Support/SourceFieldData.php';
    use Joomla\CMS\Factory;
    use Joomla\CMS\Layout\FileLayout;
    use SuperSoft\Plugin\Content\Sources\Support\InlineConfig;
    use SuperSoft\Plugin\Content\Sources\Support\InlineRenderer;
    use SuperSoft\Component\Smartbrowser\Administrator\Support\SelectionOutputRenderer as Output;
    function check($ok, $message) { if (!$ok) throw new \RuntimeException($message); }
    $dispatcher = new class {
        public int $calls = 0; public array $contexts = [];
        public function dispatch($name, $event) {
            $this->calls++; $this->contexts[] = $event->getArgument('context');
            $event->getArgument('imageElement')->setAttribute('style', 'filter:grayscale(1)');
        }
    };
    Factory::$app = new class($dispatcher) {
        public function __construct(private $dispatcher) {}
        public function bootComponent($name) {}
        public function isClient($client) { return $client === 'site'; }
        public function getDispatcher() { return $this->dispatcher; }
        public function getDocument() { return $this; }
        public function getWebAssetManager() { return $this; }
        public function useStyle($name) {}
    };
    // Keep selection/query tests separate; feed the renderer candidates already checked there.
    $reflection = new \ReflectionClass(InlineRenderer::class);
    $renderer = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('app')->setValue($renderer, Factory::$app);
    $reflection->getProperty('params')->setValue($renderer, new \Joomla\Registry\Registry());
    $prepare = $reflection->getMethod('prepareOutput');
    $render = $reflection->getMethod('resourceItem');
    $usage = ['article.displayedName' => 'Local <name>', 'visual.thumbnailOverride' => ['adapter' => 'media', 'id' => 'local-images:/override.jpg'],
        'visual.iconOverride' => 'fas fa-book', 'visual.presentation' => 'both', 'ext.example.data' => ['keep' => true]];
    $article = (object) ['id' => 11, 'title' => 'Original', 'introtext' => 'Summary', 'fulltext' => '<p>Full body</p>', 'sources_usage' => $usage];
    $unavailable = clone $article; $unavailable->id = 4;
    $before = serialize($article);
    $items = $prepare->invoke($renderer, [$article, $unavailable]);
    check(isset($items['article:11'], $items['article:4']) && $items['article:4']['unavailable'] && !isset($items['article:4']['resource']), 'Readonly preparation must retain neutral unavailable references: ' . json_encode($items));
    foreach ([0,1] as $global) foreach (['','1','0'] as $local) {
        \Joomla\CMS\Component\ComponentHelper::$globalVisibility = $global;
        $reflection->getProperty('params')->setValue($renderer, new \Joomla\Registry\Registry(['show_unavailable' => $local]));
        $expected = $local === '' ? (bool) $global : $local === '1';
        check(Output::showUnavailable($local) === $expected, 'Use Global was cast before resolution');
        foreach (InlineConfig::LAYOUTS as $layout) foreach (['output','preview'] as $purpose) {
            $count = $dispatcher->calls;
            $html = $render->invoke($renderer, $unavailable, $layout, $items['article:4'], $purpose);
            check(($html !== '') === $expected && (! $expected || str_contains($html, 'sources-item--' . $layout)), 'Sources unavailable renderer matrix failed');
            if ($expected) {
                $data = end(FileLayout::$itemData);
                check($data['unavailable'] && !$data['header_only'] && $data['fallback_reason'] === null && $data['layout'] === $layout, 'Unavailable/header-only contract confused');
                check($data['item']->id === 0 && $data['item']->introtext === '' && $data['selected']['selection'] === null && $data['selected']['usage'] === [], 'Private unavailable model leaked');
            }
            check($dispatcher->calls === $count && !str_contains($html, 'Local') && !str_contains($html, 'href="/article') && !str_contains($html, 'override.jpg'), 'Unavailable data or image presentation leaked');
        }
    }
    foreach ([false, true] as $image) foreach ([false, true] as $icon) {
        \Joomla\CMS\Component\ComponentHelper::$presentation = ['selection_unavailable_text' => 'Safe message',
            'selection_unavailable_image_enabled' => (int) $image, 'selection_unavailable_image' => 'images/placeholder.jpg',
            'selection_unavailable_icon_enabled' => (int) $icon, 'selection_unavailable_icon' => 'fa-ban'];
        $reflection->getProperty('params')->setValue($renderer, new \Joomla\Registry\Registry(['show_unavailable' => '1']));
        FileLayout::$overrides['sources.item'] = static function ($data) use ($image, $icon) {
            check($data['unavailable'] && $data['item']->title === 'Safe message', 'Unavailable override bypassed');
            check((bool) $data['unavailable_presentation']['image'] === $image && (bool) $data['unavailable_presentation']['icon'] === $icon, 'Configured visual lost');
            check(!str_contains(json_encode($data), 'Local') && !str_contains(json_encode($data), 'override.jpg'), 'Override received private fields');
            return 'safe-' . $data['layout'];
        };
        foreach (InlineConfig::LAYOUTS as $layout) {
            $count = $dispatcher->calls;
            check($render->invoke($renderer, $unavailable, $layout, $items['article:4'], 'preview') === 'safe-' . $layout, 'Wrong view at override');
            check($dispatcher->calls === $count, 'Forced image presentation');
        }
    }
    unset(FileLayout::$overrides['sources.item']);
    \Joomla\CMS\Component\ComponentHelper::$presentation = [];
    \Joomla\CMS\Component\ComponentHelper::$globalVisibility = 0;
    $reflection->getProperty('params')->setValue($renderer, new \Joomla\Registry\Registry());
    foreach (InlineConfig::LAYOUTS as $layout) {
        foreach (['output', 'preview'] as $purpose) {
            $count = $dispatcher->calls;
            $html = $render->invoke($renderer, $article, $layout, $items['article:11'], $purpose);
            check($dispatcher->calls === $count + 1, 'Expected exactly one presentation per primary image');
            check(str_contains($html, 'data-sb-image-presentation="server"'), 'Server marker lost');
            check(str_contains($html, 'images/override.jpg') && str_contains($html, 'Local &lt;name&gt;'), 'Usage overrides lost');
            check(!str_contains($html, 'ext.example.data'), 'Raw Usage leaked into HTML');
            check(end($dispatcher->contexts)['purpose'] === $purpose, 'Preview purpose not propagated');
            check(end($dispatcher->contexts)['resource']['metadata']['retained'] === 'metadata', 'Metadata lost');
            check(end($dispatcher->contexts)['usage']['ext.example.data']['keep'], 'Opaque extension data lost');
            Output::present($html, $items['article:11'], ['purpose' => $purpose]);
            check($dispatcher->calls === $count + 1, 'Server HTML was presented twice');
            if ($layout === 'contents') check(str_contains($html, 'Full body'), 'Contents lost full article body');
        }
    }
    FileLayout::$overrides['smartbrowser.resource'] = static function ($data) {
        return '<div class="site-identity">' . \Joomla\CMS\Layout\LayoutHelper::render('joomla.html.image', $data['image']) . '</div>';
    };
    $count = $dispatcher->calls;
    $html = $render->invoke($renderer, $article, 'cards', $items['article:11'], 'preview');
    check(str_contains($html, 'site-identity') && $dispatcher->calls === $count + 1, 'Common template override bypassed');
    $count = $dispatcher->calls;
    $received = null;
    FileLayout::$overrides['sources.item'] = static function ($data) use (&$received, $render, $renderer) {
        $received = $data;
        check(array_keys($data) === ['item', 'layout', 'selected', 'purpose', 'parent_article_id', 'showUnavailable', 'unavailable', 'unavailable_presentation', 'header_only', 'fallback_reason', 'depth', 'read_more_text'], 'Item layout contract changed');
        check($render->invoke($renderer, $data['item'], 'cards', $data['selected'], 'preview') === '', 'Override recursion bypassed Sources guard');
        $data['item']->title = 'Override-local title';
        return '<aside>Template-only output</aside>';
    };
    $html = $render->invoke($renderer, $article, 'details', $items['article:11'], 'preview', 77);
    check($html === '<aside>Template-only output</aside>' && $dispatcher->calls === $count, 'Sources forced presentation before or after override');
    check($received['parent_article_id'] === 77 && $received['layout'] === 'details' && $received['purpose'] === 'preview', 'Parent/layout/purpose not passed');
    check($received['selected'] === $items['article:11'], 'Prepared selection changed before override');
    FileLayout::$overrides['sources.item'] = static fn($data) => Output::render($data['selected'], ['purpose' => $data['purpose']]);
    $html = $render->invoke($renderer, $article, 'contents', $items['article:11'], 'output', 77);
    check($dispatcher->calls === $count + 1 && str_contains($html, 'data-sb-image-presentation="server"'), 'Override must own its one renderer call');
    FileLayout::$overrides['sources.item'] = static function ($data) {
        check($data['header_only'] && $data['fallback_reason'] === 'cycle' && $data['item']->introtext === '' && $data['item']->fulltext === '', 'Cycle must publish a detached empty body and header contract');
        return 'Linked header';
    };
    check($render->invoke($renderer, $article, 'contents', $items['article:11'], 'output', 11) === 'Linked header', 'Contents self-reference must retain linked header');
    $seenDepths = [];
    FileLayout::$overrides['sources.item'] = static function ($data) use (&$seenDepths, $render, $renderer) {
        $seenDepths[] = [$data['depth'], $data['header_only'], $data['fallback_reason']];
        if ($data['header_only']) { return 'Depth header'; }
        $child = clone $data['item']; $child->id++;
        return $render->invoke($renderer, $child, 'contents', $data['selected'], 'output', $data['item']->id);
    };
    check($render->invoke($renderer, $article, 'contents', $items['article:11'], 'output', 77) === 'Depth header'
        && $seenDepths === [[1, false, null], [2, false, null], [3, false, null], [4, true, 'depth']], 'Site Contents must expand three levels and retain fourth-level headers');
    check($article->introtext !== '', 'Header fallback mutated original article');
    $reflection->getProperty('params')->setValue($renderer, new \Joomla\Registry\Registry(['contents_levels' => 2]));
    $seenDepths = [];
    check($render->invoke($renderer, $article, 'contents', $items['article:11'], 'output', 77) === 'Depth header'
        && $seenDepths === [[1, false, null], [2, false, null], [3, true, 'depth']], 'Site must respect the same configured depth as email');
    $reflection->getProperty('params')->setValue($renderer, new \Joomla\Registry\Registry());
    FileLayout::$overrides['sources.item'] = static function ($data) { ob_start(); echo 'partial'; throw new \RuntimeException('Fixture error'); };
    $level = ob_get_level();
    try { $render->invoke($renderer, $article, 'cards', $items['article:11'], 'output'); }
    catch (\RuntimeException $error) {}
    check(ob_get_level() === $level, 'Failed override leaked an output buffer');
    FileLayout::$overrides['sources.item'] = static fn($data) => 'Recovered';
    check($render->invoke($renderer, $article, 'cards', $items['article:11'], 'output') === 'Recovered', 'Failed override left recursion locked');
    check(serialize($article) === $before, 'Rendering mutated the article or Usage');
    echo "Actual SmartBrowser output integration passed\n";
}
