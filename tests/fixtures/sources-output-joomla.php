<?php
namespace Joomla\CMS {
    final class Factory { public static object $app; public static function getApplication() { return self::$app; } }
}
namespace Joomla\CMS\Log {
    final class Log { const WARNING = 4; public static function add(...$args) {} }
}
namespace Joomla\CMS\Plugin {
    final class PluginHelper { public static function importPlugin(...$args) {} }
}
namespace Joomla\Event {
    final class Event {
        public function __construct(private string $name, private array $arguments) {}
        public function getName() { return $this->name; }
        public function getArgument($key) { return $this->arguments[$key] ?? null; }
    }
}
namespace Joomla\CMS\Layout {
    final class FileLayout {
        public static array $overrides = [];
        public static array $itemData = [];
        private array $paths = [];
        public function __construct(private string $id, $base, array $options) {
            if ($base !== null || $options['client'] !== 0) throw new \RuntimeException('Override lookup bypassed');
        }
        public function getDefaultIncludePaths() { return ['/fixture/template/html/layouts']; }
        public function setIncludePaths($paths) {
            if ($paths[0] !== '/fixture/template/html/layouts') throw new \RuntimeException('Template is not first');
            $this->paths = $paths; return $this;
        }
        public function render($displayData) {
            if ($this->id === 'sources.item') self::$itemData[] = $displayData;
            if (isset(self::$overrides[$this->id])) return (self::$overrides[$this->id])($displayData);
            $file = $this->id === 'sources.item'
                ? end($this->paths) . '/sources/item.php'
                : dirname(__DIR__, 3) . '/pkg_smartbrowser/package/component/site/layouts/smartbrowser/resource.php';
            ob_start(); require $file; return ob_get_clean();
        }
    }
    final class LayoutHelper {
        public static function render($id, array $data) {
            if ($id !== 'joomla.html.image') throw new \RuntimeException('Native image layout not used');
            $data['src'] = explode('#joomlaImage://', $data['src'])[0];
            $html = '<img';
            foreach ($data as $key => $value) $html .= ' ' . $key . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
            return $html . '>';
        }
    }
}
