<?php
namespace SuperSoft\Plugin\Content\Sources\Support;
require_once __DIR__ . '/Introduction.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Language\Text;
use SuperSoft\Component\Smartbrowser\Administrator\Support\SelectionOutputRenderer;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Registry\Registry;

final class EmailRenderer
{
    public const LIMITS = ['maxBytes' => 2000000];
    private int $contentsLevels;
    private mixed $showUnavailable;
    private array $active = [];
    private int $introductionDepth = 0;
    private InlineRenderer $resolver;
    private array $limits;
    private string $root;
    private ?array $layoutPaths = null;

    public function __construct(private Registry $document, private string $layoutPath, private ?array $overridePaths = null)
    {
        if ($document->get('version') !== 1 || $document->get('publicOnly') !== true || (int) Factory::getApplication()->getIdentity()->id !== 0) { throw new \RuntimeException('sources.email.public_identity'); }
        $this->root = (string) $document->get('rootUrl', '');
        if (!preg_match('~^https?://[^/]+/~i', $this->root) || EmailHtml::url($this->root, $this->root) === '') { throw new \RuntimeException('sources.email.root'); }
        $this->limits = [];
        foreach (self::LIMITS as $key => $maximum) {
            $value = $document->get('limits.' . $key);
            if (!is_int($value) || $value < 1) { throw new \RuntimeException('sources.email.limits'); }
            $this->limits[$key] = min($maximum, $value);
        }
        foreach (['items', 'tokens'] as $key) {
            $value = $document->get('usage.' . $key);
            if (!is_int($value) || $value < 0) { throw new \RuntimeException('sources.email.usage'); }
        }
        $params = new Registry(PluginHelper::getPlugin('content', 'sources')->params ?? '');
        $this->contentsLevels = max(1, (int) $params->get('contents_levels', 3));
        $this->showUnavailable = $params->get('show_unavailable', '');
        Factory::getApplication()->getLanguage()->load('plg_content_sources', JPATH_ADMINISTRATOR);
        $this->resolver = new InlineRenderer(new Registry(), true);
    }

    public function prepare(string $html, int $depth = 1): string
    {
        $this->bytes($html);
        $tokens = InlineTokens::scan($html);
        $remaining = $html;
        foreach (array_reverse($tokens) as $token) {
            if ($token['config'] === null) { throw new \RuntimeException('sources.email.configuration'); }
            $remaining = substr_replace($remaining, '', $token['start'], $token['end'] - $token['start']);
        }
        if (EmailHtml::hasSourcesSyntax($remaining)) { throw new \RuntimeException('sources.email.unresolved'); }
        if (!$tokens) { return $html; }
        $result = ''; $offset = 0;
        foreach ($tokens as $token) {
            $this->consume('tokens');
            $result .= substr($html, $offset, $token['start'] - $offset) . $this->container($token['config'], $depth);
            $this->bytes($result); $offset = $token['end'];
        }
        $result .= substr($html, $offset); $this->bytes($result);
        return $result;
    }

    private function container(array $config, int $depth): string
    {
        // Retain the shared selector's per-container bounds, not a cumulative email item cap.
        $blocks = $this->resolver->resolveBlocks($config, 'email', 2000, $depth === 1 && $this->introductionDepth === 0);
        $html = '';
        foreach ($blocks as $block) {
            $models = [];
            foreach ($block['items'] as $item) {
                if (!empty($item->sources_unavailable)) {
                    $prepared = $this->resolver->prepareOutput([$item], ['email']);
                    $selected = $prepared['article:' . $item->id] ?? null;
                    if ($selected !== null) {
                        if (SelectionOutputRenderer::showUnavailable($this->showUnavailable)) {
                            $safe = SelectionOutputRenderer::displayData(['unavailable' => true]);
                            $model = ['id' => 0, 'unavailable' => true, 'title' => $safe['title'], 'url' => '',
                                'image' => EmailHtml::url($safe['imageAddress'] ?? '', $this->root, true),
                                'icon' => $safe['icon'], 'summary' => '', 'content' => '',
                                'header_only' => false, 'fallback_reason' => null, 'depth' => $depth, 'read_more_text' => ''];
                            $model['html'] = $this->layout('sources.email.item', ['item' => $model, 'layout' => $block['layout'],
                                'unavailable' => true, 'purpose' => $this->document->get('purpose'),
                                'header_only' => false, 'fallback_reason' => null, 'depth' => $depth]);
                            $models[] = $model;
                        }
                    }
                    continue;
                }
                $this->consume('items'); $id = (int) $item->id;
                $cycle = isset($this->active[$id]);
                $reason = $block['layout'] === 'contents' ? ($cycle ? 'cycle' : ($depth > $this->contentsLevels ? 'depth' : null)) : null;
                if (!$cycle) { $this->active[$id] = true; }
                try {
                    $model = $this->article($item, $block['layout'], $depth, $reason);
                    $model['html'] = $this->layout('sources.email.item', ['item' => $model, 'layout' => $block['layout'],
                        'header_only' => $model['header_only'], 'fallback_reason' => $reason, 'depth' => $depth]);
                    $models[] = $model;
                }
                finally { if (!$cycle) { unset($this->active[$id]); } }
            }
            if (!$models) { continue; }
            $introHtml = ''; $emailIntroductions = [];
            foreach ($block['introductions'] ?? [] as $intro) {
                $this->introductionDepth++;
                try {
                    $model = Introduction::model($intro['entity'], $intro['type'], $this->root);
                    $presentation = $intro['presentation'] ?? IntroUsage::presentation([]);
                    $selected = $this->resolver->prepareIntroduction($intro);
                    if ($selected !== null && !empty($selected['unavailable'])) { continue; }
                    $model['icon'] = null;
                    if ($selected !== null) {
                        $selected['visualPresentation'] ??= 'both';
                        $visual = SelectionOutputRenderer::displayData($selected);
                        $model['image'] = EmailHtml::url($visual['imageAddress'] ?? '', $this->root, true);
                        $model['icon'] = $visual['icon'];
                    }
                    $model['description'] = in_array($presentation['elements'], ['both','description'], true)
                        ? (!empty($intro['entity']->sources_description_plain) ? $model['description'] : $this->supportedContent($model['description'], $depth)) : '';
                    $emailIntroductions[] = ['entity'=>$model,'type'=>$intro['type'],'criteria'=>$intro['criteria'],
                        'presentation'=>$presentation,'selected'=>$selected];
                    $introHtml .= $this->layout('sources.email.' . ($intro['type'] === 'tags' ? 'tag' : 'category') . '-intro', [
                        'entity' => $model, 'type' => $intro['type'], 'purpose' => $this->document->get('purpose') === 'preview' ? 'preview' : 'output',
                        'layout' => $block['layout'], 'display' => $config['display'], 'criteria' => $intro['criteria'],
                        'public_only' => true, 'depth' => $depth, 'parent_article_id' => 0,
                        'presentation'=>$presentation, 'selected'=>$selected,
                    ]);
                } finally { $this->introductionDepth--; }
            }
            $results = $this->layout('sources.email.block', ['title' => $block['title'], 'layout' => $block['layout'], 'items' => $models]);
            $html .= $introHtml !== '' || isset($block['group']) ? $this->layout('sources.email.group', [
                'introductions_html'=>$introHtml, 'results_html'=>$results, 'introductions'=>$emailIntroductions,
                'group'=>$block['group'] ?? null, 'layout'=>$block['layout'], 'display'=>$config['display'],
                'purpose'=>$this->document->get('purpose') === 'preview' ? 'preview' : 'output', 'public_only'=>true,
                'collapsible'=>(bool) array_filter($emailIntroductions, static fn($intro)=>$intro['presentation']['layout'] === 'collapsible'),
            ]) : $results;
            $this->bytes($html);
        }
        return $html;
    }

    private function article(object $item, string $layout, int $depth, ?string $reason): array
    {
        $images = json_decode((string) ($item->images ?? '{}'), true) ?: [];
        $image = (string) ($layout === 'contents' ? (($images['image_fulltext'] ?? '') ?: ($images['image_intro'] ?? '')) : (($images['image_intro'] ?? '') ?: ($images['image_fulltext'] ?? '')));
        $image = explode('#', $image, 2)[0];
        $usage = $item->sources_usage ?? [];
        $override = $usage['visual.thumbnailOverride'] ?? null;
        if ($override !== null || array_filter(array_keys($usage), static fn($key) => str_starts_with($key, 'ext.'))) {
            // Native article email output cannot safely resolve arbitrary media/provider extension data.
            throw new \RuntimeException('sources.email.unsupported_usage');
        }
        if (($usage['visual.presentation'] ?? '') === 'icon') { $image = ''; }
        $url = EmailHtml::url('index.php?option=com_content&view=article&id=' . (int) $item->id . '&catid=' . (int) $item->catid, $this->root);
        $content = '';
        if ($layout === 'contents' && $reason === null) {
            $raw = (string) ($item->introtext ?? '') . "\n" . (string) ($item->fulltext ?? '');
            $this->bytes($raw);
            // Only explicit Sources syntax and Sources fields are expanded; never general content events.
            $content = $this->supportedContent($raw, $depth + 1);
            if (ComponentHelper::isEnabled('com_fields') && class_exists(FieldsHelper::class)) {
                $fields = RequestCache::remember(RequestCache::key('email-fields', [$item->id, $item->catid, $item->language ?? '']),
                    static fn() => FieldsHelper::getFields('com_content.article', clone $item, false));
                foreach ($fields as $field) {
                    if (($field->type ?? '') !== 'sources') { continue; }
                    // FieldsHelper models may have been booted under another identity earlier in the request.
                    $levels = (new \Joomla\CMS\User\User(0))->getAuthorisedViewLevels();
                    if (!in_array((int) ($field->access ?? 1), $levels, true) || (int) ($field->state ?? 1) !== 1
                        || (!empty($field->group_id) && ((int) ($field->group_state ?? 0) !== 1 || !in_array((int) ($field->group_access ?? 0), $levels, true)))) { continue; }
                    $app = Factory::getApplication();
                    if (method_exists($app, 'getLanguageFilter') && $app->getLanguageFilter()
                        && !in_array($field->language ?? '*', ['*', $app->getLanguage()->getTag()], true)) { continue; }
                    $value = $field->rawvalue ?? '';
                    if (!is_string($value) || trim($value) === '') { continue; }
                    $config = InlineConfig::decode($value);
                    $content .= $this->prepare('{sources:' . json_encode($config, JSON_THROW_ON_ERROR) . '}', $depth + 1);
                    $this->bytes($content);
                }
            }
        }
        $summary = $reason === null ? InlineTokens::replace((string) ($item->introtext ?? ''), static fn() => '') : '';
        $summary = trim(preg_replace('/\s+/u', ' ', strip_tags(EmailHtml::clean($summary, $this->root))) ?: '');
        $summary = \Joomla\String\StringHelper::substr($summary, 0, 240);
        return ['id' => (int) $item->id, 'title' => (string) $item->title, 'url' => $url,
            'image' => EmailHtml::url($image, $this->root, true), 'summary' => $summary, 'content' => $content,
            'header_only' => $reason !== null, 'fallback_reason' => $reason, 'depth' => $depth,
            'read_more_text' => $reason !== null ? Text::_('PLG_CONTENT_SOURCES_EMAIL_READ_MORE') : ''];
    }

    private function supportedContent(string $html, int $depth): string
    {
        // Protect complete valid Sources syntax while sanitising the article fragment.
        $replacements = []; $prefix = 'SOURCES_EMAIL_' . hash('sha256', $html) . '_';
        $safe = InlineTokens::replace($html, static function (array $config) use (&$replacements, $prefix): string {
            $key = $prefix . count($replacements) . '_END';
            $replacements[$key] = '{sources:' . json_encode($config, JSON_THROW_ON_ERROR) . '}'; return $key;
        });
        if (EmailHtml::hasSourcesSyntax($safe)) { throw new \RuntimeException('sources.email.unresolved'); }
        $safe = EmailHtml::clean($safe, $this->root);
        return $this->prepare(strtr($safe, $replacements), $depth);
    }

    private function layout(string $name, array $data): string
    {
        $layout = new FileLayout($name, null, ['component' => 'com_content', 'client' => 0]);
        $paths = $this->layoutPaths ?? $this->overridePaths ?? $layout->getDefaultIncludePaths();
        if ($this->layoutPaths === null && $this->overridePaths === null && !Factory::getApplication()->isClient('site')) {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $template = (string) $db->setQuery($db->getQuery(true)->select('template')->from('#__template_styles')->where('client_id = 0')->where('home != ' . $db->quote('0'))->order('id ASC'), 0, 1)->loadResult();
            if ($template !== '' && basename($template) === $template) {
                array_unshift($paths, JPATH_SITE . '/templates/' . $template . '/html/layouts/com_content', JPATH_SITE . '/templates/' . $template . '/html/layouts');
            }
        }
        $this->layoutPaths = $paths;
        $layout->setIncludePaths([...$paths, $this->layoutPath]);
        $result = $layout->render($data); $this->bytes($result); return $result;
    }

    private function consume(string $key): void
    {
        $value = $this->document->get('usage.' . $key) + 1;
        $this->document->set('usage.' . $key, $value);
    }

    private function bytes(string $html): void
    {
        if (strlen($html) > $this->limits['maxBytes'] || !preg_match('//u', $html)) { throw new \RuntimeException('sources.email.bytes'); }
    }
}
