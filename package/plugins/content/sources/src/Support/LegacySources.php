<?php
namespace SuperSoft\Plugin\Content\Sources\Support;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;

/** Temporary, read-only bridge to the original template rendering contract. */
final class LegacySources
{
    private static array $active = [];

    public static function prepare(string $context, object $article, $params): void
    {
        $key = (int) ($article->id ?? 0);
        if (isset(self::$active[$key]) || count(self::$active) >= 3) { return; }
        self::$active[$key] = true;
        try { self::prepareLegacy($context, $article, $params); }
        finally { unset(self::$active[$key]); }
    }

    private static function prepareLegacy(string $context, object $article, $params): void
    {
        if (!(bool) $params->get('legacy_enabled', 1)) { return; }
        // Even malformed/future inline configurations must never be rewritten as legacy tokens.
        if (strpos($article->text, '{sources:') !== false
            && preg_match('/\{sources:\s*\{/', $article->text)) { return; }

        $name = trim((string) $params->get('token_name', 'sources')) ?: 'sources';
        $pattern = '/\{\s*' . preg_quote($name, '/') . '\s*(?::\s*(rest|[1-9]\d*)\s*)?\}/i';
        if ($context !== 'com_content.article') {
            $replacement = $params->get('list_token_mode', 'hide') === 'placeholder'
                ? '<span class="sources-list-placeholder">[' . htmlspecialchars(Text::_('PLG_CONTENT_SOURCES_LIST_TOKEN_PLACEHOLDER'), ENT_QUOTES, 'UTF-8') . ']</span>' : '';
            $article->text = preg_replace_callback($pattern, static fn () => $replacement, $article->text);
            return;
        }
        if (empty($article->id)) { return; }
        if (!preg_match($pattern, $article->text) && !(bool) $params->get('append_if_missing', 1)) { return; }

        $app = Factory::getApplication();
        $base = trim((string) $params->get('templates_base_path', ''));
        if ($base === '') { $base = JPATH_SITE . '/templates/' . $app->getTemplate() . '/html/com_content/article'; }
        $entry = trim((string) $params->get('templates_entry_file', 'sources.php')) ?: 'sources.php';
        $path = rtrim($base, '/\\') . '/' . ltrim($entry, '/\\');
        if (!is_file($path) || !is_readable($path)) { return; }
        $fields = RequestCache::remember(RequestCache::key('legacy-fields', [
            $article->id, $article->catid ?? null, $article->fieldscatid ?? null,
            $article->assigned_cat_ids ?? null, $article->language ?? null,
        ]), static fn() => FieldsHelper::getFields('com_content.article', $article, false));
        $fieldName = trim((string) $params->get('subform_field_name', 'blocks')) ?: 'blocks';
        $sourcesField = null;
        foreach ($fields as $field) {
            // A migrated article uses its modern field exclusively, without deleting old values.
            if (($field->type ?? '') === 'sources' && self::hasValue($field->rawvalue ?? '')) { return; }
            if (($field->name ?? '') === $fieldName) { $sourcesField = $field; }
        }
        if ($sourcesField === null || (!self::hasValue($sourcesField->rawvalue ?? '') && empty($sourcesField->subform_rows))) { return; }
        // Only genuine legacy rendering needs full subform preparation. Other fields/plugins retain their lifecycle.
        $fields = SourceFieldData::withoutRendering(static fn() => FieldsHelper::getFields('com_content.article', $article, true));
        $sourcesField = null;
        foreach ($fields as $field) { if (($field->name ?? '') === $fieldName) { $sourcesField = $field; break; } }
        $rows = $sourcesField->subform_rows ?? [];
        if (!is_array($rows) || !$rows) { return; }
        $sourcesArticle = clone $article;
        $sourcesArticle->jcfields = $fields;

        $render = static function (array $indices, string $mode) use ($sourcesArticle, $sourcesField, $rows, $params, $path): string {
            $sourcesRows = $rows;
            $sources_block_indices = $indices;
            $sources_render_mode = $mode;
            $sources_heading_tag = trim((string) $params->get('heading_tag', 'h2'));
            $sources_heading_class = trim((string) $params->get('heading_class', ''));
            $level = ob_get_level();
            ob_start();
            try { include $path; return (string) ob_get_clean(); }
            finally { while (ob_get_level() > $level) { ob_end_clean(); } }
        };
        $all = range(0, count($rows) - 1);
        if (!preg_match($pattern, $article->text)) {
            if ((bool) $params->get('append_if_missing', 1)) { $article->text .= $render($all, 'all'); }
            return;
        }
        $used = [];
        $article->text = preg_replace_callback($pattern, static function ($match) use (&$used, $all, $render): string {
            $arg = strtolower($match[1] ?? '');
            $indices = $arg === '' ? $all : ($arg === 'rest' ? array_values(array_diff($all, $used)) : array_values(array_intersect($all, [(int) $arg - 1])));
            $used = array_unique(array_merge($used, $indices));
            return $indices ? $render($indices, $arg === '' ? 'all' : ($arg === 'rest' ? 'rest' : 'single')) : '';
        }, $article->text);
    }

    private static function hasValue($value): bool
    {
        if (is_array($value)) {
            foreach ($value as $part) { if (self::hasValue($part)) { return true; } }
            return false;
        }
        return is_scalar($value) && trim((string) $value) !== '';
    }
}
