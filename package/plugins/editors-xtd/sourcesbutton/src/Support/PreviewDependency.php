<?php
namespace SuperSoft\Plugin\EditorsXtd\SourcesButton\Support;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;

/** Inspect availability without changing the current principal or plugin cache. */
final class PreviewDependency
{
    public static function check(string $group, string $element, bool $publicOnly): ?string
    {
        $prefix = $group === 'content' ? 'content_plugin_' : 'email_plugin_';
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)->select('enabled, state, access')->from('#__extensions')
            ->where('type = ' . $db->quote('plugin'))->where('folder = ' . $db->quote($group))
            ->where('element = ' . $db->quote($element));
        $row = $db->setQuery($query, 0, 1)->loadObject();
        if (!$row) { return $prefix . 'missing'; }
        if ((int) $row->enabled !== 1) { return $prefix . 'disabled'; }
        if (!in_array((int) $row->state, [0, 1], true)) { return $prefix . 'unavailable'; }
        $identity = Factory::getApplication()->getIdentity();
        if (!in_array((int) $row->access, array_map('intval', $identity->getAuthorisedViewLevels()), true)) { return $prefix . 'restricted'; }
        if ($publicOnly && !in_array((int) $row->access, array_map('intval', (new User(0))->getAuthorisedViewLevels()), true)) { return $prefix . 'public_required'; }
        if (!PluginHelper::isEnabled($group, $element)) { return $prefix . 'unavailable'; }
        $base = JPATH_PLUGINS . '/' . $group . '/' . $element;
        $required = $group === 'content' ? ['services/provider.php', 'src/Support/InlineRenderer.php']
            : ['services/provider.php', 'layouts/sources/email/item.php', 'layouts/sources/email/block.php'];
        foreach ($required as $file) { if (!is_file($base . '/' . $file)) { return $prefix . 'incomplete'; } }
        return null;
    }
}
