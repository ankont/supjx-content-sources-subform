<?php
namespace SuperSoft\Plugin\Fields\Sources\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\Component\Fields\Administrator\Plugin\FieldsPlugin;
use Joomla\Event\SubscriberInterface;

final class Sources extends FieldsPlugin implements SubscriberInterface
{
    public function onCustomFieldsPrepareDom($field, \DOMElement $parent, Form $form)
    {
        if (!in_array($form->getName(), ['com_content.article', 'com_content.form'], true)) { return null; }
        $node = parent::onCustomFieldsPrepareDom($field, $parent, $form);
        if ($node) {
            $node->setAttribute('addfieldprefix', 'SuperSoft\\Plugin\\Fields\\Sources\\Field');
            $node->setAttribute('addruleprefix', 'SuperSoft\\Plugin\\Fields\\Sources\\Rule');
            $node->setAttribute('filter', 'raw');
            $node->setAttribute('validate', 'Sources');
            $node->setAttribute('message', 'PLG_FIELDS_SOURCES_INVALID');
        }
        return $node;
    }

    public function onContentPrepareForm(Form $form, $data)
    {
        parent::onContentPrepareForm($form, $data);
        $type = $form->getValue('type', null, is_object($data) ? ($data->type ?? '') : ($data['type'] ?? ''));
        if (str_starts_with($form->getName(), 'com_fields.field') && $type === 'sources') {
            $form->setFieldAttribute('display', 'default', '3', 'params');
            $form->setFieldAttribute('showlabel', 'default', '0', 'params');
        }
    }
}
