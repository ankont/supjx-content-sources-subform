<?php
defined('_JEXEC') or die;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use SuperSoft\Plugin\Content\Sources\Support\IntroUsage;
final class PlgSystemSourcesusage extends CMSPlugin
{
    public function onAfterInitialise(): void
    {
        if (!ComponentHelper::isEnabled('com_smartbrowser')) { return; }
        $path = JPATH_PLUGINS . '/content/sources/src/Support/IntroUsage.php';
        if (!is_file($path)) { return; }
        $app = $this->getApplication() ?? Factory::getApplication();
        $app->bootComponent('com_smartbrowser');
        require_once $path;
        IntroUsage::register();
    }
    public function onBeforeRender(): void
    {
        $app = $this->getApplication() ?? Factory::getApplication();
        if (!class_exists(IntroUsage::class) || $app->getDocument()->getType() !== 'html') { return; }
        $app->getLanguage()->load('plg_content_sources', JPATH_ADMINISTRATOR);
        foreach (IntroUsage::definitions() as $definition) {
            foreach ([$definition['label'], ...array_column($definition['options'] ?? [], 'label')] as $label) {
                \Joomla\CMS\Language\Text::script($label);
            }
        }
    }
}
