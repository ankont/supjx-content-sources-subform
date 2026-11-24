<?php
namespace SuperSoft\Plugin\Newsletter\SourcesEmail\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use SuperSoft\Plugin\Content\Sources\Support\EmailRenderer;

final class SourcesEmail extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;
    public static function getSubscribedEvents(): array { return ['onNewsletterPrepareEmail' => 'prepareEmail']; }
    public function getCapabilities(): array
    {
        $available = true; $enabled = false;
        try {
            $params = new Registry(PluginHelper::getPlugin('newsletter', 'sourcesemail')->params ?? '');
            $enabled = in_array($params->get('email_private_enabled', 0), [true,1,'1'], true);
        } catch (\Throwable $error) { $available = false; }
        return ['versions'=>[1,2], 'privateRendering'=>true,
            'privateEnabled'=>$enabled, 'policyAvailable'=>$available];
    }
    public function prepareEmail(EventInterface $event): void
    {
        $document = $event->getArgument('document');
        if (!$document instanceof Registry || !in_array($document->get('version'), [1,2], true)) { return; }
        $capabilities = $this->getCapabilities();
        // Bridge v1 validates an exact top-level whitelist; response extensions belong to v2.
        if ($document->get('version') === 2) {
            $document->set('providers.sourcesemail', ['capabilities'=>$capabilities, 'effectivePublicOnly'=>null, 'effectiveUserId'=>null]);
        }
        $html = $document->get('html');
        if (!is_string($html) || !is_bool($document->get('publicOnly')) || !in_array($document->get('purpose'), ['preview','test','send'], true)
            || !is_string($document->get('rootUrl')) || !preg_match('~^https?://[^/]+/~i', $document->get('rootUrl'))
            || !is_int($document->get('limits.maxBytes')) || $document->get('limits.maxBytes') < 1
            || !is_array($document->get('errors', [])) || !is_array($document->get('warnings', []))
            || !is_int($document->get('usage.items', 0)) || $document->get('usage.items', 0) < 0
            || !is_int($document->get('usage.tokens', 0)) || $document->get('usage.tokens', 0) < 0) {
            $this->diagnostic($document, 'errors', 'sources.email.invalid_document'); return;
        }
        // Keep the no-token path independent of Sources namespace/plugin loading.
        $probe = $html;
        if (stripos($probe, 'sources') === false) { return; }
        for ($i = 0; $i < 16 && !preg_match('/\{\s*sources\b/i', $probe); $i++) {
            $decoded = html_entity_decode($probe, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $probe) { return; } $probe = $decoded;
        }
        try {
            $user = new User(0);
            if ($document->get('publicOnly') === false) {
                $warning = null;
                if ($document->get('version') !== 2) { $warning = 'sources.email.private_contract_required'; }
                elseif (!$capabilities['policyAvailable']) { $warning = 'sources.email.private_access_unavailable'; }
                elseif (!$capabilities['privateEnabled']) { $warning = 'sources.email.private_disabled'; }
                elseif ($document->get('userId') === null) { $warning = 'sources.email.user_missing'; }
                elseif (!is_int($document->get('userId')) || $document->get('userId') < 1) { $warning = 'sources.email.user_invalid'; }
                else {
                    try {
                        $factory = Factory::getContainer()->get(UserFactoryInterface::class);
                        if (!$factory instanceof UserFactoryInterface) { throw new \RuntimeException('User factory unavailable'); }
                        $candidate = $factory->loadUserById($document->get('userId'));
                        if (!$candidate instanceof User || (int) $candidate->id !== $document->get('userId') || !empty($candidate->guest)) { $warning = 'sources.email.user_invalid'; }
                        elseif (!empty($candidate->block)) { $warning = 'sources.email.user_blocked'; }
                        elseif ((string) ($candidate->activation ?? '') !== '') { $warning = 'sources.email.user_inactive'; }
                        else {
                            $levels = $candidate->getAuthorisedViewLevels();
                            if (!is_array($levels) || !$levels || array_filter($levels, static fn($level)=>!is_int($level) || $level < 1)) { throw new \RuntimeException('Invalid access levels'); }
                            $user = $candidate;
                        }
                    } catch (\Throwable $error) { $warning = 'sources.email.private_access_unavailable'; }
                }
                if ($warning !== null) { $this->diagnostic($document, 'warnings', $warning); }
            }
            try { $result = $this->renderAs($document, $html, $user); }
            catch (\Throwable $error) {
                if ((int) $user->id === 0) { throw $error; }
                $this->diagnostic($document, 'warnings', 'sources.email.private_render_failed');
                $user = new User(0);
                $result = $this->renderAs($document, $html, $user);
            }
            $document->set('html', $result['html']);
            $document->set('usage.items', $result['items']); $document->set('usage.tokens', $result['tokens']);
            if ($document->get('version') === 2) {
                $document->set('providers.sourcesemail.effectivePublicOnly', (int) $user->id === 0);
                $document->set('providers.sourcesemail.effectiveUserId', (int) $user->id);
            }
        } catch (\Throwable $error) {
            $this->diagnostic($document, 'errors', 'sources.email.failed');
        }
    }
    private function diagnostic(Registry $document, string $severity, string $code): void
    {
        $messages = $document->get($severity, []);
        if (!is_array($messages)) { $messages = []; }
        $messages[] = $code; $document->set($severity, $messages);
    }
    private function renderAs(Registry $document, string $html, User $user): array
    {
        $app = $this->getApplication() ?? Factory::getApplication();
        $original = $app->getIdentity();
        $fields = class_exists(FieldsHelper::class);
        try {
            $app->loadIdentity($user);
            if ($fields) { FieldsHelper::clearFieldsCache(); }
            $app->bootPlugin('sources', 'content');
            $working = new Registry(['version'=>$document->get('version'), 'purpose'=>$document->get('purpose'),
                'publicOnly'=>(int) $user->id === 0, 'userId'=>(int) $user->id, 'rootUrl'=>$document->get('rootUrl'),
                'limits'=>['maxBytes'=>$document->get('limits.maxBytes')],
                'usage'=>['items'=>$document->get('usage.items', 0),'tokens'=>$document->get('usage.tokens', 0)]]);
            $renderer = new EmailRenderer($working, dirname(__DIR__, 2) . '/layouts');
            $rendered = $renderer->prepare($html);
            return ['html'=>$rendered,'items'=>$working->get('usage.items'),'tokens'=>$working->get('usage.tokens')];
        } finally {
            try { $app->loadIdentity($original); }
            finally { if ($fields) { FieldsHelper::clearFieldsCache(); } }
        }
    }
}
