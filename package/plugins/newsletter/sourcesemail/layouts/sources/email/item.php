<?php
defined('_JEXEC') or die;
use SuperSoft\Plugin\Content\Sources\Support\EmailHtml;
$item = $displayData['item']; $mode = $displayData['layout'];
$escape = static fn($value) => EmailHtml::escape((string) $value);
if (!empty($item['unavailable'])) {
    echo '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:8px">';
    if ($item['image'] !== '') { echo '<img src="' . $escape($item['image']) . '" alt="" width="120" style="display:block;max-width:100%;height:auto">'; }
    echo '<span>' . $escape($item['title']) . '</span></td></tr></table>';
    return;
}
$headerOnly = $mode === 'contents' && !empty($item['header_only']);
$stacked = !$headerOnly && in_array($mode, ['cards', 'icons', 'contents'], true);
$width = $headerOnly ? 96 : ($mode === 'icons' ? 120 : ($mode === 'contents' ? 560 : ($mode === 'list' ? 50 : ($mode === 'details' ? 146 : 264))));
?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-family:inherit;color:inherit">
<?php if ($stacked && $item['image'] !== '') : ?><tr><td style="padding:0 0 8px;text-align:center"><a href="<?php echo $escape($item['url']); ?>"><img src="<?php echo $escape($item['image']); ?>" alt="" width="<?php echo $width; ?>" style="display:block;width:100%;max-width:<?php echo $width; ?>px;height:auto;margin:0 auto;border:0"></a></td></tr><?php endif; ?>
<tr>
<?php if (!$stacked && $item['image'] !== '') : ?><td width="<?php echo $width + 14; ?>" valign="top" style="padding-right:14px"><a href="<?php echo $escape($item['url']); ?>"><img src="<?php echo $escape($item['image']); ?>" alt="" width="<?php echo $width; ?>" style="display:block;width:100%;height:auto;border:0"></a></td><?php endif; ?>
<td valign="top" style="font-size:16px;line-height:1.5;font-weight:400;text-align:<?php echo $mode === 'icons' ? 'center' : 'left'; ?>"><a href="<?php echo $escape($item['url']); ?>"><?php echo $escape($item['title']); ?></a>
<?php if (in_array($mode, ['cards', 'details'], true) && $item['summary'] !== '') : ?><p style="margin:6px 0;font-size:14px"><?php echo $escape($item['summary']); ?></p><?php endif; ?>
<?php if ($headerOnly) : ?><p style="margin:6px 0 0;font-size:12px;line-height:1.5"><?php echo $escape($item['read_more_text']); ?></p>
<?php elseif ($mode === 'contents') : ?><div style="margin-top:10px;font-size:14px;line-height:1.6"><?php echo $item['content']; ?></div><?php endif; ?>
</td></tr></table>
