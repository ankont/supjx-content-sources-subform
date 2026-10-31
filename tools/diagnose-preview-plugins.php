<?php
// Local-only metadata diagnostic. No extension settings or authenticated sessions are changed.
if (PHP_SAPI !== 'cli' || !is_file('/opt/local/localize.php')) { exit(1); }
define('_JEXEC', 1); define('JPATH_BASE', '/var/www/html');
$_SERVER['HTTP_HOST']='localhost:8089'; $_SERVER['REQUEST_URI']='/'; $_SERVER['SCRIPT_NAME']='/index.php';
require JPATH_BASE . '/includes/defines.php'; require JPATH_BASE . '/includes/framework.php';
$container=\Joomla\CMS\Factory::getContainer();
$container->alias('session','session.web.site')->alias(\Joomla\Session\SessionInterface::class,'session.web.site');
$app=$container->get(\Joomla\CMS\Application\SiteApplication::class);
\Joomla\CMS\Factory::$application=$app; $app->loadIdentity(new \Joomla\CMS\User\User(0));
(new ReflectionMethod($app,'initialiseApp'))->invoke($app);
$db=$container->get(\Joomla\Database\DatabaseInterface::class);
$rows=$db->setQuery("SELECT folder,element,enabled,state,access FROM #__extensions WHERE type='plugin' AND ((folder='content' AND element='sources') OR (folder='newsletter' AND element='sourcesemail'))")->loadObjectList();
$levels=(new \Joomla\CMS\User\User(0))->getAuthorisedViewLevels();
foreach($rows as $row) {
    $row->public_access=in_array((int)$row->access,array_map('intval',$levels),true);
    $row->helper_available=\Joomla\CMS\Plugin\PluginHelper::isEnabled($row->folder,$row->element);
}
echo json_encode($rows,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
