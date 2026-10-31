<?php
namespace SuperSoft\Plugin\Newsletter\SourcesEmail\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use SuperSoft\Plugin\Content\Sources\Support\EmailRenderer;

final class SourcesEmail extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;
    public static function getSubscribedEvents(): array { return ['onNewsletterPrepareEmail' => 'prepareEmail']; }
    public function prepareEmail(EventInterface $event): void
    {
        if ($event->getArgument('context') !== 'com_newsletterbridge.newsletter') { return; }
        $document = $event->getArgument('document');
        if (!$document instanceof Registry || $document->get('version') !== 1) { return; }
        $html = $document->get('html');
        if (!is_string($html)) { return; }
        // Keep the no-token path independent of Sources namespace/plugin loading.
        $probe = $html;
        if (stripos($probe, 'sources') === false) { return; }
        for ($i = 0; $i < 16 && !preg_match('/\{\s*sources\b/i', $probe); $i++) {
            $decoded = html_entity_decode($probe, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $probe) { return; } $probe = $decoded;
        }
        try {
            if ($document->get('publicOnly') !== true || !in_array($document->get('purpose'), ['preview', 'test', 'send'], true)
                || (int) $this->getApplication()->getIdentity()->id !== 0) { throw new \RuntimeException('Public policy required'); }
            $this->getApplication()->bootPlugin('sources', 'content');
            $renderer = new EmailRenderer($document, dirname(__DIR__, 2) . '/layouts');
            $document->set('html', $renderer->prepare($html));
        } catch (\Throwable $error) {
            $errors = $document->get('errors', []);
            if (!is_array($errors)) { throw new \RuntimeException('Invalid email diagnostics'); }
            $errors[] = 'sources.email.failed'; $document->set('errors', $errors);
        }
    }
}
