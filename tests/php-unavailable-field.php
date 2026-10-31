<?php
namespace Joomla\CMS\Component {
    class ComponentHelper {
        public static int $global = 0;
        public static function getParams($name) { return new \Joomla\Registry\Registry(['selection_show_unavailable' => self::$global]); }
    }
}
namespace Joomla\CMS\Language {
    class Text {
        public static array $translations = [];
        public static function _($key) { return self::$translations[$key] ?? $key; }
        public static function alt($key, ...$args) { return self::_($key); }
        public static function sprintf($key, ...$args) { return sprintf(self::_($key), ...$args); }
    }
}
namespace {
    $site = dirname(__DIR__, 3) . '/JoomlaTest/site';
    if (!is_file($site . '/libraries/src/Form/Field/ListField.php')) { exit(3); }
    define('_JEXEC', 1); define('JDEBUG', false);
    require $site . '/libraries/vendor/autoload.php';
    spl_autoload_register(static function ($class) use ($site) {
        if (str_starts_with($class, 'Joomla\\CMS\\')) {
            $path = $site . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
            if (is_file($path)) require_once $path;
        }
    });
    $smart = dirname(__DIR__, 2) . '/pkg_smartbrowser/package/component/admin/src/';
    require $smart . 'Support/SelectionOutputRenderer.php';
    require $smart . 'Field/UnavailablevisibilityField.php';
    $manifest = simplexml_load_file(dirname(__DIR__) . '/package/plugins/content/sources/sources.xml');
    $element = $manifest->xpath('//field[@name="show_unavailable"]')[0];
    if ((string) $element['default'] !== '' || (string) $element['type'] !== 'Unavailablevisibility'
        || (string) $element['addfieldprefix'] !== 'SuperSoft\\Component\\Smartbrowser\\Administrator\\Field') throw new \RuntimeException('Incorrect native field registration');
    $reflection = new \ReflectionClass(\SuperSoft\Component\Smartbrowser\Administrator\Field\UnavailablevisibilityField::class);
    $field = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('element')->setValue($field, $element);
    $reflection->getProperty('fieldname')->setValue($field, 'show_unavailable');
    foreach ([['Use Global (%s)', 'Show', 'Hide'], ['Γενική ρύθμιση (%s)', 'Εμφάνιση', 'Απόκρυψη']] as [$globalText, $show, $hide]) {
        \Joomla\CMS\Language\Text::$translations = ['JGLOBAL_USE_GLOBAL_VALUE' => $globalText, 'JSHOW' => $show, 'JHIDE' => $hide];
        foreach ([0,1] as $global) {
            \Joomla\CMS\Component\ComponentHelper::$global = $global;
            $options = $reflection->getMethod('getOptions')->invoke($field);
            if (array_column($options, 'value') !== ['', '1', '0'] || $options[0]->text !== sprintf($globalText, $global ? $show : $hide)
                || $options[1]->text !== $show || $options[2]->text !== $hide) throw new \RuntimeException('Native translated global options failed');
        }
    }
    echo "Actual SmartBrowser unavailable field and Joomla ListField options passed\n";
}
