<?php
namespace Joomla\CMS {
    class Factory {
        public static $app;
        public static function getApplication() { return self::$app; }
        public static function getContainer() { static $container; if (!$container) {
            $container = new \Joomla\DI\Container();
            $container->set(\Joomla\Database\DatabaseInterface::class, (new \ReflectionClass(\Joomla\Database\Mysqli\MysqliDriver::class))->newInstanceWithoutConstructor());
        } return $container; }
        public static function getLanguage() { return new class {
            public function hasKey($key) { return false; }
            public function isRtl() { return false; }
            public function _($text, ...$args) { return $text; }
        }; }
    }
}
namespace Joomla\CMS\Plugin {
    class CMSPlugin {
        protected $_type = 'fields';
        protected $_name = 'sources';
        protected $params;
        public function __construct() { $this->params = new \Joomla\Registry\Registry(); }
        public function getApplication() { return \Joomla\CMS\Factory::getApplication(); }
    }
    class PluginHelper {
        public static function isEnabled(...$args) { return true; }
        public static function getPlugin(...$args) { return (object) ['params' => '{}']; }
    }
}
namespace Joomla\Component\Fields\Administrator\Helper {
    class FieldsHelper {
        public static bool $editable = true;
        public static function displayFieldOnForm($field) { return true; }
        public static function canEditFieldValue($field) { return self::$editable; }
    }
}
namespace Joomla\CMS\Log {
    class Log { public const WARNING = 1; public static function add(...$args) {} }
}
namespace {
    $site = $argv[1] ?? dirname(__DIR__, 3) . '/JoomlaTest/site';
    if (!is_file($site . '/libraries/src/Form/Form.php')) { fwrite(STDERR, 'Local Joomla libraries required.'); exit(3); }
    define('_JEXEC', 1); define('JDEBUG', false);
    define('JPATH_ROOT', $site); define('JPATH_SITE', $site); define('JPATH_BASE', $site);
    define('JPATH_PLUGINS', dirname(__DIR__) . '/package/plugins');
    require $site . '/libraries/vendor/autoload.php';
    spl_autoload_register(static function ($class) use ($site) {
        $prefixes = [
            'Joomla\\CMS\\' => $site . '/libraries/src/',
            'Joomla\\Component\\Fields\\Administrator\\' => $site . '/administrator/components/com_fields/src/',
            'SuperSoft\\Plugin\\Fields\\Sources\\' => JPATH_PLUGINS . '/fields/sources/src/',
            'SuperSoft\\Plugin\\Content\\Sources\\' => JPATH_PLUGINS . '/content/sources/src/',
        ];
        foreach ($prefixes as $prefix => $path) {
            if (str_starts_with($class, $prefix)) {
                $file = $path . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($file)) require_once $file;
            }
        }
    });
    function check($condition, $message) { if (!$condition) throw new \RuntimeException($message); }
    \Joomla\CMS\Factory::$app = new class {
        public function bootPlugin(...$args) { return new class { public function loadAssets() {} }; }
        public function getLanguage() { return \Joomla\CMS\Factory::getLanguage(); }
        public function getIdentity() { return new \Joomla\CMS\User\User(); }
        public function getDocument() { return new class {
            public function getWebAssetManager() { return new class { public function registerAndUseScript(...$args) {} }; }
        }; }
    };
    $plugin = new \SuperSoft\Plugin\Fields\Sources\Extension\Sources();
    check($plugin->onCustomFieldsGetTypes()[0]['type'] === 'sources', 'Field type is discoverable through the Joomla FieldsPlugin');
    $form = new \Joomla\CMS\Form\Form('com_content.article', ['control' => 'jform']);
    $form->load('<form><fields name="com_fields"><fieldset name="sources"/></fields></form>');
    $dom = new \DOMDocument(); $parent = $dom->appendChild($dom->createElement('fieldset'));
    $definition = (object) ['type' => 'sources', 'name' => 'related_sources', 'label' => 'Sources', 'description' => '', 'required' => false,
        'default_value' => '', 'params' => new \Joomla\Registry\Registry(), 'fieldparams' => new \Joomla\Registry\Registry()];
    $node = $plugin->onCustomFieldsPrepareDom($definition, $parent, $form);
    check($node !== null && $node->getAttribute('filter') === 'raw', 'Configuration JSON uses a RAW filter');
    check($node->getAttribute('validate') === 'Sources', 'Schema validation is attached to the field');
    $xml = '<form><fields name="com_fields"><fieldset name="sources">' . $dom->saveXML($node) . '</fieldset></fields></form>';
    $form->load($xml);
    $raw = '{"version":2,"display":"separate","blocks":[{"type":"articles","title":"<title> & Ελληνικά","articles":[11,4]}]}';
    $form->bind(['com_fields' => ['related_sources' => $raw]]);
    $field = $form->getField('related_sources', 'com_fields');
    check($field instanceof \SuperSoft\Plugin\Fields\Sources\Field\SourcesField, 'The namespace prefix loads the actual Sources FormField');
    $html = $field->input;
    check(str_contains($html, 'name="jform[com_fields][related_sources]"'), 'Native Joomla field name is preserved');
    check(str_contains($html, '&lt;title&gt;') && !str_contains($html, '<title>'), 'Stored JSON is escaped safely in the fallback textarea');
    $filtered = $form->filter(['com_fields' => ['related_sources' => $raw]]);
    check($filtered['com_fields']['related_sources'] === $raw, 'Filtering does not strip JSON string content');
    $rule = \Joomla\CMS\Form\FormHelper::loadRuleType('Sources');
    check($rule instanceof \SuperSoft\Plugin\Fields\Sources\Rule\SourcesRule, 'The actual Joomla rule loader resolves Sources validation');
    $element = new \SimpleXMLElement('<field required="false"/>');
    check($rule->test($element, $raw), 'Valid JSON is accepted');
    check(!$rule->test($element, '{"version":999}'), 'Unsupported schema is rejected');
    check(!$rule->test($element, ['not a string']), 'Non-string values are rejected');
    check(!$rule->test($element, str_repeat('x', 131073)), 'Oversized JSON is rejected');
    check($rule->test($element, ''), 'Optional field can be emptied');
    $element['required'] = 'true'; check(!$rule->test($element, ''), 'Required field cannot be emptied');
    \Joomla\Component\Fields\Administrator\Helper\FieldsHelper::$editable = false;
    $locked = $plugin->onCustomFieldsPrepareDom($definition, $parent, $form);
    check($locked->getAttribute('disabled') === 'true', 'Native field edit permissions remain authoritative');
    $other = new \Joomla\CMS\Form\Form('com_users.user');
    check($plugin->onCustomFieldsPrepareDom($definition, $parent, $other) === null, 'Sources is limited to article editing');
    $settings = new \Joomla\CMS\Form\Form('com_fields.field.com_content.article');
    $settings->load('<form><field name="type" type="hidden"/><fields name="params"><field name="display" type="hidden" default="2"/><field name="showlabel" type="hidden" default="1"/></fields></form>');
    $plugin->onContentPrepareForm($settings, (object) ['type' => 'sources']);
    check((string) $settings->getFieldAttribute('display', 'default', '', 'params') === '3', 'New Sources fields default to After Display');
    echo 'Joomla Sources field assertions passed';
}
