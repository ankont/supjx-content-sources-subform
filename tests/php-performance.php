<?php
namespace Joomla\CMS\Plugin { class CMSPlugin {} }
namespace Joomla\Component\Fields\Administrator\Helper {
    class FieldsHelper {
        public static array $fields = [];
        public static int $raw = 0, $prepared = 0, $otherPlugins = 0;
        public static $onPrepare = null;
        public static function getFields($context, $item, $prepare) {
            if ($prepare) {
                self::$prepared++; self::$otherPlugins++;
                if (self::$onPrepare) { (self::$onPrepare)($item); }
            } else { self::$raw++; }
            $fields = unserialize(serialize(self::$fields));
            if ($prepare) foreach ($fields as $field) {
                if ($field->type === 'subform') $field->subform_rows = json_decode($field->rawvalue, true);
            }
            return $fields;
        }
    }
}
namespace {
    ob_start(); require __DIR__ . '/php-renderer.php'; ob_end_clean();
    require dirname(__DIR__) . '/package/plugins/content/sources/src/Extension/Sources.php';
    use Joomla\CMS\Factory;
    use Joomla\CMS\Layout\FileLayout;
    use Joomla\CMS\Layout\LayoutHelper;
    use Joomla\Registry\Registry;
    use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
    use SuperSoft\Component\Smartbrowser\Administrator\Support\SelectionFieldSupport as API;
    use SuperSoft\Plugin\Content\Sources\Support\InlineConfig;
    use SuperSoft\Plugin\Content\Sources\Support\InlineRenderer;
    use SuperSoft\Plugin\Content\Sources\Support\RequestCache;
    use SuperSoft\Plugin\Content\Sources\Support\SourceFieldData;

    $config = InlineConfig::decode('{"version":2,"display":"separate","blocks":[{"type":"articles","articles":[11,4]}]}');
    $renderer = new InlineRenderer(new Registry());
    $field = (object) ['id' => 9, 'type' => 'sources', 'rawvalue' => json_encode($config)];
    $a = (object) ['id' => 701]; $b = (object) ['id' => 702];
    $calls = 0; $paint = static function () use (&$calls) { $calls++; return 'painted'; };
    check(SourceFieldData::configuration($field) === $config, 'Data-only configuration changed schema/ordering');
    check(SourceFieldData::withoutRendering(static fn() => SourceFieldData::render($a, $field, $paint)) === '', 'Data-only read painted Sources HTML');
    check($calls === 0 && SourceFieldData::render($a, $field, $paint) === 'painted', 'Explicit rendering must remain available');
    try { SourceFieldData::withoutRendering(static fn() => throw new \RuntimeException('Fixture')); } catch (\RuntimeException $error) {}
    check(SourceFieldData::render($a, $field, $paint) === 'painted', 'Failed data-only consumer leaked its scope');
    $nested = SourceFieldData::render($a, $field, static fn() => 'A' . SourceFieldData::render($b, $field,
        static fn() => 'B' . SourceFieldData::render($a, $field, static fn() => 'cycle')));
    check($nested === 'AB', 'Distinct embedded Sources must render; A-B-A cycle must stop');
    $seen = 0;
    FileLayout::$onRender = static function () use (&$seen, $b, $field) {
        SourceFieldData::render($b, $field, static function () use (&$seen) { $seen++; return 'child'; });
    };
    RequestCache::reset();
    $renderer->render($config, (object) ['id' => 700]);
    check($seen === 0, 'Card metadata consumer triggered full Sources field rendering');
    $config['blocks'][0]['presentation']['layout'] = 'contents';
    $renderer->render($config, (object) ['id' => 700], 'render', 'preview');
    check($seen === 0, 'Contents preview must not expand nested Sources fields');
    $renderer->render($config, (object) ['id' => 700], 'render', 'output');
    check($seen === 2, 'Legitimate Contents must permit embedded Sources fields');
    FileLayout::$onRender = null;

    $previewContents = (new \ReflectionClass($renderer))->getMethod('previewContents');
    FieldsHelper::$fields = [$field]; RequestCache::reset();
    $previewBody = static function ($nestedPreview) use ($renderer, $config) {
        check(InlineRenderer::isContentsPreview(), 'Missing preview boundary');
        check($renderer->render($config, (object) ['id' => 800]) === '', 'Nested renderer expanded in preview');
        $article = (object) ['text' => '<p>Body</p>{sources:' . json_encode($config) . '}'];
        $params = new Registry();
        (new \SuperSoft\Plugin\Content\Sources\Extension\Sources())->onContentPrepare('com_content.article', $article, $params);
        check($article->text === '<p>Body</p>', 'Content plugin expanded or exposed nested JSON during preview');
        return '<article><p>Original body</p>' . $nestedPreview() . $nestedPreview() . '</article>';
    };
    $html = $previewContents->invoke($renderer, $a, $previewBody);
    check(str_starts_with($html, '<article><p>Original body</p>') && str_ends_with($html, '</article>')
        && substr_count($html, 'data-sources-preview-token=') === 1 && str_contains($html, 'sources-preview-list'),
        'Field-only article must retain body and show one nested manual-list block');
    FieldsHelper::$fields = []; RequestCache::reset();
    check($previewContents->invoke($renderer, $b, static fn() => 'Body') === 'Body', 'Article without Sources gained a placeholder');
    $rawToken = '{sources:' . json_encode($config) . '}';
    $inlineArticle = (object) ['id' => 703, 'introtext' => '<p>Before</p>' . $rawToken . '<p>Middle</p>',
        'fulltext' => '<p>After split</p>' . $rawToken . '<p>End</p>'];
    $renderPreviewArticle = static function ($slot, $article) {
        $params = new Registry(); $article->text = $article->introtext . $article->fulltext;
        (new \SuperSoft\Plugin\Content\Sources\Extension\Sources())->onContentPrepare('com_content.article', $article, $params);
        return '<article>' . $article->text . $slot() . '</article>';
    };
    $html = $previewContents->invoke($renderer, $inlineArticle, $renderPreviewArticle);
    check(substr_count($html, 'data-sources-preview-token=') === 2, 'Each inline token must have exactly one preview');
    $first = strpos($html, 'data-sources-preview-token='); $second = strpos($html, 'data-sources-preview-token=', $first + 1);
    check(strpos($html, 'Before') < $first && $first < strpos($html, 'Middle')
        && strpos($html, 'After split') < $second && $second < strpos($html, 'End'), 'Inline preview moved out of its original paragraph position');
    check($inlineArticle->introtext === '<p>Before</p>' . $rawToken . '<p>Middle</p>'
        && $inlineArticle->fulltext === '<p>After split</p>' . $rawToken . '<p>End</p>', 'Preview changed original article content');
    $resolved = $renderer->resolve($config['blocks'][0]); $prepared = $renderer->prepareOutput($resolved);
    $layoutArticle = clone $resolved[0]; $layoutArticle->introtext = $inlineArticle->introtext; $layoutArticle->fulltext = $inlineArticle->fulltext;
    $resourceItem = (new \ReflectionClass($renderer))->getMethod('resourceItem');
    $html = $resourceItem->invoke($renderer, $layoutArticle, 'contents', $prepared['article:' . $layoutArticle->id], 'preview', 999);
    check(substr_count($html, 'data-sources-preview-token=') === 2 && strpos($html, 'Before') < strpos($html, 'data-sources-preview-token='),
        'Actual default layout did not receive the detached, position-preserving preview article');
    FieldsHelper::$fields = [$field]; RequestCache::reset();
    $html = $previewContents->invoke($renderer, $inlineArticle, $renderPreviewArticle);
    check(substr_count($html, 'data-sources-preview-token=') === 3 && strrpos($html, 'data-sources-preview-token=') > strpos($html, 'End'),
        'Independent inline and field Containers must each retain their preview');
    $badField = clone $field; $badField->rawvalue = '{invalid'; FieldsHelper::$fields = [$badField]; RequestCache::reset();
    check($previewContents->invoke($renderer, $a, static fn() => 'Body') === 'Body', 'Malformed configuration gained a misleading placeholder');
    try { $previewContents->invoke($renderer, $a, static fn() => throw new \RuntimeException('Preview failure')); } catch (\RuntimeException $error) {}
    check(!InlineRenderer::isContentsPreview(), 'Failed preview leaked its boundary');
    FieldsHelper::$fields = []; RequestCache::reset();

    $block = $config['blocks'][0];
    $bench = [];
    foreach ([false, true] as $cached) {
        RequestCache::reset(); API::$calls = 0; Factory::$db->queries = []; LayoutHelper::$calls = [];
        $start = hrtime(true);
        for ($i = 0; $i < 100; $i++) {
            if (!$cached) RequestCache::reset();
            $prepared = $renderer->prepareOutput($renderer->resolve($block));
        }
        $bench[] = ['cache' => $cached, 'iterations' => 100, 'queries' => count(Factory::$db->queries),
            'prepare_calls' => API::$calls, 'html_calls' => count(LayoutHelper::$calls), 'ms' => (hrtime(true) - $start) / 1e6];
    }
    check($bench[0]['prepare_calls'] === 100 && $bench[1]['prepare_calls'] === 1, 'Repeated selection preparation did not reuse snapshots');
    check($bench[1]['queries'] === 1 && $bench[1]['html_calls'] === 0, 'Data preparation must reuse queries without rendering HTML');
    RequestCache::reset(); API::$calls = 0; LayoutHelper::$calls = [];
    $config['blocks'][0]['presentation']['layout'] = 'cards';
    $renderer->render($config, (object) ['id' => 700]);
    $renderer->render($config, (object) ['id' => 700]);
    check(API::$calls === 1 && count(LayoutHelper::$calls) === 4, 'Request snapshots must never cache visual layout/presentation work');
    $prepared['article:11']['resource']['metadata']['object']->value = 'mutated';
    $rows = $renderer->resolve($block); $rows[0]->title = 'mutated';
    check($renderer->resolve($block)[0]->title === 'Article 11', 'Article snapshots share mutable objects');
    check($renderer->prepareOutput($renderer->resolve($block))['article:11']['resource']['metadata']['object']->value === 'original', 'Prepared snapshots share nested mutable metadata');
    $entry = static fn($name) => ['selection' => ['adapter' => 'articles', 'id' => 'article:11'], 'usage' => ['article.displayedName' => $name, 'ext.fixture' => ['keep' => true]]];
    $one = $block; $one['articles'] = [$entry('One')];
    $two = $block; $two['articles'] = [$entry('Two')];
    $first = $renderer->prepareOutput($renderer->resolve($one));
    $second = $renderer->prepareOutput($renderer->resolve($two));
    check($first['article:11']['usage']['article.displayedName'] === 'One' && $second['article:11']['usage']['article.displayedName'] === 'Two', 'Usage-local selections collided');
    $previous = API::$calls;
    Factory::$userId = 2; Factory::$levels = [1];
    $renderer->prepareOutput($renderer->resolve($two));
    check(API::$calls === $previous + 1, 'Identity/access context reused a different principal snapshot');
    Factory::$userId = 1; Factory::$levels = [1,2];
    RequestCache::reset(); API::$unavailable = true; API::$calls = 0;
    check($renderer->prepareOutput($renderer->resolve($one))['article:11']['unavailable'] === true, 'Unavailable reference lost');
    check(!isset($renderer->prepareOutput($renderer->resolve($one))['article:11']['resource']) && API::$calls === 1, 'Unavailable snapshot not reused safely or leaked resource data');
    API::$unavailable = false; RequestCache::reset();

    echo json_encode(['fixture' => 'same-code cache-off/cache-on, no real SQL/network', 'bench' => $bench], JSON_PRETTY_PRINT) . "\n";
}
