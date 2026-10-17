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
    $directory = $root . '/media/plg_editors-xtd_sourcesbutton/css';
    mkdir($directory, 0777, true);
    $file = $directory . '/sourcesbutton.css';
    try {
        copy(dirname(__DIR__) . '/package/plugins/editors-xtd/sourcesbutton/media/css/sourcesbutton.css', $file);
        $assets = new class {
            public array $styles = [];
            public function useStyle(...$args) { return $this; }
            public function usePreset(...$args) { return $this; }
            public function useScript(...$args) { return $this; }
            public function registerAndUseStyle($name, $uri, $options) { $this->styles[$name] = $options; }
            public function registerAndUseScript(...$args) {}
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
        };
        require dirname(__DIR__) . '/package/plugins/editors-xtd/sourcesbutton/src/Extension/SourcesButton.php';
        $plugin = new \SuperSoft\Plugin\EditorsXtd\SourcesButton\Extension\SourcesButton();
        $plugin->loadAssets();
        $hash = hash_file('sha256', $file);
        $url = $document->options['plg_editorsxtd_sourcesbutton.inline']['contentCss'];
        if ($url !== '/subdirectory/media/plg_editors-xtd_sourcesbutton/css/sourcesbutton.css?v=' . $hash
            || $assets->styles['plg.sources.inline']['version'] !== $hash) throw new \RuntimeException('Editor iframe and outer document must use the same content-versioned stylesheet.');
        echo 'Preview stylesheet content hash assertions passed';
    } finally {
        if (is_file($file)) unlink($file);
        foreach ([$directory, dirname($directory), $root . '/media', $root] as $path) if (is_dir($path)) rmdir($path);
    }
}
