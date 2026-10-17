<?php
namespace SuperSoft\Plugin\Fields\Sources\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;

final class SourcesField extends FormField
{
    protected $type = 'Sources';

    protected function getInput()
    {
        $escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $raw = is_string($this->value) ? $this->value : '';
        $locked = $this->disabled || $this->readonly;
        try {
            if (!$locked) {
                if (!PluginHelper::isEnabled('editors-xtd', 'sourcesbutton') || !PluginHelper::isEnabled('content', 'sources')) {
                    throw new \RuntimeException('Sources plugins are disabled.');
                }
                Factory::getApplication()->bootPlugin('sourcesbutton', 'editors-xtd')->loadAssets();
                Factory::getApplication()->getDocument()->getWebAssetManager()->registerAndUseScript(
                    'plg.sources.field', 'plg_editors-xtd_sourcesbutton/field.js', ['version' => 'auto'], ['type' => 'module'], ['plg.sources.inline']
                );
            }
        } catch (\Throwable $error) {
            \Joomla\CMS\Log\Log::add('Sources field: ' . $error->getMessage(), \Joomla\CMS\Log\Log::WARNING, 'sources');
        }
        $labels = [
            'preview' => Text::_('PLG_FIELDS_SOURCES_PREVIEW'), 'close' => Text::_('JCLOSE'),
            'invalid' => Text::_('PLG_FIELDS_SOURCES_INVALID'), 'empty' => Text::_('PLG_FIELDS_SOURCES_EMPTY'),
            'pending' => Text::_('PLG_FIELDS_SOURCES_PENDING'),
            'raw' => Text::_('PLG_FIELDS_SOURCES_RAW'),
        ];
        return '<div class="sources-custom-field" data-sources-field data-labels="' . $escape(json_encode($labels)) . '">'
            . '<textarea id="' . $escape($this->id) . '" name="' . $escape($this->name) . '" rows="8" class="form-control sources-field-value"'
            . ($this->required ? ' required' : '') . ($this->disabled ? ' disabled' : '') . ($this->readonly ? ' readonly' : '')
            . ' data-locked="' . ($locked ? '1' : '0') . '">' . $escape($raw) . '</textarea></div>';
    }
}
