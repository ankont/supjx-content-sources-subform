<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Editors-xtd.sourcesbutton
 */

namespace SuperSoft\Plugin\EditorsXtd\SourcesButton\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Editor\Button\Button;
use Joomla\CMS\Event\Editor\EditorButtonsSetupEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

final class SourcesButton extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;
    public function __construct(DispatcherInterface $dispatcher, array $config = [])
    {
        parent::__construct($dispatcher, $config);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'onEditorButtonsSetup' => 'onEditorButtonsSetup',
            'onAjaxSourcesbutton'  => 'onAjaxSourcesbutton',
        ];
    }

    public function onEditorButtonsSetup(EditorButtonsSetupEvent $event): void
    {
        $app = Factory::getApplication();

        if (!$app->isClient('administrator') && !$app->isClient('site')) {
            return;
        }

        $this->loadAssets();

        $button = new Button('sourcesbutton', [
            'text'    => Text::_('PLG_EDITORSXTD_SOURCESBUTTON_LABEL'),
            'icon'    => 'list',
            'iconSVG' => '<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' width=\'16\' height=\'16\' aria-hidden=\'true\' focusable=\'false\'><path d=\'M5 4h10a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3V4z\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\'/><path d=\'M8 8h7M8 12h7M8 16h5\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\'/></svg>',
            'action'  => 'sourcesbutton:insert',
        ]);

        $event->getButtonsRegistry()->add($button);
    }

    public function onAjaxSourcesbutton(): array
    {
        $app = Factory::getApplication();
        $this->loadSourcesLanguages();

        if (!Session::checkToken('request')) {
            return $this->sendPreviewResponse(false, $this->noticeHtml(Text::_('JINVALID_TOKEN')), 'invalid_token');
        }

        $input = $app->getInput();
        $task  = (string) $input->getCmd('task', 'preview');

        if ($task !== 'preview') {
            return $this->sendPreviewResponse(false, $this->noticeHtml('Invalid Sources preview task.'), 'invalid_task');
        }

        $articleId = (int) $input->getInt('article_id', 0);
        $token     = trim((string) $input->getString('token', ''));
        $mode      = (string) $input->getCmd('mode', 'placeholder');
        $previousTokens = json_decode((string) $input->getString('previous_tokens', '[]'), true);

        if (!is_array($previousTokens)) {
            $previousTokens = [];
        }

        if (!in_array($mode, ['placeholder', 'list_manual', 'list_full', 'render'], true)) {
            $mode = 'placeholder';
        }

        if ($articleId <= 0) {
            return $this->sendPreviewResponse(false, $this->noticeHtml(Text::_('PLG_EDITORSXTD_SOURCESBUTTON_SAVE_TO_PREVIEW')), 'preview_unavailable');
        }

        $article = $this->loadArticle($articleId);

        if ($article === null) {
            return $this->sendPreviewResponse(false, $this->noticeHtml(Text::_('PLG_EDITORSXTD_SOURCESBUTTON_SAVE_TO_PREVIEW')), 'preview_unavailable');
        }

        $contentParams = $this->getContentSourcesParams();
        $tokenName     = trim((string) $contentParams->get('token_name', 'sources')) ?: 'sources';
        $fieldName     = trim((string) $contentParams->get('subform_field_name', 'blocks')) ?: 'blocks';
        $fields        = $this->loadArticleFields($article);
        $sourcesField  = $this->findField($fields, $fieldName);
        $rows          = $sourcesField ? $this->extractRows($sourcesField) : [];

        if ($sourcesField === null) {
            return $this->sendPreviewResponse(false, $this->noticeHtml(Text::_('PLG_EDITORSXTD_SOURCESBUTTON_SAVE_TO_PREVIEW')), 'sources_field_not_found:' . $fieldName);
        }

        if ($rows === []) {
            return $this->sendPreviewResponse(false, $this->noticeHtml(Text::_('PLG_EDITORSXTD_SOURCESBUTTON_SAVE_TO_PREVIEW')), 'sources_rows_empty:' . $fieldName);
        }

        $renderedIndices = $this->collectRenderedIndices($previousTokens, $tokenName, count($rows));
        $selection       = $this->parseToken($token, $tokenName, count($rows), $renderedIndices);

        if ($selection['indices'] === []) {
            return $this->sendPreviewResponse(true, '', '');
        }

        if ($mode === 'render') {
            $html = $this->renderFrontend($article, $sourcesField, $rows, $selection['indices'], $selection['mode'], $contentParams);

            return $this->sendPreviewResponse($html !== '', $html !== '' ? $html : $this->noticeHtml(Text::_('PLG_EDITORSXTD_SOURCESBUTTON_SAVE_TO_PREVIEW')), $html !== '' ? '' : 'render_template_unavailable');
        }

        if ($mode === 'list_full') {
            return $this->sendPreviewResponse(true, $this->buildListPreview($rows, $selection['indices'], true));
        }

        if ($mode === 'list_manual') {
            return $this->sendPreviewResponse(true, $this->buildListPreview($rows, $selection['indices'], false));
        }

        return $this->sendPreviewResponse(true, $this->placeholderHtml($selection));
    }

    private function loadAssets(): void
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        
        $this->loadSourcesLanguages();

        $document      = Factory::getApplication()->getDocument();
        $wa            = $document->getWebAssetManager();
        $media         = rtrim(Uri::root(true), '/') . '/media/plg_editors-xtd_sourcesbutton';
        $scriptVersion = $this->getMediaVersion('js/sourcesbutton.js');
        $contentParams = $this->getContentSourcesParams();
        $input         = Factory::getApplication()->getInput();
        $articleId     = max((int) $input->getInt('id', 0), (int) $input->getInt('a_id', 0));
        $previewMode   = (string) $this->params->get('editor_preview_mode', 'placeholder');
        $badgeDisplay  = (string) $this->params->get('badge_display', 'label_token');

        if (!in_array($previewMode, ['none', 'placeholder', 'list_manual', 'list_full', 'render'], true)) {
            $previewMode = 'placeholder';
        }

        if (!in_array($badgeDisplay, ['label_token', 'label_only', 'token_only'], true)) {
            $badgeDisplay = 'label_token';
        }

        $config = [
            'tokenName'       => trim((string) $contentParams->get('token_name', 'sources')) ?: 'sources',
            'articleId'       => $articleId,
            'subformName'     => trim((string) $contentParams->get('subform_field_name', 'blocks')) ?: 'blocks',
            'previewMode'     => $previewMode,
            'previewMaxItems' => max(1, (int) $this->params->get('editor_preview_max_items', 20)),
            'badgeLabel'      => trim((string) $this->params->get('badge_label', 'Sources')) ?: 'Sources',
            'badgeDisplay'    => $badgeDisplay,
            'ajaxUrl'         => 'index.php?option=com_ajax&plugin=sourcesbutton&group=editors-xtd&format=json',
            'fontAwesomeUrl'  => rtrim(Uri::root(true), '/') . '/media/system/css/joomla-fontawesome.min.css',
            'csrfToken'       => Session::getFormToken(),
            'i18n'            => [
                'modalTitle'           => Text::_('PLG_EDITORSXTD_SOURCESBUTTON_MODAL_TITLE'),
                'insert'               => Text::_('PLG_EDITORSXTD_SOURCESBUTTON_INSERT'),
                'cancel'               => Text::_('JCANCEL'),
                'allBlocks'            => Text::_('PLG_EDITORSXTD_SOURCESBUTTON_TOKEN_ALL'),
                'singleBlock'          => Text::_('PLG_EDITORSXTD_SOURCESBUTTON_TOKEN_SINGLE'),
                'remainingBlocks'      => Text::_('PLG_EDITORSXTD_SOURCESBUTTON_TOKEN_REST'),
                'blockNumber'          => Text::_('PLG_EDITORSXTD_SOURCESBUTTON_BLOCK_NUMBER'),
                'saveToPreview'        => Text::_('PLG_EDITORSXTD_SOURCESBUTTON_SAVE_TO_PREVIEW'),
                'serverPreviewPending' => Text::_('PLG_EDITORSXTD_SOURCESBUTTON_SERVER_PREVIEW_PENDING'),
                'previewLoading'       => Text::_('PLG_EDITORSXTD_SOURCESBUTTON_PREVIEW_LOADING'),
                'placeholderAll'       => Text::_('PLG_EDITORSXTD_SOURCESBUTTON_PLACEHOLDER_ALL'),
                'placeholderSingle'    => Text::_('PLG_EDITORSXTD_SOURCESBUTTON_PLACEHOLDER_SINGLE'),
                'placeholderRest'      => Text::_('PLG_EDITORSXTD_SOURCESBUTTON_PLACEHOLDER_REST'),
                'modeSettings'         => Text::_('PLG_EDITORSXTD_SOURCESBUTTON_EDITOR_PREVIEW_MODE_LABEL'),
                'modeNone'             => Text::_('PLG_EDITORSXTD_SOURCESBUTTON_EDITOR_PREVIEW_MODE_NONE'),
                'modePlaceholder'      => Text::_('PLG_EDITORSXTD_SOURCESBUTTON_EDITOR_PREVIEW_MODE_PLACEHOLDER'),
                'modeListManual'       => Text::_('PLG_EDITORSXTD_SOURCESBUTTON_EDITOR_PREVIEW_MODE_LIST_MANUAL'),
                'modeListFull'         => Text::_('PLG_EDITORSXTD_SOURCESBUTTON_EDITOR_PREVIEW_MODE_LIST_FULL'),
                'modeRender'           => Text::_('PLG_EDITORSXTD_SOURCESBUTTON_EDITOR_PREVIEW_MODE_RENDER'),
            ],
        ];

        $wa->useScript('editors');
        $wa->registerStyle(
            'plg.editorsxtd.sourcesbutton',
            'plg_editors-xtd_sourcesbutton/css/sourcesbutton.css',
            [],
            ['version' => 'auto']
        );
        $wa->useStyle('plg.editorsxtd.sourcesbutton');

        if (method_exists($document, 'addCustomTag')) {
            $document->addCustomTag(
                '<script>window.SuperSoftSourcesButtonConfig = '
                . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
                . ';</script>'
            );
            $document->addCustomTag(
                '<script type="module" src="'
                . htmlspecialchars($media . '/js/sourcesbutton.js?v=' . rawurlencode($scriptVersion), ENT_COMPAT, 'UTF-8')
                . '"></script>'
            );
        }

        $loaded = true;
    }

    private function getContentSourcesParams(): Registry
    {
        $plugin = PluginHelper::getPlugin('content', 'sources');

        return new Registry($plugin ? ($plugin->params ?? '') : '');
    }


    private function getMediaVersion(string $relativePath): string
    {
        $path = JPATH_ROOT . '/media/plg_editors-xtd_sourcesbutton/' . ltrim($relativePath, '/');

        if (is_file($path)) {
            return (string) filemtime($path);
        }

        return (string) $this->getManifestVersion();
    }

    private function getManifestVersion(): string
    {
        $manifest = JPATH_PLUGINS . '/editors-xtd/sourcesbutton/sourcesbutton.xml';

        if (is_file($manifest)) {
            try {
                $xml = simplexml_load_file($manifest);

                if ($xml && isset($xml->version)) {
                    return trim((string) $xml->version) ?: '1';
                }
            } catch (\Throwable $e) {
            }
        }

        return '1';
    }
    private function loadSourcesLanguages(): void
    {
        $language = Factory::getLanguage();
        $language->load('plg_editors-xtd_sourcesbutton', JPATH_ADMINISTRATOR)
            || $language->load('plg_editors-xtd_sourcesbutton', JPATH_PLUGINS . '/editors-xtd/sourcesbutton');
        $language->load('plg_content_sources', JPATH_ADMINISTRATOR)
            || $language->load('plg_content_sources', JPATH_PLUGINS . '/content/sources');
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

    private function sendPreviewResponse(bool $ok, string $html, string $message = ''): array
    {
        $response = ['ok' => $ok, 'html' => $html, 'message' => $message];
        $payload  = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $app      = Factory::getApplication();

        if ($payload !== false) {
            $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
            echo $payload;
            $app->close();
        }

        return $response;
    }

    private function loadArticle(int $id): ?object
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' = ' . (int) $id);

        $db->setQuery($query);
        $article = $db->loadObject();

        return is_object($article) ? $article : null;
    }

    private function loadArticleFields(object $article): array
    {
        try {
            return FieldsHelper::getFields('com_content.article', $article, true) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function findField(array $fields, string $name): ?object
    {
        foreach ($fields as $field) {
            if (is_object($field) && isset($field->name) && (string) $field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    private function extractRows(object $field): array
    {
        if (property_exists($field, 'subform_rows') && is_array($field->subform_rows) && $field->subform_rows !== []) {
            return $field->subform_rows;
        }

        foreach (['rawvalue', 'value'] as $property) {
            if (!property_exists($field, $property)) {
                continue;
            }

            $rows = $this->parseRowsValue($field->{$property});

            if ($rows !== []) {
                return $rows;
            }
        }

        return [];
    }

    private function parseRowsValue($raw): array
    {
        if (is_string($raw)) {
            $raw = trim(htmlspecialchars_decode($raw, ENT_QUOTES));

            if ($raw === '') {
                return [];
            }

            $decoded = json_decode($raw, true);

            if (!is_array($decoded)) {
                return [];
            }

            $raw = $decoded;
        }

        if (!is_array($raw)) {
            return [];
        }

        if (isset($raw['rows']) && is_array($raw['rows'])) {
            $raw = $raw['rows'];
        }

        $rows = [];

        foreach ($raw as $row) {
            $normalised = $this->normaliseDecodedRow($row);

            if ($normalised !== []) {
                $rows[] = $normalised;
            }
        }

        return $rows;
    }

    private function normaliseDecodedRow($row): array
    {
        if (is_object($row)) {
            $row = get_object_vars($row);
        }

        if (!is_array($row)) {
            return [];
        }

        if (isset($row['fields']) && is_array($row['fields'])) {
            $row = $row['fields'];
        }

        $normalised = [];

        foreach ($row as $key => $value) {
            if (is_object($value) && property_exists($value, 'rawvalue')) {
                if (!property_exists($value, 'value')) {
                    $value->value = $value->rawvalue;
                }
                $normalised[(string) $key] = $value;
                continue;
            }

            if (is_object($value)) {
                $value = get_object_vars($value);
            }

            if (is_array($value) && array_key_exists('rawvalue', $value)) {
                $normalised[(string) $key] = (object) [
                    'rawvalue' => $value['rawvalue'],
                    'value'    => $value['value'] ?? $value['rawvalue']
                ];
                continue;
            }

            if (is_array($value) && array_key_exists('value', $value)) {
                $normalised[(string) $key] = (object) [
                    'rawvalue' => $value['value'],
                    'value'    => $value['value']
                ];
                continue;
            }

            $normalised[(string) $key] = (object) [
                'rawvalue' => $value,
                'value'    => $value
            ];
        }

        return $normalised;
    }

    private function collectRenderedIndices(array $tokens, string $tokenName, int $total): array
    {
        $renderedIndices = [];

        foreach ($tokens as $token) {
            if (!is_string($token)) {
                continue;
            }

            $selection = $this->parseToken($token, $tokenName, $total, $renderedIndices);

            foreach ($selection['indices'] as $idx) {
                if (!in_array($idx, $renderedIndices, true)) {
                    $renderedIndices[] = $idx;
                }
            }
        }

        return $renderedIndices;
    }

    private function parseToken(string $token, string $tokenName, int $total, array $renderedIndices = []): array
    {
        $pattern = '/^\{\s*' . preg_quote($tokenName, '/') . '\s*(?::\s*(rest|[1-9]\d*)\s*)?\}$/i';

        if (!preg_match($pattern, trim($token), $matches)) {
            return ['mode' => 'all', 'indices' => []];
        }

        $arg = isset($matches[1]) ? strtolower(trim((string) $matches[1])) : '';

        if ($arg === '') {
            return ['mode' => 'all', 'indices' => range(0, max(0, $total - 1))];
        }

        if ($arg === 'rest') {
            $indices = [];

            for ($idx = 0; $idx < $total; $idx++) {
                if (!in_array($idx, $renderedIndices, true)) {
                    $indices[] = $idx;
                }
            }

            return ['mode' => 'rest', 'indices' => $indices];
        }

        $idx = (int) $arg - 1;

        return ['mode' => 'single', 'indices' => ($idx >= 0 && $idx < $total) ? [$idx] : []];
    }

    private function placeholderHtml(array $selection): string
    {
        if ($selection['mode'] === 'rest') {
            $text = Text::_('PLG_EDITORSXTD_SOURCESBUTTON_PLACEHOLDER_REST');
        } elseif ($selection['mode'] === 'single') {
            $text = Text::sprintf('PLG_EDITORSXTD_SOURCESBUTTON_PLACEHOLDER_SINGLE', ((int) $selection['indices'][0]) + 1);
        } else {
            $text = Text::_('PLG_EDITORSXTD_SOURCESBUTTON_PLACEHOLDER_ALL');
        }

        return '<span class="sources-editor-token__body">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    private function buildListPreview(array $rows, array $indices, bool $full): string
    {
        $html = '<div class="sources-editor-preview-list">';
        $manualIds = [];
        $tagIds    = [];
        $catIds    = [];

        foreach ($indices as $idx) {
            foreach ($this->extractIdList($rows[$idx] ?? [], $this->fieldAliases('manual')) as $id) {
                $manualIds[$id] = $id;
            }
            if (!$full) {
                foreach ($this->extractIdList($rows[$idx] ?? [], $this->fieldAliases('tags')) as $id) {
                    $tagIds[$id] = $id;
                }
                foreach ($this->extractIdList($rows[$idx] ?? [], $this->fieldAliases('categories')) as $id) {
                    $catIds[$id] = $id;
                }
            }
        }

        $manualTitleMap = $manualIds ? $this->fetchTitlesByIds(array_values($manualIds)) : [];
        $tagTitleMap    = $tagIds ? $this->fetchTitlesByIds(array_values($tagIds), '#__tags') : [];
        $catTitleMap    = $catIds ? $this->fetchTitlesByIds(array_values($catIds), '#__categories') : [];

        foreach ($indices as $idx) {
            $row = $rows[$idx] ?? [];
            $title = $this->extractTextValue($row, $this->fieldAliases('title'));
            $manual = $this->extractIdList($row, $this->fieldAliases('manual'));
            $tags = $this->extractIdList($row, $this->fieldAliases('tags'));
            $cats = $this->extractIdList($row, $this->fieldAliases('categories'));
            $limit = $this->extractPositiveInt($row, $this->fieldAliases('limit'), max(1, (int) $this->params->get('editor_preview_max_items', 20)));

            $html .= '<div class="sources-editor-preview-block">';
            $html .= '<strong>' . htmlspecialchars(Text::sprintf('PLG_CONTENT_SOURCES_PREVIEW_BLOCK_HEADER', $idx + 1, $title !== '' ? $title : Text::_('PLG_CONTENT_SOURCES_PREVIEW_NO_TITLE')), ENT_QUOTES, 'UTF-8') . '</strong>';

            if ($full) {
                $titles = $this->resolveFullPreviewTitles($manual, $tags, $cats, $limit);
                $html .= $this->titlesHtml($titles);
            } else {
                $titles = [];

                foreach ($manual as $id) {
                    if (isset($manualTitleMap[$id])) {
                        $titles[] = $manualTitleMap[$id];
                    }
                }

                if ($tags || $cats) {
                    $tagNames = [];
                    foreach ($tags as $id) {
                        if (isset($tagTitleMap[$id])) {
                            $tagNames[] = '<span style="color:#3d4d5c;font-style:normal;">' . htmlspecialchars($tagTitleMap[$id], ENT_QUOTES, 'UTF-8') . '</span>';
                        }
                    }

                    $catNames = [];
                    foreach ($cats as $id) {
                        if (isset($catTitleMap[$id])) {
                            $catNames[] = '<span style="color:#3d4d5c;">' . htmlspecialchars($catTitleMap[$id], ENT_QUOTES, 'UTF-8') . '</span>';
                        }
                    }

                    $metaHtml = [];
                    if ($tagNames) {
                        $metaHtml[] = 'Tags: ' . implode(', ', $tagNames);
                    }
                    if ($catNames) {
                        $metaHtml[] = 'Categories: ' . implode(', ', $catNames);
                    }

                    $summary = implode(' · ', $metaHtml);
                    $summary .= $summary ? ' (Limit: <span style="color:#3d4d5c;">' . $limit . '</span>)' : 'Limit: <span style="color:#3d4d5c;font-style:normal;">' . $limit . '</span>';

                    $html .= $this->titlesHtml($titles, '+ ' . $summary);
                } else {
                    $html .= $this->titlesHtml($titles);
                }
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    private function titlesHtml(array $titles, string $appendHtml = ''): string
    {
        if ($titles === [] && $appendHtml === '') {
            return '<div>' . htmlspecialchars(Text::_('PLG_CONTENT_SOURCES_PREVIEW_NONE'), ENT_QUOTES, 'UTF-8') . '</div>';
        }

        $html = '<ul>';

        foreach ($titles as $title) {
            $html .= '<li>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</li>';
        }

        if ($appendHtml !== '') {
            $html .= '<li class="sources-editor-auto-item" style="color:#6c8299;font-style:italic;">' . $appendHtml . '</li>';
        }

        return $html . '</ul>';
    }
    private function renderFrontend(object $article, object $field, array $rows, array $indices, string $mode, Registry $params): string
    {
        // Render by making an HTTP request to the frontend, where the
        // template runs in its natural site context with all layouts,
        // models and helpers available.
        $siteUrl = rtrim(\Joomla\CMS\Uri\Uri::root(), '/');
        $url = $siteUrl . '/index.php?option=com_ajax&plugin=sources&group=content&format=raw'
             . '&article_id=' . (int) $article->id
             . '&indices=' . implode(',', array_map('intval', $indices))
             . '&mode=' . urlencode($mode);

        try {
            $http = \Joomla\CMS\Http\HttpFactory::getHttp(new \Joomla\Registry\Registry(['follow_location' => true]), ['curl', 'stream']);
            $response = $http->get($url, [], 10);

            if ($response->code === 200) {
                $body = trim($response->body);

                if ($body !== '') {
                    return $body;
                }
            }
        } catch (\Throwable $e) {
        }

        return '';
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

        return array_values($titlesById);
    }

    private function findAutoArticleIds(array $tags, array $categories, int $limit): array
    {
        $tags = array_values(array_unique(array_filter(array_map('intval', $tags), static function ($id) { return $id > 0; })));
        $categories = array_values(array_unique(array_filter(array_map('intval', $categories), static function ($id) { return $id > 0; })));

        if ($tags === []) {
            return [];
        }

        $db = Factory::getDbo();
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

        $query->group('c.id')->having('COUNT(DISTINCT m.tag_id) >= ' . count($tags))->order('c.publish_up DESC, c.id DESC');
        $db->setQuery($query, 0, max(1, $limit));

        return array_values(array_filter(array_map('intval', (array) $db->loadColumn())));
    }

    private function fetchTitlesByIds(array $ids, string $table = '#__content'): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) { return $id > 0; })));

        if ($ids === []) {
            return [];
        }

        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'title']))
            ->from($db->quoteName($table))
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

    private function extractPositiveInt($row, $keys, int $default): int
    {
        $value = $this->extractTextValue($row, $keys);

        if (!preg_match('/^\d+$/', $value)) {
            return max(1, $default);
        }

        return max(1, (int) $value);
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

    private function noticeHtml(string $text): string
    {
        return '<span class="sources-editor-token__body">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

