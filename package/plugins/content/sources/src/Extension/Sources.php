<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.sources
 */

namespace SuperSoft\Plugin\Content\Sources\Extension;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;

final class Sources extends CMSPlugin
{
    protected $autoloadLanguage = true;

    public function onContentPrepare($context, &$article, &$params, $page = 0): void
    {
        if (!is_string($context) || !$this->isSupportedContentContext($context)) {
            return;
        }

        if (!is_object($article) || !property_exists($article, 'text') || !is_string($article->text)) {
            return;
        }

        $subformFieldName = trim((string) $this->params->get('subform_field_name', 'blocks'));
        $tokenName        = trim((string) $this->params->get('token_name', 'sources'));

        if ($subformFieldName === '' || $tokenName === '') {
            return;
        }

        $text    = $article->text;
        $pattern = $this->buildTokenPattern($tokenName);

        if (!$this->isFullArticleContext($context)) {
            if (preg_match($pattern, $text)) {
                $article->text = $this->replaceListViewTokens($text, $pattern);
            }

            return;
        }

        $sourcesField = $this->findSubformField($article, $subformFieldName);

        if ($sourcesField === null) {
            return;
        }

        $sourcesRows = $this->extractRows($sourcesField);

        if ($sourcesRows === []) {
            return;
        }

        $totalBlocks = count($sourcesRows);

        if (!preg_match($pattern, $text)) {
            if ((bool) $this->params->get('append_if_missing', 1)) {
                $article->text = $text . $this->renderByClient($article, $sourcesField, $sourcesRows, range(0, $totalBlocks - 1), 'all');
            }

            return;
        }

        $renderedIndices = [];

        $article->text = (string) preg_replace_callback(
            $pattern,
            function (array $matches) use (&$renderedIndices, $article, $sourcesField, $sourcesRows, $totalBlocks): string {
                $rawArg = '';

                foreach (array_slice($matches, 1) as $match) {
                    if (isset($match) && is_string($match) && $match !== '' && $match !== '"' && $match !== "'") {
                        $rawArg = $match;
                        break;
                    }
                }

                $arg = strtolower(trim((string) $rawArg));

                if ($arg === '') {
                    $indices = range(0, $totalBlocks - 1);
                    $mode    = 'all';
                } elseif ($arg === 'rest') {
                    $indices = [];

                    for ($i = 0; $i < $totalBlocks; $i++) {
                        if (!in_array($i, $renderedIndices, true)) {
                            $indices[] = $i;
                        }
                    }

                    $mode = 'rest';
                } elseif (ctype_digit($arg)) {
                    $index   = (int) $arg - 1;
                    $indices = ($index >= 0 && $index < $totalBlocks) ? [$index] : [];
                    $mode    = 'single';
                } else {
                    return '';
                }

                if ($indices === []) {
                    return '';
                }

                foreach ($indices as $idx) {
                    if (!in_array($idx, $renderedIndices, true)) {
                        $renderedIndices[] = $idx;
                    }
                }

                return $this->renderByClient($article, $sourcesField, $sourcesRows, $indices, $mode);
            },
            $text
        );
    }

    /**
     * Frontend AJAX endpoint for rendering sources preview.
     * Called via: /index.php?option=com_ajax&plugin=sources&group=content&format=raw&article_id=X
     */
    public function onAjaxSources()
    {
        $app = Factory::getApplication();

        if (!$app->isClient('site')) {
            return '';
        }

        $input     = $app->getInput();
        $articleId = $input->getInt('article_id', 0);

        if ($articleId <= 0) {
            return '';
        }

        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' = ' . $articleId);

        $db->setQuery($query);
        $article = $db->loadObject();

        if (!$article) {
            return '';
        }

        $article->jcfields = FieldsHelper::getFields('com_content.article', $article, true);

        $fieldName    = trim((string) $this->params->get('subform_field_name', 'blocks')) ?: 'blocks';
        $sourcesField = $this->findSubformField($article, $fieldName);

        if (!$sourcesField) {
            return '';
        }

        $rows = $this->extractRows($sourcesField);

        if (!$rows) {
            return '';
        }

        $indicesParam = $input->getString('indices', '');
        $mode         = $input->getCmd('mode', 'all');
        $indices      = [];

        if ($indicesParam !== '') {
            foreach (explode(',', $indicesParam) as $i) {
                $idx = (int) trim($i);

                if ($idx >= 0 && $idx < count($rows)) {
                    $indices[] = $idx;
                }
            }
        }

        if (!$indices) {
            $indices = range(0, count($rows) - 1);
        }

        $html = $this->renderFrontend($article, $sourcesField, $rows, $indices, $mode);

        $app->setHeader('Content-Type', 'text/html; charset=utf-8');
        echo $html;
        $app->close();
    }

    private function buildTokenPattern(string $tokenName): string
    {
        $token = preg_quote($tokenName, '/');
        $inner = '\{\s*' . $token . '\s*(?::\s*(rest|[1-9]\d*)\s*)?\}';
        $dataToken = '<span\b(?=[^>]*\bsources-editor-token\b)(?=[^>]*\bdata-sources-token\s*=\s*(["\'])\{\s*' . $token . '\s*(?::\s*(rest|[1-9]\d*)\s*)?\}\1)[^>]*>.*?<\/span>';
        $wrappedToken = '<span\b(?=[^>]*\bsources-editor-token\b)[^>]*>\s*' . $inner . '\s*<\/span>';

        return '/(?:' . $dataToken . '|' . $wrappedToken . '|' . $inner . ')/is';
    }

    private function isSupportedContentContext(string $context): bool
    {
        return strpos($context, 'com_content.article') !== false
            || strpos($context, 'com_content.category') !== false
            || strpos($context, 'com_content.featured') !== false;
    }

    private function isFullArticleContext(string $context): bool
    {
        return strpos($context, 'com_content.article') !== false;
    }

    private function replaceListViewTokens(string $text, string $pattern): string
    {
        $mode = (string) $this->params->get('list_token_mode', 'hide');

        if ($mode === 'placeholder') {
            $label = '[' . htmlspecialchars(Text::_('PLG_CONTENT_SOURCES_LIST_TOKEN_PLACEHOLDER'), ENT_QUOTES, 'UTF-8') . ']';
            $style = 'display:inline-flex;align-items:center;margin:0 .12rem;padding:.16rem .45rem;border:1px solid #8fb3d9;border-radius:.3rem;background:#eef6ff;color:#1f4e79;font:600 .9em/1.3 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Arial,sans-serif;white-space:nowrap;';
            $placeholder = '<span class="sources-list-placeholder" style="' . $style . '">' . $label . '</span>';

            return (string) preg_replace($pattern, $placeholder, $text);
        }

        return (string) preg_replace($pattern, '', $text);
    }

    private function findSubformField(object $article, string $fieldName): ?object
    {
        if (!property_exists($article, 'jcfields') || !is_array($article->jcfields)) {
            return null;
        }

        foreach ($article->jcfields as $field) {
            if (is_object($field) && isset($field->name) && (string) $field->name === $fieldName) {
                return $field;
            }
        }

        return null;
    }

    private function extractRows(object $field): array
    {
        if (!property_exists($field, 'subform_rows') || !is_array($field->subform_rows)) {
            return [];
        }

        return $field->subform_rows;
    }

    private function renderByClient(object $article, object $field, array $rows, array $indices, string $mode): string
    {
        $app = Factory::getApplication();

        if ($app->isClient('site')) {
            return $this->renderFrontend($article, $field, $rows, $indices, $mode);
        }

        if ($app->isClient('administrator')) {
            return $this->renderAdministrator($article, $rows, $indices, $mode);
        }

        return '';
    }

    private function renderFrontend(object $article, object $field, array $rows, array $indices, string $mode): string
    {
        $basePath  = trim((string) $this->params->get('templates_base_path', ''));
        $entryFile = trim((string) $this->params->get('templates_entry_file', 'sources.php'));

        if ($entryFile === '') {
            $entryFile = 'sources.php';
        }

        if ($basePath === '') {
            $app = Factory::getApplication();
            
            if ($app->isClient('administrator')) {
                $basePath = JPATH_SITE . '/templates/' . $this->getSiteTemplateName() . '/html/com_content/article';
            } else {
                $basePath = JPATH_THEMES . '/' . $app->getTemplate() . '/html/com_content/article';
            }
        }

        $templatePath = rtrim($basePath, '/\\') . '/' . ltrim($entryFile, '/\\');

        if (!is_file($templatePath) || !is_readable($templatePath)) {
            return '';
        }

        $sourcesArticle        = $article;
        $sourcesField          = $field;
        $sourcesRows           = $rows;
        $sources_block_indices = array_values(array_unique(array_map('intval', $indices)));
        $sources_render_mode   = $mode;
        $sources_heading_tag   = trim((string) $this->params->get('heading_tag', 'h2'));
        $sources_heading_class = trim((string) $this->params->get('heading_class', ''));

        ob_start();
        include $templatePath;

        return (string) ob_get_clean();
    }

    private function getSiteTemplateName(): string
    {
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName('template'))
                ->from($db->quoteName('#__template_styles'))
                ->where($db->quoteName('client_id') . ' = 0')
                ->where($db->quoteName('home') . ' != ' . $db->quote('0'))
                ->order($db->quoteName('home') . ' ASC');

            $db->setQuery($query, 0, 1);
            $template = trim((string) $db->loadResult());

            if ($template !== '') {
                return $template;
            }
        } catch (\Throwable $e) {
        }

        return 'cassiopeia';
    }

    private function renderAdministrator(object $article, array $sourcesRows, array $indices, string $mode): string
    {
        $previewMode = (string) $this->params->get('admin_preview_mode', 'list_manual');

        if ($previewMode === 'none') {
            return '';
        }

        if ($previewMode === 'render') {
            $sourcesField = $this->findSubformField($article, trim((string) $this->params->get('subform_field_name', 'blocks')));

            if ($sourcesField === null) {
                return '';
            }

            return $this->renderFrontend($article, $sourcesField, $sourcesRows, $indices, $mode);
        }

        if ($previewMode === 'placeholder') {
            return '<span class="sources-placeholder" contenteditable="false">' . htmlspecialchars(Text::sprintf('PLG_CONTENT_SOURCES_PREVIEW_PLACEHOLDER', $this->formatBlockNumbers($indices), strtoupper($mode)), ENT_QUOTES, 'UTF-8') . '</span>';
        }

        return $this->buildListPreview($sourcesRows, $indices, $previewMode === 'list_full');
    }

    private function buildListPreview(array $sourcesRows, array $indices, bool $full): string
    {
        $maxItems = max(1, (int) $this->params->get('admin_preview_max_items', 20));
        $valid    = [];

        foreach (array_values(array_unique(array_map('intval', $indices))) as $idx) {
            if ($idx >= 0 && $idx < count($sourcesRows)) {
                $valid[] = $idx;
            }
        }

        if ($valid === []) {
            return '';
        }

        $allManualIds = [];
        $manualByBlock = [];

        foreach ($valid as $idx) {
            $ids               = $this->extractIdList($sourcesRows[$idx] ?? [], $this->fieldAliases('manual'));
            $manualByBlock[$idx] = $ids;

            foreach ($ids as $id) {
                $allManualIds[$id] = $id;
            }
        }

        $manualTitleMap = $allManualIds ? $this->fetchTitlesByIds(array_values($allManualIds)) : [];

        $html = '<div class="sources-preview" contenteditable="false">';
        $html .= '<strong>' . htmlspecialchars(Text::_('PLG_CONTENT_SOURCES_PREVIEW_TITLE'), ENT_QUOTES, 'UTF-8') . '</strong>';

        foreach ($valid as $idx) {
            $row      = $sourcesRows[$idx] ?? [];
            $title    = $this->extractTextValue($row, $this->fieldAliases('title'));
            $tags     = $this->extractIdList($row, $this->fieldAliases('tags'));
            $cats     = $this->extractIdList($row, $this->fieldAliases('categories'));
            $limit    = $this->extractPositiveInt($row, $this->fieldAliases('limit'), $maxItems);
            $manualIds = $manualByBlock[$idx] ?? [];

            $html .= '<div class="sources-preview-block">';
            $html .= '<div><strong>' . htmlspecialchars(Text::sprintf('PLG_CONTENT_SOURCES_PREVIEW_BLOCK_HEADER', $idx + 1, $title !== '' ? $title : Text::_('PLG_CONTENT_SOURCES_PREVIEW_NO_TITLE')), ENT_QUOTES, 'UTF-8') . '</strong></div>';
            $html .= '<div>' . htmlspecialchars(Text::_('PLG_CONTENT_SOURCES_PREVIEW_MANUAL'), ENT_QUOTES, 'UTF-8') . '</div>';

            $manualTitles = [];

            foreach ($manualIds as $id) {
                if (isset($manualTitleMap[$id])) {
                    $manualTitles[] = $manualTitleMap[$id];
                }
            }

            if ($full) {
                $resolvedTitles = $this->resolveFullPreviewTitles($manualIds, $tags, $cats, $limit);
                $html .= '<div>' . htmlspecialchars(Text::_('PLG_CONTENT_SOURCES_PREVIEW_FULL'), ENT_QUOTES, 'UTF-8') . '</div>';
                $html .= $this->renderTitlesSection($resolvedTitles, $maxItems);
            } else {
                $html .= $this->renderTitlesSection($manualTitles, $maxItems);
                $html .= '<div>' . htmlspecialchars(Text::_('PLG_CONTENT_SOURCES_PREVIEW_AUTO'), ENT_QUOTES, 'UTF-8') . '</div>';

                if ($tags === []) {
                    $html .= '<div>' . htmlspecialchars(Text::_('PLG_CONTENT_SOURCES_PREVIEW_AUTO_DISABLED'), ENT_QUOTES, 'UTF-8') . '</div>';
                } else {
                    $html .= '<div>' . htmlspecialchars(Text::sprintf('PLG_CONTENT_SOURCES_PREVIEW_AUTO_SUMMARY', implode(', ', $tags), $cats ? implode(', ', $cats) : Text::_('PLG_CONTENT_SOURCES_PREVIEW_ANY'), $limit), ENT_QUOTES, 'UTF-8') . '</div>';
                }
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    private function renderTitlesSection(array $titles, int $maxItems): string
    {
        if ($titles === []) {
            return '<div>' . htmlspecialchars(Text::_('PLG_CONTENT_SOURCES_PREVIEW_NONE'), ENT_QUOTES, 'UTF-8') . '</div>';
        }

        $slice = array_slice($titles, 0, $maxItems);
        $more  = count($titles) - count($slice);

        $html = '<ul>';

        foreach ($slice as $title) {
            $html .= '<li>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</li>';
        }

        $html .= '</ul>';

        if ($more > 0) {
            $html .= '<div>' . htmlspecialchars(Text::sprintf('PLG_CONTENT_SOURCES_PREVIEW_MORE', $more), ENT_QUOTES, 'UTF-8') . '</div>';
        }

        return $html;
    }

    private function resolveFullPreviewTitles(array $manualIds, array $tags, array $categories, int $limit): array
    {
        $titlesById = [];

        if ($manualIds) {
            $manualMap = $this->fetchTitlesByIds($manualIds);

            foreach ($manualIds as $id) {
                if (isset($manualMap[$id])) {
                    $titlesById[$id] = $manualMap[$id];
                }
            }
        }

        if ($tags) {
            $autoIds = $this->findAutoArticleIds($tags, $categories, $limit);
            $autoMap = $autoIds ? $this->fetchTitlesByIds($autoIds) : [];

            foreach ($autoIds as $id) {
                if (!isset($titlesById[$id]) && isset($autoMap[$id])) {
                    $titlesById[$id] = $autoMap[$id];
                }
            }
        }

        $titles = array_values($titlesById);

        return $limit > 0 ? array_slice($titles, 0, $limit) : $titles;
    }

    private function findAutoArticleIds(array $tags, array $categories, int $limit): array
    {
        if ($tags === []) {
            return [];
        }

        $tags       = array_values(array_unique(array_filter(array_map('intval', $tags), static function ($id) { return $id > 0; })));
        $categories = array_values(array_unique(array_filter(array_map('intval', $categories), static function ($id) { return $id > 0; })));

        if ($tags === []) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('c.id')
            ->from($db->quoteName('#__content', 'c'))
            ->join('INNER', $db->quoteName('#__contentitem_tag_map', 'm') . ' ON m.content_item_id = c.id')
            ->where('c.state = 1')
            ->where('m.type_alias = ' . $db->quote('com_content.article'))
            ->where('m.tag_id IN (' . implode(',', $tags) . ')');

        if ($categories) {
            $query->where('c.catid IN (' . implode(',', $categories) . ')');
        }

        $query
            ->group('c.id')
            ->having('COUNT(DISTINCT m.tag_id) >= ' . count($tags))
            ->order('c.publish_up DESC, c.id DESC');

        $limit = max(1, $limit);
        $db->setQuery($query, 0, $limit * 2);

        $ids = [];

        foreach ((array) $db->loadColumn() as $id) {
            $id = (int) $id;

            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_slice(array_values($ids), 0, $limit);
    }

    private function fetchTitlesByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) { return $id > 0; })));

        if ($ids === []) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'title']))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' IN (' . implode(',', $ids) . ')');

        $db->setQuery($query);

        $map = [];

        foreach ((array) $db->loadAssocList() as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;

            if ($id > 0) {
                $map[$id] = (string) ($row['title'] ?? '');
            }
        }

        return $map;
    }

    private function fieldAliases(string $type): array
    {
        $aliases = [
            'title'      => ['block-title', 'title', 'list-title', 'list_title', 'sources-title', 'sources_title', 'source-title', 'source_title'],
            'manual'     => ['block-manual-articles', 'block-selected-sources', 'block-selected_sources', 'block-selected-articles', 'block-selected_articles', 'block-source-articles', 'block-source_articles', 'block-sources-articles', 'block-sources_articles', 'block-articles', 'block-sources', 'block-items', 'manual-articles', 'manual_articles', 'manualarticles', 'manual-sources', 'manual_sources', 'manualsources', 'manual', 'selected-sources', 'selected_sources', 'selectedsources', 'selected-source', 'selected_source', 'selected-articles', 'selected_articles', 'selectedarticles', 'selected-article', 'selected_article', 'selected-items', 'selected_items', 'selected', 'sources', 'source-articles', 'source_articles', 'sources-articles', 'sources_articles', 'source', 'articles', 'article', 'article-ids', 'article_ids', 'articleids', 'items', 'content-items', 'content_items'],
            'tags'       => ['block-tags', 'tags', 'source-tags', 'source_tags', 'sources-tags', 'sources_tags'],
            'categories' => ['block-categories', 'categories', 'tag-categories', 'tag_categories', 'source-categories', 'source_categories', 'sources-categories', 'sources_categories'],
            'limit'      => ['block-limit', 'limit', 'sources-limit', 'sources_limit', 'source-limit', 'source_limit'],
        ];

        return $aliases[$type] ?? [];
    }

    private function extractTextValue($row, $keys): string
    {
        $value = $this->extractRowValue($row, $keys);

        if (is_array($value)) {
            $value = reset($value);
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function extractIdList($row, $keys): array
    {
        $value = $this->extractRowValue($row, $keys);

        if ($value === null || $value === '') {
            return [];
        }

        $ids = [];
        $this->collectIdsFromValue($value, $ids);

        return array_values($ids);
    }

    private function collectIdsFromValue($value, array &$ids): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (is_object($value)) {
            if (property_exists($value, 'rawvalue')) {
                $this->collectIdsFromValue($value->rawvalue, $ids);
                return;
            }

            if (property_exists($value, 'value')) {
                $this->collectIdsFromValue($value->value, $ids);
                return;
            }

            if (property_exists($value, 'id')) {
                $this->collectIdsFromValue($value->id, $ids);
                return;
            }

            $value = get_object_vars($value);
        }

        if (is_array($value)) {
            if (array_key_exists('rawvalue', $value)) {
                $this->collectIdsFromValue($value['rawvalue'], $ids);
                return;
            }

            if (array_key_exists('value', $value)) {
                $this->collectIdsFromValue($value['value'], $ids);
                return;
            }

            if (array_key_exists('id', $value)) {
                $this->collectIdsFromValue($value['id'], $ids);
                return;
            }

            foreach ($value as $item) {
                $this->collectIdsFromValue($item, $ids);
            }

            return;
        }

        if (!is_scalar($value)) {
            return;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return;
        }

        if ($text[0] === '[' || $text[0] === '{') {
            $decoded = json_decode($text, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->collectIdsFromValue($decoded, $ids);
                return;
            }
        }

        if (strpos($text, ',') !== false) {
            foreach (explode(',', $text) as $part) {
                $this->collectIdsFromValue($part, $ids);
            }

            return;
        }

        if (preg_match('/^\d+$/', $text)) {
            $id = (int) $text;

            if ($id > 0) {
                $ids[$id] = $id;
            }

            return;
        }

        if (preg_match('/\[(\d+)\]\s*$/', $text, $match)) {
            $id = (int) $match[1];

            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
    }

    private function extractRowValue($row, $keys)
    {
        if (!is_array($row)) {
            return null;
        }

        foreach ((array) $keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }

            $value = $row[$key];

            return is_object($value) && property_exists($value, 'rawvalue') ? $value->rawvalue : $value;
        }

        return null;
    }

    private function extractPositiveInt($row, $keys, int $default): int
    {
        $value = $this->extractTextValue($row, $keys);

        if (!preg_match('/^\d+$/', $value)) {
            return max(1, $default);
        }

        $int = (int) $value;

        return $int > 0 ? $int : max(1, $default);
    }

    private function formatBlockNumbers(array $indices): string
    {
        return implode(', ', array_map(static function ($idx): int {
            return (int) $idx + 1;
        }, $indices));
    }
}

