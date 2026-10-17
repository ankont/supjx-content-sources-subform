<?php
namespace SuperSoft\Plugin\Fields\Sources\Rule;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormRule;
use Joomla\Registry\Registry;
use SuperSoft\Plugin\Content\Sources\Support\InlineConfig;

final class SourcesRule extends FormRule
{
    public function test(\SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null)
    {
        if ($value === '' || $value === null) { return !in_array(strtolower((string) $element['required']), ['true', '1', 'required'], true); }
        if (!is_string($value)) { return false; }
        try {
            Factory::getApplication()->bootPlugin('sources', 'content');
            InlineConfig::decode($value);
            return true;
        } catch (\Throwable $error) { return false; }
    }
}
