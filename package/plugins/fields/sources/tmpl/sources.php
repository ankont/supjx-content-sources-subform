<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;
use SuperSoft\Plugin\Content\Sources\Support\InlineConfig;
use SuperSoft\Plugin\Content\Sources\Support\InlineRenderer;

// Joomla's Fields pipeline owns positioning, category assignment and field access.
$app = Factory::getApplication();
if ($context !== 'com_content.article' || !$app->isClient('site') || $app->getDocument()->getType() !== 'html'
    || $app->getInput()->getCmd('layout') === 'edit' || $app->getInput()->getCmd('view') !== 'article'
    || !is_string($field->value) || trim($field->value) === '' || !PluginHelper::isEnabled('content', 'sources')) { return; }
static $depth = 0;
if ($depth >= 3) { return; }
$depth++;
try {
    $app->bootPlugin('sources', 'content');
    $config = InlineConfig::decode($field->value);
    $params = new Registry(PluginHelper::getPlugin('content', 'sources')->params ?? '');
    $app->getDocument()->getWebAssetManager()->registerAndUseStyle('plg.sources.frontend', 'plg_editors-xtd_sourcesbutton/sourcesbutton.css', ['version' => 'auto']);
    echo (new InlineRenderer($params))->render($config, $item);
} catch (\Throwable $error) {
    \Joomla\CMS\Log\Log::add('Sources field render: ' . $error->getMessage(), \Joomla\CMS\Log\Log::WARNING, 'sources');
} finally { $depth--; }
