<?php
namespace Joomla\CMS {
    class Factory { public static $app; public static function getApplication() { return self::$app; } }
}
namespace Joomla\CMS\Component { class ComponentHelper { public static function isEnabled($name) { return true; } } }
namespace Joomla\CMS\Uri { class Uri { public static function root() { return 'https://example.test/'; } } }
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
    require_once dirname(__DIR__) . '/package/plugins/content/sources/src/Support/RequestCache.php';
    require_once dirname(__DIR__) . '/package/plugins/content/sources/src/Support/SourceFieldData.php';
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
    $reflection->getProperty('params')->setValue($renderer, new \Joomla\Registry\Registry());
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
        foreach (['tag', 'category'] as $type) {
            foreach (['sources.' . $type . '-intro', 'sources.email.' . $type . '-intro'] as $name) {
                $relative = str_replace('.', '/', $name) . '.php';
                $path = $root . '/templates/child/html/layouts/' . $relative;
                $make(dirname($path)); $files[] = $path;
                file_put_contents($path, '<?php echo "Intro override:" . $displayData["purpose"];');
                $layout = new \Joomla\CMS\Layout\FileLayout($name, null, ['component'=>'com_content','client'=>0]);
                $fallback = dirname(__DIR__) . '/package/plugins/' . (str_contains($name, '.email.') ? 'newsletter/sourcesemail' : 'content/sources') . '/layouts';
                $layout->setIncludePaths([$root . '/templates/child/html/layouts', $fallback]);
                if ($layout->render(['purpose'=>'preview']) !== 'Intro override:preview') { throw new \RuntimeException('Introduction override bypassed'); }
                unlink($path); array_pop($files);
                (new \ReflectionClass(\Joomla\CMS\Layout\FileLayout::class))->getProperty('cache')->setValue(null, []);
                $entity = (object) ['id'=>8, 'title'=>'Native title', 'description'=>'Native description', 'params'=>'{"image":"images/category.jpg"}', 'images'=>'{"image_intro":"images/tag.jpg"}'];
                $data = ['entity'=>str_contains($name, '.email.') ? ['id'=>8,'title'=>'Native title','description'=>'Native description','image'=>'https://example.test/images/email.jpg'] : $entity,
                    'type'=>$type === 'tag' ? 'tags' : 'categories', 'description_html'=>'Prepared native description'];
                $html = $layout->render($data);
                if (!str_contains($html, 'Native title') || !str_contains($html, 'description')) { throw new \RuntimeException('Native introduction fallback failed'); }
                if (!str_contains($html, '<img')) { throw new \RuntimeException('Native introduction image lost'); }
                foreach (['default','simple','heading','card','collapsible'] as $introMode) foreach (['both','title','description','none'] as $elements) {
                    $data['presentation']=['layout'=>$introMode,'elements'=>$elements,'class'=>'feature'];
                    $html=$layout->render($data);
                    if (str_contains($html,'Native title') !== in_array($elements,['both','title'],true)) { throw new \RuntimeException('Introduction title visibility lost'); }
                    if (str_contains($html,'description') !== in_array($elements,['both','description'],true)) { throw new \RuntimeException('Introduction description visibility lost'); }
                    if (!str_contains($html,'feature')) { throw new \RuntimeException('Introduction class lost'); }
                }
            }
        }
        foreach (['sources.group','sources.email.group'] as $name) {
            $paired = new \Joomla\CMS\Layout\FileLayout($name,null,['component'=>'com_content','client'=>0]);
            $fallback=dirname(__DIR__) . '/package/plugins/' . (str_contains($name,'.email.') ? 'newsletter/sourcesemail' : 'content/sources') . '/layouts';
            $paired->setIncludePaths([$root . '/templates/child/html/layouts',$fallback]);
            $data=['introductions_html'=>'<h2>Intro</h2><p>Description</p>','results_html'=>'<ul><li>Result</li></ul>','collapsible'=>true];
            $html=$paired->render($data);
            if (!str_contains($html,'Result') || !str_contains($html,'Description')) { throw new \RuntimeException('Paired group lost content'); }
            if ($name === 'sources.group' && !(strpos($html,'</summary>') < strpos($html,'<ul>') && str_contains($html,'</details>'))) { throw new \RuntimeException('Disclosure must fold results, not description'); }
            if ($name === 'sources.email.group' && str_contains($html,'<details')) { throw new \RuntimeException('Email disclosure is not portable'); }
            $path=$root . '/templates/child/html/layouts/' . str_replace('.','/',$name) . '.php';$make(dirname($path));$files[]=$path;
            file_put_contents($path,'<?php echo "Paired override:" . $displayData["results_html"];');
            (new \ReflectionClass(\Joomla\CMS\Layout\FileLayout::class))->getProperty('cache')->setValue(null,[]);
            if (!str_starts_with($paired->render($data),'Paired override:')) { throw new \RuntimeException('Group override bypassed'); }
        }
        echo "Actual Joomla item override lookup passed\n";
    } finally {
        foreach ($files as $file) if (is_file($file)) unlink($file);
        foreach (array_reverse($dirs) as $dir) rmdir($dir);
    }
}
