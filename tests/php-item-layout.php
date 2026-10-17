<?php
namespace Joomla\CMS {
    class Factory { public static $app; public static function getApplication() { return self::$app; } }
}
namespace Joomla\CMS\Component { class ComponentHelper { public static function isEnabled($name) { return true; } } }
namespace {
    $site = dirname(__DIR__, 3) . '/JoomlaTest/site';
    if (!is_file($site . '/libraries/src/Layout/FileLayout.php')) { exit(3); }
    define('_JEXEC', 1); define('JDEBUG', false);
    $root = sys_get_temp_dir() . '/sources-layout-' . uniqid();
    define('JPATH_ROOT', $root); define('JPATH_SITE', $root); define('JPATH_ADMINISTRATOR', $root . '/administrator');
    define('JPATH_THEMES', $root . '/templates');
    require $site . '/libraries/vendor/autoload.php';
    spl_autoload_register(static function ($class) use ($site) {
        if (str_starts_with($class, 'Joomla\\CMS\\')) {
            $path = $site . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
            if (is_file($path)) require_once $path;
        }
    });
    require dirname(__DIR__) . '/package/plugins/content/sources/src/Support/InlineRenderer.php';
    $app = new class {
        public bool $site = true;
        public function isClient($client) { return $client === ($this->site ? 'site' : 'administrator'); }
        public function getTemplate($params = false) { return (object) ['template' => $this->site ? 'child' : 'atum', 'parent' => $this->site ? 'parent' : '']; }
    };
    \Joomla\CMS\Factory::$app = $app;
    $db = new class {
        public function getQuery($new) { return new class { public function __call($name, $args) { return $this; } }; }
        public function quote($value) { return "'$value'"; }
        public function setQuery(...$args) { return $this; }
        public function loadResult() { return 'child'; }
    };
    $reflection = new \ReflectionClass(\SuperSoft\Plugin\Content\Sources\Support\InlineRenderer::class);
    $renderer = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('app')->setValue($renderer, $app);
    $reflection->getProperty('db')->setValue($renderer, $db);
    $render = $reflection->getMethod('resourceItem');
    $dirs = []; $files = [];
    $make = static function ($path) use (&$make, &$dirs) {
        if (is_dir($path)) return;
        $make(dirname($path)); mkdir($path); $dirs[] = $path;
    };
    try {
        foreach (['parent' => 'Parent override', 'child' => 'Child override'] as $template => $label) {
            $path = $root . '/templates/' . $template . '/html/layouts/sources/item.php';
            $make(dirname($path)); $files[] = $path;
            file_put_contents($path, '<?php echo "' . $label . ':" . $displayData["parent_article_id"];');
        }
        $item = (object) ['id' => 12]; $selected = ['resource' => ['id' => 'article:12']];
        $check = static function ($expected) use ($render, $renderer, $item, $selected) {
            $html = $render->invoke($renderer, $item, 'cards', $selected, 'preview', 77);
            if ($html !== $expected) throw new \RuntimeException('Incorrect FileLayout lookup: ' . $html);
        };
        $check('Child override:77');
        $app->site = false; $check('Child override:77');
        $app->site = true;
        unlink(array_pop($files));
        (new \ReflectionClass(\Joomla\CMS\Layout\FileLayout::class))->getProperty('cache')->setValue(null, []);
        $check('Parent override:77');
        echo "Actual Joomla item override lookup passed\n";
    } finally {
        foreach ($files as $file) if (is_file($file)) unlink($file);
        foreach (array_reverse($dirs) as $dir) rmdir($dir);
    }
}
