<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.sources
 */

namespace SuperSoft\Plugin\Content\Sources\Extension;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;

final class Sources extends CMSPlugin
{
    protected $autoloadLanguage = true;

    public function onContentPrepare($context, &$article, &$params, $page = 0): void
    {
        if (!is_string($context) || strpos($context, 'com_content.article') === false) {
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

        $sourcesField = $this->findSubformField($article, $subformFieldName);

        if ($sourcesField === null) {
            return;
        }

        $sourcesRows = $this->extractRows($sourcesField);

        if ($sourcesRows === []) {
            return;
        }

        $text       = $article->text;
        $totalBlocks = count($sourcesRows);
        $pattern    = $this->buildTokenPattern($tokenName);

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
                $arg = isset($matches[1]) ? strtolower(trim((string) $matches[1])) : '';

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

    private function buildTokenPattern(string $tokenName): string
    {
        return '/\{\s*' . preg_quote($tokenName, '/') . '\s*(?::\s*(rest|[1-9]\d*)\s*)?\}/i';
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
            $basePath = JPATH_THEMES . '/' . Factory::getApplication()->getTemplate() . '/html/com_content/article';
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

        ob_start();
        include $templatePath;

        return (string) ob_get_clean();
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
            $ids               = $this->extractIdList($sourcesRows[$idx] ?? [], 'block-manual-articles');
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
            $title    = $this->extractTextValue($row, 'block-title');
            $tags     = $this->extractIdList($row, 'block-tags');
            $cats     = $this->extractIdList($row, 'block-categories');
            $limit    = $this->extractPositiveInt($row, 'block-limit', $maxItems);
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

    private function extractTextValue($row, string $key): string
    {
        if (!is_array($row) || !isset($row[$key]) || !is_object($row[$key]) || !property_exists($row[$key], 'rawvalue')) {
            return '';
        }

        $value = $row[$key]->rawvalue;

        if (is_array($value)) {
            $value = reset($value);
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function extractIdList($row, string $key): array
    {
        if (!is_array($row) || !isset($row[$key]) || !is_object($row[$key]) || !property_exists($row[$key], 'rawvalue')) {
            return [];
        }

        $value = $row[$key]->rawvalue;

        if ($value === null || $value === '') {
            return [];
        }

        if (!is_array($value)) {
            $value = [$value];
        }

        $ids = [];

        foreach ($value as $item) {
            if (is_scalar($item) && preg_match('/^\d+$/', (string) $item)) {
                $id = (int) $item;

                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        }

        return array_values($ids);
    }

    private function extractPositiveInt($row, string $key, int $default): int
    {
        $value = $this->extractTextValue($row, $key);

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

