<?php
defined('_JEXEC') or die;
require_once dirname(__DIR__, 2) . '/src/Support/Introduction.php';
use SuperSoft\Plugin\Content\Sources\Support\Introduction;
use Joomla\CMS\Uri\Uri;
$model = Introduction::model($displayData['entity'], $displayData['type'], Uri::root());
$presentation = $displayData['presentation'] ?? ['layout'=>'default','elements'=>'both','class'=>''];
$selected = $displayData['selected'] ?? null;
$icon = null;
if ($selected !== null) {
    $selected['visualPresentation'] ??= 'both';
    $visual = \SuperSoft\Component\Smartbrowser\Administrator\Support\SelectionOutputRenderer::displayData($selected);
    $model['image'] = $visual['imageAddress'] ?? ''; $icon = $visual['icon'];
}
if ($icon) { \Joomla\CMS\Factory::getApplication()->getDocument()->getWebAssetManager()->useStyle('fontawesome'); }
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<header class="sources-intro sources-intro--<?php echo $escape($displayData['type']); ?> sources-intro--<?php echo $escape($presentation['layout']); ?> <?php echo $escape($presentation['class']); ?>">
<?php
if ($model['image'] !== '' || $icon) {
    $html = '<div class="sources-intro__visual">';
    if ($model['image'] !== '') { $html .= '<img data-sb-primary-image="1" src="' . $escape($model['image']) . '" alt="" loading="lazy" style="max-width:100%;height:auto">'; }
    if ($icon) { $html .= '<span aria-hidden="true" class="' . $escape($icon) . '"></span>'; }
    $html .= '</div>';
    echo $selected === null ? $html : \SuperSoft\Component\Smartbrowser\Administrator\Support\SelectionOutputRenderer::present($html, $selected, ['purpose'=>$displayData['purpose'] ?? 'output']);
}
?>
<div class="sources-intro__text">
<?php if (in_array($presentation['elements'], ['both','title'], true)) : ?><h2><?php echo $escape($model['title']); ?></h2><?php endif; ?>
<?php if (in_array($presentation['elements'], ['both','description'], true)) : ?><div class="sources-intro__description"><?php echo $displayData['description_html']; ?></div><?php endif; ?>
</div>
</header>
