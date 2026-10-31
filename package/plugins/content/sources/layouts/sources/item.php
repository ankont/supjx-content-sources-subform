<?php
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\String\StringHelper;
use SuperSoft\Component\Smartbrowser\Administrator\Support\SelectionOutputRenderer;
use SuperSoft\Plugin\Content\Sources\Support\InlineTokens;

$item = $displayData['item'];
$layout = $displayData['layout'];
$selected = $displayData['selected'];
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
if (!empty($displayData['unavailable'])) {
    $safe = $displayData['unavailable_presentation'];
    echo '<article class="sources-item sources-item--' . $escape($layout) . '">';
    if ($safe['image']) { echo '<img src="' . $escape($safe['image']['src']) . '" alt="" loading="lazy">'; }
    if ($safe['icon']) { echo '<span aria-hidden="true" class="' . $escape($safe['icon']) . '"></span>'; }
    echo '<span>' . $escape($safe['title']) . '</span></article>';
    return;
}
if (in_array($layout, ['list', 'icons'], true) && ($selected['visualPresentation'] ?? null) === null) {
    $selected['visualPresentation'] = 'icon';
}
$visual = SelectionOutputRenderer::render($selected, ['layout' => 'smartbrowser.resource', 'purpose' => $displayData['purpose'], 'showUnavailable' => $displayData['showUnavailable'] ?? '']);
if (trim($visual) === '') { return; }
?>
<article class="sources-item sources-item--<?php echo $escape($layout); ?>"><div class="sources-item__identity"><?php echo $visual; ?></div><?php
if ($layout === 'contents' && !empty($displayData['header_only'])) {
    echo '<p class="sources-item__read-more"><small>' . $escape($displayData['read_more_text']) . '</small></p>';
} elseif ($layout === 'contents') {
    echo HTMLHelper::_('content.prepare', (string) $item->introtext . (string) $item->fulltext, null, 'com_content.article');
    if (is_callable($displayData['render_nested_sources_preview'] ?? null)) {
        echo $displayData['render_nested_sources_preview']();
    }
} elseif (in_array($layout, ['cards', 'details'], true)) {
    $intro = InlineTokens::replace((string) $item->introtext, static fn() => '');
    echo '<div class="sources-item__summary"><p>' . $escape(StringHelper::substr(strip_tags($intro), 0, 240)) . '</p></div>';
}
?></article>
