<?php
defined('_JEXEC') or die;
use SuperSoft\Plugin\Content\Sources\Support\EmailHtml;
$model = $displayData['entity'];
$presentation = $displayData['presentation'] ?? ['layout'=>'default','elements'=>'both','class'=>''];
$horizontal = in_array($presentation['layout'], ['card','heading'], true);
?>
<table role="presentation" class="<?php echo EmailHtml::escape($presentation['class']); ?>" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:16px 0;font-family:inherit">
<tr>
<?php if ($model['image'] !== '') : ?><td<?php echo $horizontal ? ' width="144"' : ''; ?> style="padding:8px;vertical-align:top"><?php
$image = '<img data-sb-primary-image="1" src="' . EmailHtml::escape($model['image']) . '" alt="" width="' . ($horizontal ? 128 : 560) . '" style="display:block;width:100%;max-width:' . ($horizontal ? 128 : 560) . 'px;height:auto;border:0">';
echo empty($displayData['selected']) ? $image : \SuperSoft\Component\Smartbrowser\Administrator\Support\SelectionOutputRenderer::present($image, $displayData['selected'], ['purpose'=>$displayData['purpose']]);
?></td><?php if (!$horizontal) echo '</tr><tr>'; ?><?php endif; ?>
<td style="padding:8px;vertical-align:top">
<?php if (in_array($presentation['elements'], ['both','title'], true)) : ?><div style="font-size:20px;font-weight:bold"><?php echo EmailHtml::escape($model['title']); ?></div><?php endif; ?>
<?php if (in_array($presentation['elements'], ['both','description'], true)) : ?><div style="padding:8px 0;font-size:14px;line-height:1.5"><?php echo $model['description']; ?></div><?php endif; ?>
</td></tr>
</table>
