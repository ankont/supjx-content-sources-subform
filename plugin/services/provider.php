<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.sources
 */

defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use SuperSoft\Plugin\Content\Sources\Extension\Sources;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $config  = (array) PluginHelper::getPlugin('content', 'sources');
                $subject = $container->get(DispatcherInterface::class);
                $app     = Factory::getApplication();

                $plugin = new Sources($subject, $config);
                $plugin->setApplication($app);

                return $plugin;
            }
        );
    }
};
