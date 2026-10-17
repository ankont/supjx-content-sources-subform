<?php
namespace Joomla\CMS {
    class Factory {
        public static function getApplication() { return new class { public function getTemplate() { return 'missing'; } }; }
    }
}
namespace Joomla\CMS\Language {
    class Text { public static function _($key) { return 'Sources'; } }
}
namespace Joomla\Component\Fields\Administrator\Helper {
    class FieldsHelper {
        public static array $fields = [];
        public static function getFields(...$args) { return self::$fields; }
    }
}
namespace {
    define('JPATH_SITE', __DIR__);
    require dirname(__DIR__) . '/package/plugins/content/sources/src/Support/LegacySources.php';
    use SuperSoft\Plugin\Content\Sources\Support\LegacySources;
    use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
    function check($ok, $message) { if (!$ok) { throw new \RuntimeException($message); } }
    $file = tempnam(sys_get_temp_dir(), 'sources-legacy-');
    file_put_contents($file, '<?php echo "[" . implode(",", $sources_block_indices) . ":" . $sources_render_mode . "]";');
    $settings = ['templates_base_path' => dirname($file), 'templates_entry_file' => basename($file)];
    $params = new class($settings) {
        public array $values;
        public function __construct($values) { $this->values = $values; }
        public function get($key, $default = null) { return $this->values[$key] ?? $default; }
    };
    $old = (object) ['name' => 'blocks', 'type' => 'subform', 'subform_rows' => [['title' => 'One'], ['title' => 'Two']]];
    FieldsHelper::$fields = [$old];
    $before = serialize($old);
    $run = static function ($text, $context = 'com_content.article') use ($params) {
        $article = (object) ['id' => 12, 'text' => $text];
        LegacySources::prepare($context, $article, $params);
        return $article->text;
    };
    try {
        check($run('Body') === 'Body[0,1:all]', 'Legacy auto append');
        check($run('{sources:1}{sources:rest}') === '[0:single][1:rest]', 'Numbered and remaining blocks');
        $old->subform_rows = [['title' => 'One']];
        check($run('{sources:1}{sources:rest}') === '[0:single]', 'Empty rest must not repeat a block');
        check($run('{sources:99}') === '', 'Missing block is empty');
        $old->subform_rows[] = ['title' => 'Two'];
        check(serialize($old) === $before, 'Stored values untouched');
        check($run('{sources}', 'com_content.category') === '', 'Blog hides old tokens');
        $params->values['list_token_mode'] = 'placeholder';
        check(str_contains($run('{sources}', 'com_content.featured'), '[Sources]'), 'Blog placeholder');
        $params->values['legacy_enabled'] = 0;
        check($run('{sources}') === '{sources}', 'Disabled compatibility');
        $params->values['legacy_enabled'] = 1;
        check($run('{sources:{"version":99}}') === '{sources:{"version":99}}', 'Future inline data preserved');
        FieldsHelper::$fields[] = (object) ['type' => 'sources', 'rawvalue' => ['{"version":2}']];
        check($run('Body') === 'Body', 'Modern field prevents duplicate output');
        array_pop(FieldsHelper::$fields);
        $params->values['templates_entry_file'] = 'absent.php';
        check($run('Body') === 'Body', 'Missing template preserves content');
        $params->values['templates_entry_file'] = basename($file);
        $params->values['append_if_missing'] = 0;
        check($run('Body') === 'Body', 'Append can be disabled');
    } finally { unlink($file); }
    echo "Legacy compatibility checks passed\n";
}
