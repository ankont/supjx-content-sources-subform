<?php
namespace Joomla\CMS {
    class Factory {
        public static function getApplication() {
            return new class {
                public function getTemplate($parameters = false) { return (object) ['template' => 'fixture', 'parent' => '', 'inheritable' => false]; }
            };
        }
    }
}
namespace Joomla\CMS\Uri {
    class Uri {
        public static string $prefix = '';
        public static function root($pathOnly = false) { return self::$prefix; }
    }
}
namespace {
    $site = $argv[1] ?? dirname(__DIR__, 3) . '/JoomlaTest/site';
    if (!is_file($site . '/libraries/src/WebAsset/WebAssetItem.php')) {
        fwrite(STDERR, 'Local Joomla libraries required for real WebAsset resolver verification.'); exit(3);
    }
    define('_JEXEC', 1); define('JDEBUG', false);
    $root = sys_get_temp_dir() . '/sources-assets-' . getmypid();
    define('JPATH_ROOT', $root); define('JPATH_PUBLIC', $root); define('JPATH_THEMES', $root . '/templates');
    require $site . '/libraries/vendor/autoload.php';
    spl_autoload_register(static function ($class) use ($site) {
        $prefix = 'Joomla\\CMS\\';
        if (str_starts_with($class, $prefix)) {
            $file = $site . '/libraries/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) require_once $file;
        }
    });
    function check($condition, $message) { if (!$condition) throw new \RuntimeException($message); }
    $package = dirname(__DIR__) . '/package/plugins';
    $destination = $root . '/media/plg_editors-xtd_sourcesbutton';
    $created = [];
    try {
        foreach (['js/sourcesbutton.js', 'js/inline.js', 'js/usage.js', 'js/builder.js', 'js/field.js', 'css/sourcesbutton.css'] as $file) {
            $directory = dirname($destination . '/' . $file);
            if (!is_dir($directory)) mkdir($directory, 0777, true);
            copy($package . '/editors-xtd/sourcesbutton/media/' . $file, $destination . '/' . $file);
            $created[] = $destination . '/' . $file;
        }
        $registrations = [];
        foreach (['editors-xtd/sourcesbutton/src/Extension/SourcesButton.php', 'content/sources/src/Extension/Sources.php', 'fields/sources/src/Field/SourcesField.php', 'fields/sources/tmpl/sources.php'] as $source) {
            preg_match_all('/registerAndUse(Script|Style)\(\s*\'[^\']+\'\s*,\s*\'([^\']+)\'/s', file_get_contents($package . '/' . $source), $matches, PREG_SET_ORDER);
            $registrations = array_merge($registrations, $matches);
        }
        check(count($registrations) === 5, 'Verify every Sources script/style registration.');
        foreach (['', '/joomla-subdirectory'] as $prefix) {
            \Joomla\CMS\Uri\Uri::$prefix = $prefix;
            foreach ($registrations as $registration) {
                $asset = new \Joomla\CMS\WebAsset\WebAssetItem('sources-test', $registration[2]);
                $url = $asset->getUri();
                $folder = $registration[1] === 'Script' ? 'js' : 'css';
                check($url === $prefix . '/media/plg_editors-xtd_sourcesbutton/' . $folder . '/' . basename($registration[2]), 'Registered asset must resolve to the installed media file: ' . $registration[2] . ' => ' . $url);
            }
            $broken = new \Joomla\CMS\WebAsset\WebAssetItem('old-broken-path', 'plg_editors-xtd_sourcesbutton/js/sourcesbutton.js');
            check($broken->getUri() === '', 'Reproduce 2.0.0 failure: duplicated js segment silently resolves to no script.');
        }
        echo 'Joomla WebAsset resolver assertions passed';
    } finally {
        foreach ($created as $file) { if (is_file($file)) unlink($file); }
        foreach ([$destination . '/js', $destination . '/css', $destination, $root . '/media', $root] as $directory) {
            if (is_dir($directory)) rmdir($directory);
        }
    }
}
