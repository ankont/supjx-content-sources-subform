<?php
defined('_JEXEC') or die;
if (!empty($displayData['collapsible'])) {
    echo '<details class="sources-group sources-group--collapsible" open><summary>' . $displayData['introductions_html'] . '</summary>' . $displayData['results_html'] . '</details>';
} else {
    echo $displayData['introductions_html'] . $displayData['results_html'];
}
