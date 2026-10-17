<?php
namespace SuperSoft\Plugin\EditorsXtd\SourcesButton\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Editor\Button\Button;
use Joomla\CMS\Event\Editor\EditorButtonsSetupEvent;
use Joomla\CMS\Event\Plugin\AjaxEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use SuperSoft\Plugin\Content\Sources\Support\InlineConfig;
use SuperSoft\Plugin\Content\Sources\Support\InlineRenderer;
use SuperSoft\Component\Smartbrowser\Administrator\Support\CollectionViewSupport;

final class SourcesButton extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return ['onEditorButtonsSetup' => 'onEditorButtonsSetup', 'onAjaxSourcesbutton' => 'onAjaxSourcesbutton'];
    }

    public function onEditorButtonsSetup(EditorButtonsSetupEvent $event): void
    {
        $app = Factory::getApplication();
        if (!$app->isClient('site') && !$app->isClient('administrator')) { return; }
        $this->loadAssets();
        $event->getButtonsRegistry()->add(new Button('sourcesbutton', [
            'text' => Text::_('PLG_EDITORSXTD_SOURCESBUTTON_LABEL'), 'icon' => 'list', 'action' => 'sourcesbutton:insert',
        ]));
    }

    public function loadAssets(): void
    {
        $this->loadLanguage();
        static $loaded = false;
        if ($loaded) { return; }
        $app = Factory::getApplication();
        $document = $app->getDocument();
        $assets = $document->getWebAssetManager();
        $stylesheet = (defined('JPATH_PUBLIC') ? JPATH_PUBLIC : JPATH_ROOT) . '/media/plg_editors-xtd_sourcesbutton/css/sourcesbutton.css';
        $styleVersion = (is_readable($stylesheet) ? hash_file('sha256', $stylesheet) : null) ?: 'auto';
        $assets->useStyle('fontawesome');
        $assets->usePreset('choicesjs')->useScript('webcomponent.field-fancy-select');
        $dependencies = ['editors', 'webcomponent.field-fancy-select'];
        if (ComponentHelper::isEnabled('com_smartbrowser')) {
            try {
                $app->bootComponent('com_smartbrowser');
                CollectionViewSupport::prepare($document);
                $assets->useScript('com_smartbrowser.picker');
                $document->addScriptOptions('com_smartbrowser.picker', ['url' => Uri::base() . 'index.php?option=com_smartbrowser&view=browser']);
                $dependencies[] = 'com_smartbrowser.collection';
                $dependencies[] = 'com_smartbrowser.picker';
            } catch (\Throwable $error) {
                \Joomla\CMS\Log\Log::add('Sources SmartBrowser: ' . $error->getMessage(), \Joomla\CMS\Log\Log::WARNING, 'sources');
            }
        }
        $keys = ['builder', 'insert', 'update', 'cancel', 'addBlock', 'addSources', 'title', 'sources', 'presentation', 'filters',
            'tags', 'categories', 'limit', 'layout', 'separate', 'merged', 'display', 'duplicate', 'delete', 'up', 'down', 'actions',
            'untitled', 'edit', 'settings', 'none', 'placeholder', 'list_manual', 'list_full', 'render', 'unavailable', 'invalid', 'conflict',
            'cards', 'details', 'list', 'icons', 'contents', 'advancedTags', 'advancedCategories', 'maximize', 'restore',
            'articles', 'selected', 'name', 'caption', 'type_articles', 'type_categories', 'type_tags', 'add_articles', 'add_categories', 'add_tags', 'order', 'mergeOrdering', 'blockOrderingHint', 'blocks', 'global',
            'published-desc', 'published-asc', 'created-desc', 'created-asc', 'title-asc', 'title-desc', 'ordering-asc',
            'previewPermission', 'previewResponse', 'previewEmpty', 'previewNetwork', 'previewFailed', 'previewInvalid', 'previewDisabled'];
        $i18n = [];
        foreach ($keys as $key) { $i18n[$key] = Text::_('PLG_EDITORSXTD_SOURCESBUTTON_INLINE_' . strtoupper(str_replace('-', '_', $key))); }
        $document->addScriptOptions('plg_editorsxtd_sourcesbutton.inline', [
            'previewMode' => $this->params->get('editor_preview_mode', 'placeholder'),
            'badgeLabel' => $this->params->get('badge_label', '') ?: 'Sources',
            'ajaxUrl' => Uri::base() . 'index.php?option=com_ajax&plugin=sourcesbutton&group=editors-xtd&format=json',
            'csrfToken' => Session::getFormToken(),
            'contentCss' => Uri::root(true) . '/media/plg_editors-xtd_sourcesbutton/css/sourcesbutton.css?v=' . $styleVersion,
            'fontAwesomeUrl' => Uri::root(true) . '/media/system/css/joomla-fontawesome.min.css', 'i18n' => $i18n,
            'filterFields' => $this->filterFields(),
            'taxonomyEditor' => $this->params->get('taxonomy_editor', 'collection'),
            'editorSizeUrl' => ComponentHelper::isEnabled('com_smartbrowser') ? Uri::root(true) . '/media/com_smartbrowser/js/picker-size.js' : '',
        ]);
        $assets->registerAndUseStyle('plg.sources.inline', 'plg_editors-xtd_sourcesbutton/sourcesbutton.css', ['version' => $styleVersion]);
        $assets->registerAndUseScript('plg.sources.inline', 'plg_editors-xtd_sourcesbutton/sourcesbutton.js', ['version' => 'auto'], ['type' => 'module'], $dependencies);
        $loaded = true;
    }

    private function filterFields(): array
    {
        try {
            $form = Form::getInstance('plg.sources.filters', '<form>
                <field name="tags" type="tag" multiple="true" custom="deny" mode="nested" remote-search="false" published="1"/>
                <field name="categories" type="category" extension="com_content" multiple="true" published="1" layout="joomla.form.field.list-fancy-select"/>
            </form>');
            return ['tags' => $form->getInput('tags', null, []), 'categories' => $form->getInput('categories', null, [])];
        } catch (\Throwable $error) {
            \Joomla\CMS\Log\Log::add('Sources filter fields: ' . $error->getMessage(), \Joomla\CMS\Log\Log::WARNING, 'sources');
            return [];
        }
    }

    public function onAjaxSourcesbutton(AjaxEvent $event): void
    {
        $event->addResult($this->previewResponse());
    }

    private function previewResponse(): array
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!Session::checkToken('request') || $user->guest || (!$app->isClient('site') && !$app->isClient('administrator'))) {
            throw new \RuntimeException('Sources preview not authorised.', 403);
        }
        $allowed = false;
        foreach (['core.create', 'core.edit', 'core.edit.own'] as $action) {
            $allowed = $allowed || $user->authorise($action, 'com_content') || count($user->getAuthorisedCategories('com_content', $action)) > 0;
        }
        if (!$allowed) { throw new \RuntimeException('Sources preview not authorised.', 403); }
        $stage = 'bootstrap';
        try {
            if (!PluginHelper::isEnabled('content', 'sources')) {
                return ['ok' => false, 'html' => '', 'code' => 'plugin_disabled'];
            }
            $app->bootPlugin('sources', 'content');
            $stage = 'validation';
            $json = $app->getInput()->post->get('configuration', '', 'RAW');
            $config = InlineConfig::decode((string) $json);
            $mode = $app->getInput()->post->getCmd('mode', 'render');
            if (!in_array($mode, ['list_manual', 'list_full', 'render'], true)) { throw new \InvalidArgumentException('Invalid preview mode.'); }
            $plugin = PluginHelper::getPlugin('content', 'sources');
            $stage = 'render';
            $renderer = new InlineRenderer(new Registry($plugin->params ?? ''));
            $html = $renderer->render($config, (object) ['id' => 0], $mode, 'preview');
            $styles = [];
            if (method_exists($app, 'getDocument')) {
                foreach ($app->getDocument()->getWebAssetManager()->getAssets('style', true) as $asset) {
                    $uri = $asset->getUri();
                    if ($uri !== '') { $styles[] = $uri; }
                }
            }
            return ['ok' => true, 'html' => $html, 'styles' => array_values(array_unique($styles))];
        } catch (\Throwable $error) {
            \Joomla\CMS\Log\Log::add('Sources preview [' . $stage . ']: ' . $error->getMessage(), \Joomla\CMS\Log\Log::WARNING, 'sources');
            return ['ok' => false, 'html' => '', 'code' => $stage === 'validation' ? 'invalid_configuration' : 'render_failed'];
        }
    }
}
