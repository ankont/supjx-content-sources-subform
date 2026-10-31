<?php
namespace SuperSoft\Plugin\Content\Sources\Support;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\Registry\Registry;

/** Authenticated callers own authorization. Presentation always runs under public policy. */
final class EmailPreview
{
    public static function render(array $config, string $mode): string
    {
        $app = Factory::getApplication(); $identity = $app->getIdentity();
        $fields = class_exists(\Joomla\Component\Fields\Administrator\Helper\FieldsHelper::class);
        try {
            if ($fields) \Joomla\Component\Fields\Administrator\Helper\FieldsHelper::clearFieldsCache();
            $app->loadIdentity(new User(0));
            if ($mode !== 'render') {
                $resolver = new InlineRenderer(new Registry(), true);
                return $resolver->render($config, (object) ['id' => 0], $mode, 'preview');
            }
            $document = new Registry([
                'version' => 1, 'purpose' => 'preview', 'publicOnly' => true, 'rootUrl' => Uri::root(),
                'limits' => EmailRenderer::LIMITS, 'usage' => ['items' => 0, 'tokens' => 0], 'errors' => [], 'unresolved' => [],
            ]);
            $renderer = new EmailRenderer($document, JPATH_PLUGINS . '/newsletter/sourcesemail/layouts');
            return $renderer->prepare('{sources:' . json_encode($config, JSON_THROW_ON_ERROR) . '}');
        } finally {
            $app->loadIdentity($identity);
            if ($fields) \Joomla\Component\Fields\Administrator\Helper\FieldsHelper::clearFieldsCache();
        }
    }
}
