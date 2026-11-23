<?php
require __DIR__ . '/php-renderer.php';
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\FileLayout;
use Joomla\Registry\Registry;
use SuperSoft\Plugin\Content\Sources\Support\InlineConfig;
use SuperSoft\Plugin\Content\Sources\Support\InlineRenderer;
use SuperSoft\Plugin\Content\Sources\Support\RequestCache;

Factory::$db = new class extends Database {
    public array $entities = [];
    public function loadObjectList($key = null) {
        if (!str_contains($this->query, 'from:#__tags AS t') && !str_contains($this->query, 'from:#__categories AS t')) { return parent::loadObjectList($key); }
        foreach (['t.published = 1', 't.access IN (1,2)', 't.language IN (', 't.id IN ('] as $guard) {
            check(str_contains($this->query, $guard), 'Missing taxonomy guard: ' . $guard);
        }
        if (str_contains($this->query, '#__categories AS t')) {
            check(str_contains($this->query, 'ancestor.access NOT IN') && str_contains($this->query, "t.extension = 'com_content'"), 'Missing category ancestry/extension guard');
        }
        // Simulate ACL/publishing/language filtering: only 8 and 12 are accessible.
        return array_intersect_key($this->entities, array_flip([8, 12]));
    }
};
$renderer = new InlineRenderer(new Registry());
$config = InlineConfig::decode('{"version":2,"display":"separate","blocks":[{"type":"tags","filters":{"tags":[12,8,99],"categories":[14]}}]}');
$parent = (object) ['id' => 100];
Factory::$db->entities = [8 => (object) ['id' => 8, 'title' => 'Tag eight', 'description' => 'Eight description', 'params' => '{}', 'images' => '{}'],
    12 => (object) ['id' => 12, 'title' => 'Tag twelve', 'description' => 'Twelve description', 'params' => '{}', 'images' => '{}']];
$seen = [];
FileLayout::$onRender = static function ($data, $name) use (&$seen) {
    if (!isset($data['entity'])) { return '<i>ARTICLE</i>'; }
    $seen[] = [$name, $data];
    return '<aside>' . $data['entity']->title . $data['description_html'] . '</aside>';
};
$before = count(Factory::$db->queries);
$renderer->render($config, $parent);
check(!$seen && count(Factory::$db->queries) === $before + 1, 'Disabled introduction must not query taxonomy');
$config['blocks'][0]['showIntro'] = true;
foreach (['tags', 'categories'] as $type) foreach (['output', 'preview'] as $purpose) foreach (InlineConfig::LAYOUTS as $layout) {
    $config['blocks'][0]['type'] = $type;
    $config['blocks'][0]['filters'][$type] = [12, 8, 99];
    $config['blocks'][0]['presentation']['layout'] = $layout;
    $seen = []; $html = $renderer->render($config, $parent);
    check(count($seen) === 2 && array_map(static fn($row) => $row[1]['entity']->id, $seen) === [12, 8], 'Taxonomy introductions order/visibility');
    check($seen[0][0] === 'sources.' . ($type === 'tags' ? 'tag' : 'category') . '-intro', 'Wrong override boundary');
    check($seen[0][1]['layout'] === $layout && $seen[0][1]['purpose'] === 'output', 'Wrong intro context');
    $preparation = end(\Joomla\CMS\HTML\HTMLHelper::$calls);
    check($preparation[2][1] === ($type === 'tags' ? 'com_tags.tag' : 'com_content.category'), 'Native description context not used');
    $seen = []; $renderer->render($config, $parent, 'render', $purpose);
    check($seen[0][1]['purpose'] === $purpose, 'Preview diverged');
    check(strpos($html, 'Tag twelve') < strpos($html, 'ARTICLE'), 'Introduction must precede list');
}
$config['blocks'][0]['type'] = 'tags';
$grouped = $config; $grouped['blocks'][0]['grouped'] = true; $grouped['blocks'][0]['filters']['tags'] = [12,8];
$resolved = $renderer->resolveBlocks($grouped, 'render', 500);
check(count($resolved) === 2 && array_column(array_column($resolved, 'group'), 'id') === [12,8], 'Grouped order/identity lost');
check($resolved[0]['filters']['tags'] === [12] && $resolved[1]['filters']['tags'] === [8], 'Grouped tags must resolve individually instead of AND');
check($resolved[0]['filters']['categories'] === $grouped['blocks'][0]['filters']['categories'], 'Secondary filters lost');
$grouped['display'] = 'merged';$resolved = $renderer->resolveBlocks($grouped, 'render', 500);
check(count($resolved) === 1 && !isset($resolved[0]['group']), 'Grouping must not split merged output');
$local = $config;$local['blocks'][0]['filters']['tags'] = [['selection'=>['adapter'=>'tags','id'=>'tag:12'],'usage'=>['ext.sources.introLayout'=>'card','ext.sources.introElements'=>'title','ext.sources.introTitle'=>'Local title','ext.sources.introDescription'=>'Do not render this','ext.sources.introClass'=>'feature']]];
$seen=[];$renderer->render($local,$parent);
check($seen[0][1]['entity']->title === 'Local title' && $seen[0][1]['description_html'] === '' && $seen[0][1]['presentation']['class'] === 'feature', 'Per-selection introduction usage lost');
$local['blocks'][0]['filters']['tags'][0]['usage']['ext.sources.introElements']='description';
$local['blocks'][0]['filters']['tags'][0]['usage']['ext.sources.introDescription']='Plain {loadmodule} <b>description</b>';
$beforePlugins=count(\Joomla\CMS\HTML\HTMLHelper::$calls);$seen=[];$renderer->render($local,$parent);
check(count(\Joomla\CMS\HTML\HTMLHelper::$calls) === $beforePlugins && str_contains($seen[0][1]['description_html'],'&lt;b&gt;'), 'Plain override must not execute content plugins');
$nested = '{sources:' . json_encode($config) . '}';
Factory::$db->entities[12]->description = 'SUPPLEMENT ' . $nested;
RequestCache::reset(); $seen = [];
$html = $renderer->render($config, $parent, 'render', 'preview');
check(count($seen) === 2 && str_contains($html, 'SUPPLEMENT') && substr_count($html, 'ARTICLE') === 4 && !str_contains($html, '{sources:'), 'Description Sources must render without recursively adding introductions');
foreach (\Joomla\CMS\HTML\HTMLHelper::$calls as $preparation) {
    check(!str_contains($preparation[1], '{sources:'), 'Sources tokens exposed to category/blog plugin handling');
}
$config['display'] = 'merged'; $config['blocks'][] = $config['blocks'][0];
$seen = []; $html = $renderer->render($config, $parent);
check(count($seen) === 2, 'Merged introductions must deduplicate primary entities');
Factory::$db->entities[12]->description = 'Description without nested token'; RequestCache::reset();
$config['display'] = 'separate'; $config['blocks'] = [$config['blocks'][0]]; $config['blocks'][0]['presentation']['layout'] = 'contents';
$entered = false; $seen = [];
FileLayout::$onRender = static function ($data) use (&$seen, &$entered, $renderer, $config, $parent) {
    if (isset($data['entity'])) { $seen[] = $data; return 'INTRO'; }
    if (!$entered) { $entered = true; return $renderer->render($config, $parent); }
    return 'ARTICLE';
};
$renderer->render($config, $parent);
check(count($seen) === 2, 'Embedded Contents Sources must not add introductions');
// A failed template must not suppress introductions in later independent containers.
FileLayout::$onRender = static function ($data) { if (isset($data['entity'])) { throw new RuntimeException('Fixture intro failure'); } return 'ARTICLE'; };
try { $renderer->render($config, $parent); } catch (RuntimeException $error) {}
FileLayout::$onRender = static function ($data) use (&$seen) { if (isset($data['entity'])) { $seen[] = $data; } return 'OK'; };
$seen = []; $renderer->render($config, $parent);
check(count($seen) === 2, 'Failed introduction leaked recursion guard');
echo "\nIntroduction selection, override, nested Sources and preview fixtures passed\n";
