<?php
namespace SuperSoft\Plugin\Content\Sources\Extension;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use SuperSoft\Plugin\Content\Sources\Support\InlineTokens;
use SuperSoft\Plugin\Content\Sources\Support\InlineRenderer;
use SuperSoft\Plugin\Content\Sources\Support\LegacySources;

final class Sources extends CMSPlugin
{
    protected $autoloadLanguage = true;
    private static int $renderDepth = 0;

    public function onContentPrepare($context, &$article, &$params, $page = 0): void
    {
        $app = Factory::getApplication();
        if (!$app->isClient('site') || !in_array($context, ['com_content.article', 'com_content.category', 'com_content.featured'], true)
            || !is_object($article) || !isset($article->text) || !is_string($article->text)
            || $app->getInput()->getCmd('layout') === 'edit' || $app->getInput()->getCmd('view') === 'form' || self::$renderDepth >= 3) {
            return;
        }
        self::$renderDepth++;
        try {
            // Run before inline output so template-rendered text is not mistaken for stored tokens.
            LegacySources::prepare($context, $article, $this->params);
            if (strpos($article->text, '{sources:') === false) { return; }
            $renderer = new InlineRenderer($this->params);
            if ($app->getDocument()->getType() === 'html') {
                $app->getDocument()->getWebAssetManager()->registerAndUseStyle('plg.sources.frontend', 'plg_editors-xtd_sourcesbutton/sourcesbutton.css', ['version' => 'auto']);
            }
            $article->text = InlineTokens::replace($article->text, function (array $config) use ($context, $article, $renderer): string {
                if ($context !== 'com_content.article') {
                    return $this->params->get('list_token_mode', 'hide') === 'placeholder'
                        ? '<span class="sources-list-placeholder">[' . htmlspecialchars(Text::_('PLG_CONTENT_SOURCES_LIST_TOKEN_PLACEHOLDER'), ENT_QUOTES, 'UTF-8') . ']</span>' : '';
                }
                return $renderer->render($config, $article);
            });
        } catch (\Throwable $error) {
            // Failed rendering must never replace the stored configuration or damage the source.
            \Joomla\CMS\Log\Log::add('Sources: ' . $error->getMessage(), \Joomla\CMS\Log\Log::WARNING, 'sources');
        } finally {
            self::$renderDepth--;
        }
    }
}
