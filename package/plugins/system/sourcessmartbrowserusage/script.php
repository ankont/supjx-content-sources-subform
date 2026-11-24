<?php
defined('_JEXEC') or die;
final class PlgSystemSourcesSmartBrowserUsageInstallerScript
{
    public function postflight($type, $parent): void
    {
        if ($type !== 'install') { return; }
        $db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $db->setQuery($db->getQuery(true)->update('#__extensions')->set('enabled = 1')
            ->where('type = ' . $db->quote('plugin'))->where('folder = ' . $db->quote('system'))
            ->where('element = ' . $db->quote('sourcessmartbrowserusage')))->execute();
    }
}
