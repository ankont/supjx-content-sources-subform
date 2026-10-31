<?php
defined('_JEXEC') or die;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use SuperSoft\Plugin\Newsletter\SourcesEmail\Extension\SourcesEmail;
return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, static function (Container $container) {
            $plugin = new SourcesEmail($container->get(DispatcherInterface::class), (array) PluginHelper::getPlugin('newsletter', 'sourcesemail'));
            $plugin->setApplication(Factory::getApplication());
            return $plugin;
        });
    }
};
