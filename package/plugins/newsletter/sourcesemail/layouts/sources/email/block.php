<?php
defined('_JEXEC') or die;
use SuperSoft\Plugin\Content\Sources\Support\EmailHtml;
$data = $displayData;
$escape = static fn($value) => EmailHtml::escape((string) $value);
$mode = $data['layout']; $grid = in_array($mode, ['cards', 'icons'], true);
?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:16px 0;font-family:inherit;color:inherit">
<?php if ($data['title'] !== '') : ?><tr><td style="padding:8px 0 12px;font-size:20px;font-weight:500"><?php echo $escape($data['title']); ?></td></tr><?php endif; ?>
<?php if ($grid) : ?><tr><td style="font-size:0;text-align:left"><?php endif; ?>
<?php foreach ($data['items'] as $item) : ?>
<?php if ($grid) : ?><div style="display:inline-block;vertical-align:top;width:100%;max-width:<?php echo $mode === 'icons' ? '160' : '280'; ?>px;padding:8px;box-sizing:border-box">
<?php else : ?><tr><td style="padding:10px 0;border-bottom:1px solid #e3e6e8"><?php endif; ?>
<?php echo $item['html']; ?>
<?php echo $grid ? '</div>' : '</td></tr>'; ?>
<?php endforeach; ?>
<?php if ($grid) : ?></td></tr><?php endif; ?></table>
