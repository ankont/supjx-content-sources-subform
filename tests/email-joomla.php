<?php
/** Local-only, transient provider/content-table fixtures. No transport or real subscriber data. */
define('_JEXEC', 1); define('JPATH_BASE', '/var/www/html');
$_SERVER['HTTP_HOST'] = 'localhost:8089'; $_SERVER['SERVER_NAME'] = 'localhost'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['SCRIPT_NAME'] = '/index.php'; $_SERVER['PHP_SELF'] = '/index.php';
require JPATH_BASE . '/includes/defines.php'; require JPATH_BASE . '/includes/framework.php';
$candidate = $argv[1] ?? '';
if (!str_starts_with($candidate, '/tmp/sources-email-') || !is_file($candidate . '/plugins/newsletter/sourcesemail/sourcesemail.xml') || !is_file('/opt/local/localize.php')) { throw new RuntimeException('Isolated local candidate required'); }
$container = \Joomla\CMS\Factory::getContainer();
$container->alias('session', 'session.web.site')->alias(\Joomla\Session\SessionInterface::class, 'session.web.site');
$app = $container->get(\Joomla\CMS\Application\SiteApplication::class); \Joomla\CMS\Factory::$application = $app;
$app->loadIdentity(new \Joomla\CMS\User\User()); $app->createExtensionNamespaceMap();
(new ReflectionMethod($app, 'initialiseApp'))->invoke($app);
$app->loadDocument();
$checks = 0;
function check($v, $label) { global $checks; $checks++; if (!$v) throw new RuntimeException('FAIL: ' . $label); }
spl_autoload_register(static function ($class) use ($candidate) {
    foreach (['SuperSoft\\Plugin\\Content\\Sources\\' => '/plugins/content/sources/src/', 'SuperSoft\\Plugin\\Newsletter\\SourcesEmail\\' => '/plugins/newsletter/sourcesemail/src/', 'SuperSoft\\Plugin\\EditorsXtd\\SourcesButton\\' => '/plugins/editors-xtd/sourcesbutton/src/'] as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) { $path = $candidate . $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php'; if (is_file($path)) require_once $path; }
    }
}, true, true);
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;
use Joomla\Event\Event;
use SuperSoft\Plugin\Content\Sources\Support\RequestCache;
$original = $container->get(\Joomla\Database\DatabaseInterface::class);
$prefix = 'setest_' . bin2hex(random_bytes(5)) . '_';
$db = (new \Joomla\Database\DatabaseFactory())->getDriver($app->get('dbtype', 'mysqli'), ['host' => $app->get('host'), 'user' => $app->get('user'), 'password' => $app->get('password'), 'database' => $app->get('db'), 'prefix' => $prefix, 'select' => true]);
$tables = []; $files = []; $dirs = []; $pluginId = 0;
$pluginDir = JPATH_PLUGINS . '/newsletter/sourcesemail';
$existingProvider = $original->setQuery("SELECT extension_id, enabled FROM #__extensions WHERE type='plugin' AND folder='newsletter' AND element='sourcesemail'")->loadObject();
check((bool) $existingProvider === is_file($pluginDir . '/sourcesemail.xml'), 'provider files and registration must agree');
check(!$existingProvider || (int) $existingProvider->enabled === 1, 'existing provider must be enabled; never change user configuration');
$cache = new ReflectionProperty(PluginHelper::class, 'plugins'); $oldCache = $cache->getValue();
$child = new \Joomla\DI\Container($container); $child->set(\Joomla\Database\DatabaseInterface::class, $db, true);
$makeDir = static function ($dir) use (&$makeDir, &$dirs) { if (is_dir($dir)) return; $makeDir(dirname($dir)); mkdir($dir, 0755); $dirs[] = $dir; };
$resetFields = static function () use ($app, $db) {
    $factory = $app->bootComponent('com_fields')->getMVCFactory();
    foreach (['fieldsCache' => 'Fields', 'fieldCache' => 'Field'] as $property => $name) {
        $model = $factory->createModel($name, 'Administrator', ['ignore_request' => true]); $model->setDatabase($db);
        if ($name === 'Fields') { $model->setState('filter.state', 1); $model->setState('list.limit', 0); }
        (new ReflectionProperty(\Joomla\Component\Fields\Administrator\Helper\FieldsHelper::class, $property))->setValue(null, $model);
    }
};
$row = static function ($table, $values) use ($db) {
    $out = [];
    foreach ($db->getTableColumns($table, false) as $name => $info) {
        if (array_key_exists($name, $values)) { $out[$name] = $values[$name]; continue; }
        if ($info->Null === 'YES' || $info->Default !== null || str_contains($info->Extra, 'auto_increment') || str_contains($info->Extra, 'GENERATED')) continue;
        $out[$name] = preg_match('/int|decimal|float|double|bit/', $info->Type) ? 0 : (preg_match('/date|time/', $info->Type) ? '2026-01-01 00:00:00' : '');
    }
    $object = (object) $out; $db->insertObject($table, $object); return $object;
};
try {
    foreach (['extensions', 'content', 'categories', 'fields', 'fields_values', 'fields_categories', 'fields_groups', 'contentitem_tag_map', 'tags', 'users', 'languages', 'viewlevels'] as $table) {
        $name = $prefix . $table; $db->setQuery('CREATE TABLE ' . $db->quoteName($name) . ' LIKE ' . $db->quoteName($original->getPrefix() . $table))->execute(); $tables[] = $name;
    }
    $row('#__categories', ['id' => 1, 'parent_id' => 0, 'lft' => 0, 'rgt' => 20, 'title' => 'Synthetic root', 'alias' => 'root', 'extension' => 'system', 'published' => 1, 'access' => 1, 'language' => '*', 'params' => '{}', 'metadata' => '{}']);
    foreach ([2 => 1, 3 => 2] as $id => $access) $row('#__categories', ['id' => $id, 'parent_id' => 1, 'lft' => $id, 'rgt' => 15 - $id, 'title' => 'Synthetic category', 'alias' => 'synthetic-' . $id, 'extension' => 'com_content', 'published' => 1, 'access' => $access, 'language' => '*', 'params' => '{}', 'metadata' => '{}']);
    $row('#__categories', ['id' => 4, 'parent_id' => 3, 'lft' => 4, 'rgt' => 5, 'title' => 'Restricted ancestor', 'alias' => 'restricted-ancestor', 'extension' => 'com_content', 'published' => 1, 'access' => 1, 'language' => '*', 'params' => '{}', 'metadata' => '{}']);
    $common = ['title' => 'Synthetic public', 'alias' => 'synthetic', 'introtext' => '<p>Safe <b>body</b> {name}</p><script>unsafe()</script>', 'fulltext' => '', 'catid' => 2, 'state' => 1, 'access' => 1, 'language' => '*', 'images' => '{"image_intro":"images/synthetic.jpg"}', 'urls' => '{}', 'attribs' => '{}', 'metadata' => '{}', 'publish_up' => '2020-01-01 00:00:00', 'publish_down' => null];
    foreach ([1 => [], 2 => ['access' => 2], 3 => ['state' => 0], 4 => ['publish_up' => '2099-01-01 00:00:00'], 5 => ['publish_down' => '2001-01-01 00:00:00'], 6 => ['catid' => 3], 7 => ['catid' => 4], 8 => ['language' => 'zz-ZZ']] as $id => $changes) $row('#__content', array_replace($common, $changes, ['id' => $id, 'alias' => 'synthetic-' . $id]));
    if (!$existingProvider) foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($candidate . '/plugins/newsletter/sourcesemail', FilesystemIterator::SKIP_DOTS)) as $file) {
        if (!$file->isFile()) continue;
        $relative = substr($file->getPathname(), strlen($candidate . '/plugins/newsletter/sourcesemail'));
        $target = $pluginDir . $relative; $makeDir(dirname($target)); copy($file->getPathname(), $target); $files[] = $target;
    }
    $extension = (object) ['name' => 'PLG_NEWSLETTER_SOURCESEMAIL', 'type' => 'plugin', 'element' => 'sourcesemail', 'folder' => 'newsletter', 'client_id' => 0, 'enabled' => 1, 'access' => 1, 'protected' => 0, 'manifest_cache' => '{}', 'params' => '{}', 'custom_data' => '', 'system_data' => '', 'ordering' => 99999, 'state' => 0];
    if (!$existingProvider) { $original->insertObject('#__extensions', $extension, 'extension_id'); $pluginId = (int) $extension->extension_id; }
    $cache->setValue(null, null);
    // Import with the real extension registry before switching only resource queries to fixture tables.
    check(PluginHelper::importPlugin('newsletter', 'sourcesemail'), 'real Joomla group import');
    $app->bootComponent('com_newsletterbridge'); $app->bootComponent('com_simplenewsletter');
    $resetFields();
    // Mirror registration metadata for dependency/component lookups, never alter installed rows.
    $db->setQuery('INSERT INTO ' . $db->quoteName($prefix . 'extensions') . ' SELECT * FROM ' . $db->quoteName($original->getPrefix() . 'extensions'))->execute();
    Factory::$container = $child;
    $app->setLanguageFilter(true);
    $config = ['version' => 2, 'display' => 'separate', 'blocks' => [['type' => 'articles', 'articles' => [1, 2, 3, 4, 5, 6, 7, 8], 'presentation' => ['layout' => 'contents']]]];
    $raw = '{sources:' . json_encode($config) . '}';
    $document = static fn($html, $purpose = 'preview') => new Registry(['version' => 1, 'purpose' => $purpose, 'publicOnly' => true, 'rootUrl' => 'http://localhost:8089/', 'html' => $html, 'limits' => ['maxBytes' => 2000000, 'maxItems' => 60, 'maxTokens' => 8, 'maxDepth' => 3], 'usage' => ['items' => 0, 'tokens' => 0], 'errors' => [], 'unresolved' => []]);
    $dispatch = static function ($doc) use ($app) {
        RequestCache::reset(); $app->getDispatcher()->dispatch('onNewsletterPrepareEmail', new Event('onNewsletterPrepareEmail', ['context' => 'com_newsletterbridge.newsletter', 'document' => $doc])); return $doc;
    };
    $first = null;
    // Direct oracle surfaces renderer exceptions instead of the provider's safe public code.
    (new \SuperSoft\Plugin\Content\Sources\Support\EmailRenderer($document($raw), JPATH_PLUGINS . '/newsletter/sourcesemail/layouts'))->prepare($raw);
    foreach (['preview', 'test', 'send'] as $purpose) {
        $doc = $dispatch($document('<p>Outside {email}</p>' . $raw, $purpose));
        check($doc->get('errors') === [], 'provider success ' . json_encode($doc->get('errors')));
        check($doc->get('usage.items') === 1, 'private/unpublished/future/expired/restricted category excluded');
        check(str_contains($doc->get('html'), '<b>body</b> {name}') && !str_contains($doc->get('html'), '<script'), 'actual body sanitised');
        check($first === null || $first === $doc->get('html'), 'equivalent purpose output'); $first = $doc->get('html');
    }
    $doc = $dispatch($document('<p>Plain {name}</p>')); check($doc->get('usage.tokens') === 0 && $doc->get('html') === '<p>Plain {name}</p>', 'no-token no-op');
    $doc = $dispatch($document('{sources:{"version":99}}')); check($doc->get('errors') !== [], 'future token fails closed');
    foreach (['categories', 'tags'] as $type) {
        if ($type === 'tags') {
            $row('#__tags', ['id' => 1, 'parent_id' => 0, 'lft' => 1, 'rgt' => 2, 'title' => 'Synthetic tag', 'alias' => 'synthetic-tag', 'published' => 1, 'access' => 1, 'language' => '*', 'params' => '{}', 'metadata' => '{}', 'urls' => '{}', 'images' => '{}']);
            $row('#__contentitem_tag_map', ['type_alias' => 'com_content.article', 'content_item_id' => 1, 'tag_id' => 1]);
        }
        $dynamic = ['version' => 2, 'display' => 'separate', 'blocks' => [['type' => $type, 'filters' => [$type => [$type === 'tags' ? 1 : 2]]]]];
        $doc = $dispatch($document('{sources:' . json_encode($dynamic) . '}'));
        check($doc->get('errors') === [] && $doc->get('usage.items') === 1, 'real dynamic ' . $type);
    }
    $row('#__content', array_replace($common, ['id' => 9, 'alias' => 'synthetic-child', 'title' => 'Synthetic child']));
    $fieldConfig = ['version' => 2, 'display' => 'separate', 'blocks' => [['type' => 'articles', 'articles' => [9]]]];
    foreach ([1 => 1, 2 => 2] as $idField => $access) {
        $row('#__fields', ['id' => $idField, 'context' => 'com_content.article', 'type' => 'sources', 'name' => 'synthetic-sources-' . $idField, 'title' => 'Synthetic Sources', 'state' => 1, 'access' => $access, 'language' => '*', 'params' => '{}', 'fieldparams' => '{}', 'default_value' => '', 'group_id' => 0, 'only_use_in_subform' => 0]);
        $row('#__fields_values', ['field_id' => $idField, 'item_id' => '1', 'value' => $access === 1 ? json_encode($fieldConfig) : '{invalid-private-data}']);
    }
    $resetFields();
    $doc = $dispatch($document($raw));
    if ($doc->get('errors') !== []) {
        (new \SuperSoft\Plugin\Content\Sources\Support\EmailRenderer($document($raw), $candidate . '/plugins/newsletter/sourcesemail/layouts'))->prepare($raw);
    }
    check($doc->get('errors') === [] && $doc->get('usage.items') === 2 && str_contains($doc->get('html'), 'Synthetic child'), 'public Sources field rendered; private field excluded');
    $db->setQuery('DELETE FROM #__fields_values')->execute(); $db->setQuery('DELETE FROM #__fields')->execute();
    $resetFields();
    $snapshot = $first; $db->setQuery("UPDATE #__content SET title='Synthetic changed' WHERE id=1")->execute();
    check($snapshot === $first, 'prepared snapshot is detached');
    check(str_contains($dispatch($document($raw))->get('html'), 'Synthetic changed'), 'new preparation observes changed fixture');
    $overridePath = $candidate . '/overrides'; $overrideFile = $overridePath . '/sources/email/item.php';
    $makeDir(dirname($overrideFile)); $files[] = $overrideFile;
    file_put_contents($overrideFile, '<?php echo "<p>Native email override: " . htmlspecialchars($displayData["item"]["title"], ENT_QUOTES, "UTF-8") . "</p>";');
    $overrideDoc = $document($raw);
    $overrideHtml = (new \SuperSoft\Plugin\Content\Sources\Support\EmailRenderer($overrideDoc, $candidate . '/plugins/newsletter/sourcesemail/layouts', [$overridePath]))->prepare($raw);
    check(str_contains($overrideHtml, 'Native email override: Synthetic changed'), 'real Joomla FileLayout provider override');
    // Run the real bridge lifecycle with synthetic newsletter tables and memory-only transport.
    foreach (['simplenewsletter_templates', 'simplenewsletter_jobs', 'simplenewsletter_queue', 'simplenewsletter_subscribers', 'newsletterbridge_archive'] as $table) {
        $name = $prefix . $table; $db->setQuery('CREATE TABLE ' . $db->quoteName($name) . ' LIKE ' . $db->quoteName($original->getPrefix() . $table))->execute(); $tables[] = $name;
    }
    $mock = new class implements \Joomla\CMS\Mail\MailerFactoryInterface {
        public array $messages = [];
        public function createMailer(?Registry $settings = null): \Joomla\CMS\Mail\MailerInterface {
            return new class($this) extends \Joomla\CMS\Mail\Mail {
                public function __construct(private $owner) { parent::__construct(true); }
                public function Send() { $this->owner->messages[] = ['body' => $this->Body, 'subject' => $this->Subject]; return true; }
            };
        }
    };
    $child->set(\Joomla\CMS\Mail\MailerFactoryInterface::class, $mock, true);
    $app->set('mailonline', 1); $app->set('mailfrom', 'fixture@example.invalid');
    $author = new class extends \Joomla\CMS\User\User {
        public function authorise($action, $assetname = null) { return in_array($assetname, ['com_newsletterbridge', 'com_simplenewsletter', 'com_content'], true); }
    };
    $author->id = 900000001; $author->guest = 0; $author->name = 'Synthetic author'; $author->email = 'author@example.invalid'; $app->loadIdentity($author);
    $preview = new \SuperSoft\Plugin\EditorsXtd\SourcesButton\Extension\SourcesButton($app->getDispatcher(), (array) PluginHelper::getPlugin('editors-xtd', 'sourcesbutton'));
    $preview->setApplication($app);
    $app->getInput()->server->set('HTTP_X_CSRF_TOKEN', \Joomla\CMS\Session\Session::getFormToken());
    $previewDispatch = static function () use ($preview, $app) {
        $event = new \Joomla\CMS\Event\Plugin\AjaxEvent('onAjaxSourcesbutton', ['subject' => $app]);
        $preview->onAjaxSourcesbutton($event); return $event->getArgument('result')[0];
    };
    $fieldsComponent = \Joomla\CMS\Component\ComponentHelper::getComponent('com_fields'); $fieldsEnabled = $fieldsComponent->enabled;
    // Fields were tested above with explicitly injected fixture models. Preview deliberately
    // clears core identity-bound field caches, so this phase isolates native-body rendering.
    try {
        $fieldsComponent->enabled = false; $widths = [];
        foreach (['cards', 'details', 'list', 'icons', 'contents'] as $layout) {
            $emailConfig = $config; $emailConfig['blocks'][0]['presentation']['layout'] = $layout;
            $app->getInput()->post->set('configuration', json_encode($emailConfig));
            $app->getInput()->post->set('mode', 'render'); $app->getInput()->post->set('presentation', 'email');
            $result = $previewDispatch();
            check($result['ok'] && $result['renderer'] === 'EmailRenderer' && $result['presentation'] === 'email' && $result['styles'] === [], 'AJAX selects email pipeline without site assets');
            check($app->getIdentity() === $author, 'AJAX restores exact author identity');
            $app->loadIdentity(new \Joomla\CMS\User\User());
            $oracle = $dispatch($document('{sources:' . json_encode($emailConfig) . '}'));
            $app->loadIdentity($author);
            check($result['html'] === $oracle->get('html') && $oracle->get('usage.items') === 1, 'AJAX output exactly matches provider public-only email output');
            $dom = new DOMDocument(); @$dom->loadHTML($result['html']); $widths[] = $dom->getElementsByTagName('img')->item(0)?->getAttribute('width');
        }
        check(count(array_unique($widths)) === 5, 'server selects five distinct email item layouts');
        $app->getInput()->post->set('presentation', 'invalid'); check(!$previewDispatch()['ok'], 'untrusted context cannot select arbitrary renderer');
        $app->getInput()->post->set('presentation', 'email'); $author->guest = 1;
        try { $previewDispatch(); throw new LogicException('Anonymous context must not authorize preview'); }
        catch (RuntimeException $error) { check($error->getCode() === 403, 'email context does not bypass author authorization'); }
        $author->guest = 0;
        $badConfig = $config; $badConfig['blocks'][0]['articles'] = [['selection' => ['adapter' => 'articles', 'id' => 'article:1'], 'usage' => ['ext.unsupported' => true]]];
        $app->getInput()->post->set('configuration', json_encode($badConfig));
        check(!$previewDispatch()['ok'] && $app->getIdentity() === $author, 'failed email AJAX restores identity and fails closed');
    } finally { $fieldsComponent->enabled = $fieldsEnabled; $app->loadIdentity($author); $resetFields(); }
    $params = \Joomla\CMS\Component\ComponentHelper::getParams('com_simplenewsletter');
    $params->set('queue_retention', 30); $params->set('sender_mode', 'global'); $params->set('batch_size', 1);
    \Joomla\CMS\Component\ComponentHelper::getParams('com_newsletterbridge')->set('preset_category', 0);
    $factory = new \Joomla\CMS\MVC\Factory\MVCFactory('SaintArt\\Component\\SimpleNewsletter');
    $factory->setDatabase($db); $factory->setDispatcher($app->getDispatcher()); $factory->setUserFactory($container->get(\Joomla\CMS\User\UserFactoryInterface::class));
    $adapter = new class($factory, $app, $db) {
        public function __construct(private $factory, private $app, private $db) {}
        public function createModel($name, $prefix, $config) {
            $config['dbo'] = $this->db; $model = $this->factory->createModel($name, $prefix, $config); $model->setDatabase($this->db);
            if (method_exists($model, 'setCurrentUser')) $model->setCurrentUser($this->app->getIdentity()); return $model;
        }
    };
    $bridge = new \SuperSoft\Component\Newsletterbridge\Site\Service\Bridge($app, $db, $adapter);
    $id = $bridge->save(0, ['title' => 'Synthetic Sources newsletter', 'subject' => 'Synthetic {name}', 'body' => 'Fixture', 'published' => 1]);
    $newsletter = (object) ['id' => $id, 'body' => $raw]; $db->updateObject('#__simplenewsletter_templates', $newsletter, 'id');
    foreach ([1, 2] as $n) {
        $subscriber = (object) ['name' => 'Synthetic ' . $n, 'email' => 'fixture' . $n . '@example.invalid', 'state' => 1, 'token' => str_repeat((string) $n, 48)];
        $db->insertObject('#__simplenewsletter_subscribers', $subscriber, 'id');
    }
    $events = 0; $app->getDispatcher()->addListener('onNewsletterPrepareEmail', static function () use (&$events) { $events++; });
    check(str_contains($bridge->preview($id), 'Synthetic changed'), 'actual bridge preview uses Sources provider');
    $bridge->test($id, 'author@example.invalid'); check(count($mock->messages) === 1 && str_contains($mock->messages[0]['body'], 'Synthetic changed'), 'test uses memory transport and provider');
    $job = $bridge->start($id, true); $calls = $events; check($calls === 3, 'exactly one preparation for each preview/test/new send');
    $snapshot = $db->setQuery('SELECT body FROM #__simplenewsletter_jobs WHERE id=' . (int) $job['jobId'])->loadResult();
    check(str_contains($snapshot, 'Synthetic changed') && str_contains($snapshot, '{name}') && !str_contains($snapshot, '{sources:'), 'actual job stores rendered snapshot with subscriber placeholders');
    $db->setQuery("UPDATE #__content SET title='Synthetic newest' WHERE id=1")->execute();
    $job = $bridge->process($job['jobId']); $job = $bridge->process($job['jobId']);
    check($events === $calls, 'batch/resume never redispatch provider');
    check(count($mock->messages) === 3 && str_contains($mock->messages[2]['body'], 'Synthetic changed') && !str_contains($mock->messages[2]['body'], 'Synthetic newest'), 'resumed batch uses original snapshot');
    check($app->getIdentity() === $author, 'bridge restores author identity');
    echo 'Real Joomla loader/event/public-policy checks passed: ' . $checks . "; 3 memory-only messages, no real mail\n";
} finally {
    Factory::$container = $container;
    if ($pluginId) $original->setQuery('DELETE FROM #__extensions WHERE extension_id=' . $pluginId)->execute();
    $cache->setValue(null, $oldCache);
    foreach (array_reverse($tables) as $table) $db->setQuery('DROP TABLE IF EXISTS ' . $db->quoteName($table))->execute();
    foreach ($files as $file) if (is_file($file)) unlink($file);
    foreach (array_reverse($dirs) as $dir) if (is_dir($dir)) rmdir($dir);
}
check($existingProvider ? is_file($pluginDir . '/sourcesemail.xml') : !file_exists($pluginDir), 'provider files preserved or fixture removed');
check((int) $original->setQuery("SELECT COUNT(*) FROM #__extensions WHERE type='plugin' AND folder='newsletter' AND element='sourcesemail'")->loadResult() === ($existingProvider ? 1 : 0), 'provider registration preserved or fixture removed');
check($db->setQuery('SHOW TABLES LIKE ' . $db->quote($prefix . '%'))->loadColumn() === [], 'all synthetic tables removed');
echo "Cleanup verified\n";
