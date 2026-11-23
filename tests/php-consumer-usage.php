<?php
define('_JEXEC', 1);
$smart = dirname(__DIR__, 2) . '/pkg_smartbrowser/package/component/admin/src/Support/';
foreach (['ResourceDescriptor','ConsumerUsage','SelectionUsageValidation','SelectionFieldSupport'] as $class) { require $smart . $class . '.php'; }
require dirname(__DIR__) . '/package/plugins/content/sources/src/Support/SelectionUsage.php';
use SuperSoft\Plugin\Content\Sources\Support\IntroUsage;
use SuperSoft\Plugin\Content\Sources\Support\SelectionUsage;
use SuperSoft\Component\Smartbrowser\Administrator\Support\ConsumerUsage;
use SuperSoft\Component\Smartbrowser\Administrator\Support\SelectionFieldSupport;
function check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
IntroUsage::register();
check(count(ConsumerUsage::definitions('sources')) === 5, 'Trusted registry missing');
foreach (IntroUsage::definitions() as $definition) {
    check(isset($definition['icon']) && preg_match('/^fas fa-[a-z-]+$/D', $definition['icon']) && $definition['icon'] !== 'fas fa-info', 'Missing meaningful Sources Usage icon');
}
foreach (['categories'=>'category','tags'=>'tag'] as $adapter=>$type) {
    $usage = ['ext.sources.introLayout'=>'card','ext.sources.introElements'=>'both','ext.sources.introTitle'=>'Local title',
        'ext.sources.introDescription'=>"Plain <script> text\nSecond line",'ext.sources.introClass'=>'feature primary'];
    $result = SelectionUsage::values(json_decode(json_encode($usage)), $adapter);
    check($result === $usage, 'Consumer values lost on save');
    $native = SelectionFieldSupport::usageValues(['type'=>$type], $usage, ['consumer'=>'sources','selectionProfile'=>IntroUsage::profile()]);
    check($native === $usage, 'Actual SmartBrowser validation diverged');
    $entity = IntroUsage::entity((object) ['title'=>'Native','description'=>'Native description'], $usage);
    check($entity->title === 'Local title' && str_contains($entity->description, '&lt;script&gt;') && str_contains($entity->description, '<br>'), 'Overrides were not escaped plain text');
    foreach (['ext.sources.introLayout'=>'invalid','ext.sources.introElements'=>'invalid','ext.sources.introClass'=>'a" onclick="bad','ext.sources.unknown'=>'x'] as $key=>$bad) {
        $rejected=false;try { SelectionUsage::values((object) [$key=>$bad], $adapter); } catch (InvalidArgumentException $e) { $rejected=true; }
        check($rejected, 'Invalid consumer value accepted');
    }
}
$rejected=false;try { SelectionUsage::values((object) ['ext.sources.introLayout'=>'card'], 'articles'); } catch (InvalidArgumentException $e) { $rejected=true; }
check($rejected,'Wrong resource type accepted');
$rejected=false;
try { SelectionFieldSupport::usageValues(['type'=>'category'], ['ext.sources.introLayout'=>'forged'],
    ['consumer'=>'sources','selectionProfile'=>IntroUsage::profile(),'usageCapabilities'=>[['key'=>'ext.sources.introLayout','options'=>[['value'=>'forged']]]]]); }
catch (InvalidArgumentException $e) { $rejected=true; }
check($rejected,'Browser-supplied definitions overrode trusted PHP schema');
echo "Consumer Usage registry, native PHP validation, types and escaping passed\n";
