<?php
namespace Joomla\Registry {
    class Registry { public function get($key, $default = null) { return $default; } }
}
namespace Joomla\Database { interface DatabaseInterface {} }
namespace Joomla\String {
    class StringHelper { public static function substr($value, $start, $length) { return substr($value, $start, $length); } }
}
namespace Joomla\CMS\Component { class ComponentHelper { public static function isEnabled($name) { return true; } } }
namespace Joomla\CMS\HTML { class HTMLHelper { public static function _($name, $text, ...$args) { return $text; } } }
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
    check(array_keys($items) === ['article:11'], 'Unavailable resource survived readonly preparation');
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
        check(array_keys($data) === ['item', 'layout', 'selected', 'purpose', 'parent_article_id'], 'Item layout contract changed');
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
    check($render->invoke($renderer, $article, 'contents', $items['article:11'], 'output', 11) === '', 'Contents self-embedding allowed');
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
