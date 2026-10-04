<?php
namespace SuperSoft\Plugin\Content\Sources\Support;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use SuperSoft\Component\Smartbrowser\Administrator\Support\CollectionViewSupport;

final class SourcesBuilder
{
    public static function prepare(string $fieldName): void
    {
        static $loaded = false;
        $app = Factory::getApplication();
        if ($loaded || (!$app->isClient('site') && !$app->isClient('administrator'))
            || !ComponentHelper::isEnabled('com_smartbrowser')) {
            return;
        }

        try {
            $app->bootComponent('com_smartbrowser');
            if (!class_exists(CollectionViewSupport::class)) {
                return;
            }
            $document = $app->getDocument();
            if (!method_exists($document, 'getWebAssetManager')) {
                return;
            }
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'name']))
                ->from($db->quoteName('#__fields'))
                ->where($db->quoteName('context') . ' = ' . $db->quote('com_content.article'));
            $fields = $db->setQuery($query)->loadAssocList();
            $names = [];
            foreach ($fields as $field) {
                $names['field' . $field['id']] = $field['name'];
            }

            CollectionViewSupport::prepare($document);
            $assets = $document->getWebAssetManager();
            $assets->useScript('com_smartbrowser.picker');
            $document->addScriptOptions('com_smartbrowser.picker', [
                'url' => Uri::base() . 'index.php?option=com_smartbrowser&view=browser',
            ]);
            $keys = ['classic', 'builder', 'addBlock', 'addSources', 'duplicate', 'delete',
                'up', 'down', 'actions', 'sources', 'presentation', 'advanced', 'untitled',
                'empty', 'help', 'sourceCount', 'tagCount', 'unavailable', 'deleteConfirm'];
            $i18n = [];
            foreach ($keys as $key) {
                $i18n[$key] = Text::_('PLG_CONTENT_SOURCES_BUILDER_' . strtoupper($key));
            }
            $document->addScriptOptions('plg_content_sources.builder', [
                'fieldName' => $fieldName,
                'fieldNames' => $names,
                'i18n' => $i18n,
            ]);
            $assets->registerAndUseStyle('plg_content_sources.builder', 'plg_content_sources/builder.css', ['version' => 'auto']);
            $assets->registerAndUseScript('plg_content_sources.builder', 'plg_content_sources/builder.js', ['version' => 'auto'], ['type' => 'module'], ['core', 'com_smartbrowser.collection', 'com_smartbrowser.picker']);
            $loaded = true;
        } catch (\Throwable $error) {
            // Leave the native form available when optional integration cannot be prepared.
            Log::add('Sources Builder: ' . $error->getMessage(), Log::WARNING, 'sources');
        }
    }
}
