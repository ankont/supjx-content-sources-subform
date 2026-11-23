<?php
namespace SuperSoft\Plugin\Content\Sources\Support;

use SuperSoft\Component\Smartbrowser\Administrator\Support\ConsumerUsage;
use SuperSoft\Component\Smartbrowser\Administrator\Support\SelectionFieldSupport;

final class IntroUsage
{
    public const LAYOUTS = ['default', 'simple', 'heading', 'card', 'collapsible'];
    public const ELEMENTS = ['both', 'title', 'description', 'none'];
    public static function definitions(): array
    {
        $icons = ['introLayout'=>'fas fa-th-large', 'introElements'=>'fas fa-list-alt',
            'introTitle'=>'fas fa-heading', 'introDescription'=>'fas fa-align-left', 'introClass'=>'fas fa-code'];
        $result = [];
        foreach (['introLayout'=>self::LAYOUTS, 'introElements'=>self::ELEMENTS, 'introTitle'=>null, 'introDescription'=>null, 'introClass'=>null] as $name=>$choices) {
            $definition = ['key'=>'ext.sources.' . $name, 'resourceTypes'=>['category','tag'], 'type'=>'string',
                'editor'=>$choices ? 'select' : ($name === 'introDescription' ? 'textarea' : 'text'),
                'label'=>'PLG_CONTENT_SOURCES_USAGE_' . strtoupper($name),
                'icon'=>$icons[$name],
                'default'=>$name === 'introLayout' ? 'default' : ($name === 'introElements' ? 'both' : ''),
                'presentation'=>$choices ? 'primary' : 'secondary',
                'validation'=>['maxLength'=>$name === 'introDescription' ? 10000 : ($name === 'introClass' ? 255 : 2000)]];
            if ($choices) {
                $definition['options'] = array_map(static fn($value)=>['value'=>$value, 'label'=>'PLG_CONTENT_SOURCES_USAGE_' . strtoupper($value)], $choices);
            }
            if ($name === 'introClass') { $definition['validation']['pattern'] = '^[a-zA-Z_][a-zA-Z0-9_-]*(?: [a-zA-Z_][a-zA-Z0-9_-]*)*$|^$'; }
            $result[] = $definition;
        }
        return $result;
    }
    public static function register(): void
    {
        if (class_exists(ConsumerUsage::class)) { ConsumerUsage::register('sources', self::definitions()); }
    }
    public static function profile(): array
    {
        $profile = [];
        foreach (self::definitions() as $definition) { $profile[$definition['key']] = isset($definition['options']) ? ['required'=>true] : true; }
        return $profile;
    }
    public static function value(string $key, mixed $value, string $adapter): void
    {
        if (!str_starts_with($key, 'ext.sources.')) { return; }
        $definitions = array_column(self::definitions(), null, 'key');
        if (!isset($definitions[$key]) || !in_array($adapter, ['categories','tags'], true)) { throw new \InvalidArgumentException('Invalid Sources introduction usage.'); }
        if ($value === null) { return; }
        $definition = $definitions[$key];
        if (!is_string($value) || preg_match_all('/./us', $value) > $definition['validation']['maxLength']
            || (isset($definition['options']) && !in_array($value, array_column($definition['options'], 'value'), true))
            || ($key === 'ext.sources.introClass' && !preg_match('/^(?:[a-zA-Z_][a-zA-Z0-9_-]*(?: [a-zA-Z_][a-zA-Z0-9_-]*)*)?$/D', $value))) {
            throw new \InvalidArgumentException('Invalid Sources introduction usage value.');
        }
        if (class_exists(ConsumerUsage::class) && class_exists(SelectionFieldSupport::class)) {
            self::register();
            SelectionFieldSupport::usageValues(['type'=>$adapter === 'tags' ? 'tag' : 'category'], [$key=>$value],
                ['consumer'=>'sources','selectionProfile'=>self::profile()]);
        }
    }
    public static function presentation(array $usage): array
    {
        return ['layout'=>$usage['ext.sources.introLayout'] ?? 'default', 'elements'=>$usage['ext.sources.introElements'] ?? 'both',
            'class'=>$usage['ext.sources.introClass'] ?? ''];
    }
    public static function entity(object $entity, array $usage): object
    {
        $entity = clone $entity;
        $title = $usage['ext.sources.introTitle'] ?? '';
        if (is_string($title) && trim($title) !== '') { $entity->title = $title; }
        $description = $usage['ext.sources.introDescription'] ?? '';
        if (is_string($description) && trim($description) !== '') {
            $entity->description = '<p>' . str_replace(["\r\n", "\r", "\n"], '<br>', htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>';
            $entity->sources_description_plain = true;
        }
        return $entity;
    }
}
