<?php
namespace Joomla\CMS\Component { class ComponentHelper { public static function isEnabled($name) { return true; } } }
namespace Joomla\Database { interface DatabaseInterface {} }
namespace Joomla\CMS {
    class Factory {
        public static $db;
        public static function getApplication() { return new class {
            public function bootComponent($name) {}
            public function getDocument() { return new class { public function getType() { return 'html'; } }; }
            public function getLanguage() { return new class { public function load(...$args) {} }; }
        }; }
        public static function getContainer() { return new class { public function get($id) { return Factory::$db; } }; }
    }
}
namespace Joomla\CMS\Language { class Text { public static array $keys=[]; public static function script($key) { self::$keys[]=$key; } } }
namespace {
    define('_JEXEC', 1); define('JPATH_PLUGINS', dirname(__DIR__) . '/package/plugins');
    define('JPATH_ADMINISTRATOR', dirname(__DIR__));
    $site = dirname(__DIR__, 3) . '/JoomlaTest/site';
    $loader = require $site . '/libraries/vendor/autoload.php';
    $loader->addPsr4('Joomla\\CMS\\', $site . '/libraries/src');
    require dirname(__DIR__, 2) . '/pkg_smartbrowser/package/component/admin/src/Support/ConsumerUsage.php';
    require JPATH_PLUGINS . '/system/sourcessmartbrowserusage/sourcessmartbrowserusage.php';
    $plugin = new \PlgSystemSourcesSmartBrowserUsage();
    if ((new \ReflectionMethod(\Joomla\CMS\Plugin\CMSPlugin::class, 'getApplication'))->invoke($plugin) !== null) { throw new \RuntimeException('Test must reproduce a non-injected Joomla plugin'); }
    $plugin->onAfterInitialise();
    $plugin->onBeforeRender();
    if (!in_array('PLG_CONTENT_SOURCES_USAGE_INTROLAYOUT',\Joomla\CMS\Language\Text::$keys,true)) { throw new \RuntimeException('Picker page translations were not exported'); }
    if (count(\SuperSoft\Component\Smartbrowser\Administrator\Support\ConsumerUsage::definitions('sources')) !== 5) { throw new \RuntimeException('System bootstrap did not register API capabilities'); }
    \Joomla\CMS\Factory::$db = new class {
        public int $writes=0; public string $sql='';
        public function getQuery($new) { return new class { public array $parts=[]; public function __call($name,$args) { $this->parts[]=$name . ':' . implode(' ', $args); return $this; } public function __toString() { return implode(' ',$this->parts); } }; }
        public function quote($value) { return "'" . $value . "'"; }
        public function setQuery($query) { $this->sql=(string)$query; return $this; }
        public function execute() { $this->writes++; }
    };
    require JPATH_PLUGINS . '/system/sourcessmartbrowserusage/script.php';
    $installer=new \PlgSystemSourcesSmartBrowserUsageInstallerScript();$installer->postflight('update',null);
    if (\Joomla\CMS\Factory::$db->writes !== 0) { throw new \RuntimeException('Upgrade must preserve disabled state'); }
    $installer->postflight('install',null);$db=\Joomla\CMS\Factory::$db;
    foreach (["folder = 'system'", "element = 'sourcessmartbrowserusage'", 'enabled = 1'] as $guard) { if (!str_contains($db->sql,$guard)) throw new \RuntimeException('Installer updated wrong records'); }
    echo "System registry bootstrap and scoped first-install activation passed\n";
}
