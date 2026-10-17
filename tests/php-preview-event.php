<?php
namespace Joomla\CMS {
    class Factory { public static $app; public static function getApplication() { return self::$app; } }
}
namespace Joomla\CMS\Plugin {
    class CMSPlugin {}
    class PluginHelper {
        public static bool $enabled = true;
        public static function isEnabled($group, $plugin) { return self::$enabled; }
        public static function getPlugin($group, $plugin) { return (object) ['params' => '{}']; }
    }
}
namespace Joomla\CMS\Session {
    class Session { public static bool $valid = true; public static function checkToken($method) { return self::$valid; } }
}
namespace Joomla\CMS\Log {
    class Log { public const WARNING = 1; public static function add(...$arguments) {} }
}
namespace SuperSoft\Plugin\Content\Sources\Support {
    class InlineRenderer {
        public static bool $fail = false;
        public function __construct($params) {}
        public function render($config, $article, $mode, $purpose) {
            if ($purpose !== 'preview') throw new \RuntimeException('Wrong image-presentation purpose');
            if (self::$fail) throw new \RuntimeException('Private SQL/server details');
            return '<section>Actual event preview: ' . $mode . '</section>';
        }
    }
}
namespace {
    $site = $argv[1] ?? dirname(__DIR__, 3) . '/JoomlaTest/site';
    if (!is_file($site . '/libraries/src/Event/Plugin/AjaxEvent.php')) {
        fwrite(STDERR, 'Local Joomla libraries are required.'); exit(3);
    }
    define('_JEXEC', 1); define('JDEBUG', false);
    require $site . '/libraries/vendor/autoload.php';
    spl_autoload_register(static function ($class) use ($site) {
        $prefix = 'Joomla\\CMS\\';
        if (str_starts_with($class, $prefix)) {
            $path = $site . '/libraries/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($path)) require_once $path;
        }
    });
    require dirname(__DIR__) . '/package/plugins/content/sources/src/Support/InlineConfig.php';
    require dirname(__DIR__) . '/package/plugins/editors-xtd/sourcesbutton/src/Extension/SourcesButton.php';
    class PreviewApplication extends \Joomla\Application\AbstractApplication {
        public string $json = '{"version":2,"display":"separate","blocks":[{"type":"articles","articles":[4]}]}';
        public string $mode = 'list_manual';
        public bool $allowed = true;
        protected function doExecute() {}
        public function getIdentity() { return new class($this->allowed) {
            public bool $guest = false;
            public function __construct(private bool $allowed) {}
            public function authorise($action, $asset) { return $this->allowed; }
            public function getAuthorisedCategories(...$args) { return []; }
        }; }
        public function isClient($client) { return $client === 'site'; }
        public function bootPlugin(...$args) {}
        public function getInput() { return new class($this->json, $this->mode) {
            public $post;
            public function __construct(private string $json, private string $mode) { $this->post = $this; }
            public function get(...$args) { return $this->json; }
            public function getCmd(...$args) { return $this->mode; }
        }; }
    }
    function check($condition, $message) { if (!$condition) throw new \RuntimeException($message); }
    $app = new PreviewApplication();
    \Joomla\CMS\Factory::$app = $app;
    $subscriber = new \SuperSoft\Plugin\EditorsXtd\SourcesButton\Extension\SourcesButton();
    $dispatcher = new \Joomla\Event\Dispatcher();
    $broken = new \Joomla\Event\Dispatcher();
    $broken->addListener('onAjaxSourcesbutton', static fn($event) => ['ok' => true, 'html' => 'Lost return value']);
    $brokenEvent = new \Joomla\CMS\Event\Plugin\AjaxEvent('onAjaxSourcesbutton', ['subject' => $app]);
    check($broken->dispatch('onAjaxSourcesbutton', $brokenEvent)->getArgument('result', []) === [], 'Reproduce the old ignored subscriber return value');
    $dispatcher->addSubscriber($subscriber);
    $dispatch = static function () use ($dispatcher, $app) {
        $event = new \Joomla\CMS\Event\Plugin\AjaxEvent('onAjaxSourcesbutton', ['subject' => $app]);
        return $dispatcher->dispatch('onAjaxSourcesbutton', $event)->getArgument('result', []);
    };
    foreach (['list_manual', 'list_full', 'render'] as $mode) {
        $app->mode = $mode; $results = $dispatch();
        check(count($results) === 1 && $results[0]['ok'] === true, 'Subscriber must attach its result to the real Joomla AjaxEvent');
        check(str_contains($results[0]['html'], $mode), 'All editor preview modes must return HTML');
        $json = json_decode((string) new \Joomla\CMS\Response\JsonResponse($results, null, false, true), true);
        check($json['success'] === true && $json['data'][0]['html'] === $results[0]['html'], 'Actual com_ajax JSON envelope contains preview data');
    }
    \Joomla\CMS\Plugin\PluginHelper::$enabled = false;
    check($dispatch()[0]['code'] === 'plugin_disabled', 'Disabled plugin response is attached to event');
    \Joomla\CMS\Plugin\PluginHelper::$enabled = true;
    $valid = $app->json; $app->json = '{"version":999}';
    check($dispatch()[0]['code'] === 'invalid_configuration', 'Invalid schema has an explicit error code');
    $app->json = $valid;
    \SuperSoft\Plugin\Content\Sources\Support\InlineRenderer::$fail = true;
    $failure = $dispatch()[0];
    check($failure['code'] === 'render_failed' && !str_contains(json_encode($failure), 'SQL'), 'Rendering errors do not expose private diagnostics');
    \Joomla\CMS\Session\Session::$valid = false;
    try { $dispatch(); throw new \LogicException('CSRF must fail'); }
    catch (\RuntimeException $error) { check($error->getCode() === 403, 'CSRF checks remain enforced'); }
    \Joomla\CMS\Session\Session::$valid = true; $app->allowed = false;
    try { $dispatch(); throw new \LogicException('ACL must fail'); }
    catch (\RuntimeException $error) { check($error->getCode() === 403, 'Authoring ACL remains enforced'); }
    echo 'Real Joomla AjaxEvent preview assertions passed';
}
