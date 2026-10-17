<?php
namespace Joomla\CMS\Language { class Text { public static function _($key) { return $key; } } }
namespace {
    define('_JEXEC', 1);
    $support = dirname(__DIR__, 2) . '/pkg_smartbrowser/package/component/admin/src/Support/';
    if (!is_file($support . 'SelectionFieldSupport.php')) { fwrite(STDERR, 'Local SmartBrowser public API required.'); exit(3); }
    spl_autoload_register(static function ($class) use ($support) {
        $prefix = 'SuperSoft\\Component\\Smartbrowser\\Administrator\\Support\\';
        if (str_starts_with($class, $prefix)) {
            $path = $support . substr($class, strlen($prefix)) . '.php';
            if (is_file($path)) require_once $path;
        }
    });
    use SuperSoft\Component\Smartbrowser\Administrator\Support\SelectionFieldSupport as API;
    $profile = ['article.displayedName' => (object) [], 'visual.thumbnailOverride' => (object) [], 'visual.iconOverride' => ['presentation' => 'secondary'], 'visual.presentation' => ['presentation' => 'secondary']];
    $config = API::configuration(['adapter' => 'articles', 'multiple' => true, 'selection_profile' => json_encode($profile)]);
    $entry = ['selection' => ['adapter' => 'articles', 'id' => 'article:4'], 'usage' => [
        'article.displayedName' => 'Local name', 'visual.thumbnailOverride' => ['adapter' => 'media', 'id' => 'local:/cover.png'],
        'visual.iconOverride' => 'fas fa-star', 'visual.presentation' => 'both', 'ext.smartcrop.crop' => ['ratio' => '1:1'],
    ]];
    $resolve = static fn($ref) => ['id' => $ref['id'], 'title' => 'Original title', 'kind' => 'item', 'selectable' => true,
        'type' => $ref['adapter'] === 'articles' ? 'article' : 'image', 'image' => $ref['adapter'] === 'media' ? '/images/cover.png#joomlaImage://local-images/cover.png?crop=10,10,80,80' : '/images/original.png'];
    $prepared = API::prepare(json_encode(['version' => 1, 'items' => [$entry]]), $config, $resolve, true);
    $item = $prepared['items'][0];
    if ($prepared['invalid'] || $item['displayedName'] !== 'Local name' || $item['effectiveIcon'] !== 'fas fa-star'
        || $item['visualPresentation'] !== 'both' || !str_contains($item['effectiveThumbnail'], 'crop=10,10,80,80')
        || $item['usage']['ext.smartcrop.crop'] !== ['ratio' => '1:1']) throw new \RuntimeException('Public Usage preparation lost overrides/metadata.');
    $validated = API::usageValues($resolve($entry['selection']), $entry['usage'], $config, $resolve);
    if ($validated['visual.thumbnailOverride'] !== $entry['usage']['visual.thumbnailOverride']) throw new \RuntimeException('Public thumbnail reference validation failed.');
    $unavailable = API::prepare(json_encode(['version' => 1, 'items' => [$entry]]), $config,
        static fn($ref) => $ref['adapter'] === 'media' ? ['id' => $ref['id'], 'unavailable' => true] : $resolve($ref), true);
    if ($unavailable['items'][0]['effectiveThumbnail'] !== '/images/original.png') throw new \RuntimeException('Unavailable thumbnail must fall back to the article image.');
    echo 'Actual SmartBrowser public Usage API assertions passed';
}
