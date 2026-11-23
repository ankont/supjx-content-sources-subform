<?php
namespace Joomla\CMS { class Factory { public static $app; public static function getApplication() { return self::$app; } } }
namespace Joomla\CMS\Plugin {
    class CMSPlugin {
        protected $params;
        public function __construct() { $this->params = new \Joomla\Registry\Registry(); }
        public function loadLanguage() {}
    }
}
namespace Joomla\CMS\Component { class ComponentHelper { public static function isEnabled($name) { return false; } } }
namespace Joomla\CMS\Form { class Form { public static function getInstance(...$args) { throw new \RuntimeException('No filter fixture'); } } }
namespace Joomla\CMS\Language { class Text { public static function _($key) { return $key; } } }
namespace Joomla\CMS\Session { class Session { public static function getFormToken() { return 'fixture-token'; } } }
namespace Joomla\CMS\Uri { class Uri { public static function root($path = false) { return '/subdirectory'; } public static function base() { return '/subdirectory/administrator/'; } } }
namespace Joomla\CMS\Log { class Log { public const WARNING = 1; public static function add(...$args) {} } }
namespace {
    $site = $argv[1] ?? dirname(__DIR__, 3) . '/JoomlaTest/site';
    if (!is_file($site . '/libraries/vendor/autoload.php')) { fwrite(STDERR, 'Local Joomla libraries required.'); exit(3); }
    define('_JEXEC', 1);
    require $site . '/libraries/vendor/autoload.php';
    $root = sys_get_temp_dir() . '/sources-preview-styles-' . getmypid();
    define('JPATH_ROOT', $root);
    define('JPATH_ADMINISTRATOR', $root);
    $directory = $root . '/media/plg_editors-xtd_sourcesbutton/css';
    mkdir($directory, 0777, true);
    $file = $directory . '/sourcesbutton.css';
    $moduleDirectory = dirname($directory) . '/js'; mkdir($moduleDirectory); $moduleFiles = [];
    foreach (glob(dirname(__DIR__) . '/package/plugins/editors-xtd/sourcesbutton/media/js/*.js') as $source) {
        $target = $moduleDirectory . '/' . basename($source); copy($source, $target); $moduleFiles[] = $target;
    }
    try {
        copy(dirname(__DIR__) . '/package/plugins/editors-xtd/sourcesbutton/media/css/sourcesbutton.css', $file);
        $assets = new class {
            public array $styles = [];
            public array $scripts = [];
            public function useStyle(...$args) { return $this; }
            public function usePreset(...$args) { return $this; }
            public function useScript(...$args) { return $this; }
            public function registerAndUseStyle($name, $uri, $options) { $this->styles[$name] = $options; }
            public function registerAndUseScript($name, $uri, $options = []) { $this->scripts[$name] = ['uri' => $uri, 'options' => $options]; }
        };
        $document = new class($assets) {
            public array $options = [];
            public function __construct(public $assets) {}
            public function getWebAssetManager() { return $this->assets; }
            public function addScriptOptions($key, $value) { $this->options[$key] = $value; }
        };
        \Joomla\CMS\Factory::$app = new class($document) {
            public function __construct(public $document) {}
            public function getDocument() { return $this->document; }
            public function getLanguage() { return new class { public function load(...$args) {} }; }
        };
        require dirname(__DIR__) . '/package/plugins/editors-xtd/sourcesbutton/src/Extension/SourcesButton.php';
        $plugin = new \SuperSoft\Plugin\EditorsXtd\SourcesButton\Extension\SourcesButton();
        $plugin->loadAssets();
        $expectedVersion = $plugin::moduleVersion($moduleDirectory); $map = ['imports' => []];
        foreach ($moduleFiles as $module) {
            $asset = $assets->scripts['plg.sources.module.' . basename($module)] ?? null;
            if (!$asset || $asset['options']['importmap'] !== true || $asset['options']['version'] !== $expectedVersion) throw new \RuntimeException('Every dependency must be mapped with the graph hash.');
            $url = '/subdirectory/media/plg_editors-xtd_sourcesbutton/js/' . basename($module);
            if ($asset['options']['importmapName'] !== $url) throw new \RuntimeException('Subdirectory module map key');
            $map['imports'][$url] = $url . '?' . $expectedVersion;
        }
        if ($assets->scripts['plg.sources.inline']['options']['version'] !== $expectedVersion) throw new \RuntimeException('Entry script must use the graph version, not Joomla auto.');
        if (in_array('--map', $argv, true)) echo json_encode($map) . "\n";
        $moduleFile = $moduleDirectory . '/preview-ui.js';
        $firstVersion = $plugin::moduleVersion($moduleDirectory);
        file_put_contents($moduleFile, '// changed module');
        if ($firstVersion === $plugin::moduleVersion($moduleDirectory)) throw new \RuntimeException('Dependency-only changes must invalidate the graph version.');
        $hash = hash_file('sha256', $file);
        $url = $document->options['plg_editorsxtd_sourcesbutton.inline']['contentCss'];
        if ($url !== '/subdirectory/media/plg_editors-xtd_sourcesbutton/css/sourcesbutton.css?v=' . $hash
            || $assets->styles['plg.sources.inline']['version'] !== $hash) throw new \RuntimeException('Editor iframe and outer document must use the same content-versioned stylesheet.');
        echo 'Preview stylesheet content hash assertions passed';
    } finally {
        if (is_file($file)) unlink($file);
        foreach ($moduleFiles as $module) if (is_file($module)) unlink($module);
        if (is_dir($moduleDirectory)) rmdir($moduleDirectory);
        foreach ([$directory, dirname($directory), $root . '/media', $root] as $path) if (is_dir($path)) rmdir($path);
    }
}
