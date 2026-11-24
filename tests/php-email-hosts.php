<?php
require __DIR__ . '/php-email.php';
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\User;
use Joomla\Registry\Registry;
use SuperSoft\Plugin\Content\Sources\Support\InlineConfig;
use SuperSoft\Plugin\Content\Sources\Support\RequestCache;

$operator = new User(999); Factory::$identity=$operator;
Factory::$users=[21=>new User(21),22=>new User(22),23=>new User(23),24=>new User(24)];
Factory::$users[23]->block=1;Factory::$users[24]->activation='pending';
Factory::$users[26]=new class(26) extends User { public function getAuthorisedViewLevels() { throw new RuntimeException('ACL unavailable'); } };
PluginHelper::$params=['show_unavailable'=>'0'];
PluginHelper::$emailParams=['email_private_enabled'=>1];
$public=clone Factory::$db->articles[1];$public->title='Public article';$public->introtext='Public body';
Factory::$db->articles=[1=>$public];
foreach ([2=>[2,1,1],3=>[3,1,1],4=>[2,2,3],5=>[2,1,1],6=>[2,1,1],7=>[2,2,2]] as $id=>$access) {
    $row=clone $public;$row->id=$id;$row->title='Private article ' . $id;
    [$row->access,$row->category_access,$row->ancestor_access]=$access;
    if($id===5)$row->state=0;if($id===6)$row->language='fr-FR';Factory::$db->articles[$id]=$row;
}
$configuration=InlineConfig::decode('{"version":2,"display":"separate","blocks":[{"type":"articles","articles":[1,2,3,4,5,6,7],"presentation":{"layout":"contents"}}]}');
$html='{sources:' . json_encode($configuration) . '}';
$call=static function(array $options=[],string $context='com_smartnewsletter.dispatch') use($provider,$html): Registry {
    $doc=new Registry(array_replace(['version'=>1,'purpose'=>'preview','publicOnly'=>true,'rootUrl'=>'https://example.test/site/',
        'html'=>$html,'limits'=>['maxBytes'=>2000000],'errors'=>[]],$options));
    $provider->prepareEmail(new \Joomla\Event\Event(['context'=>$context,'document'=>$doc]));return $doc;
};
RequestCache::reset();
$bridge=$call([], 'com_newsletterbridge.newsletter');$other=$call([], 'any.other.host');
check($bridge->get('html') === $other->get('html') && $bridge->get('errors') === [], 'Host-independent v1 public contract');
check(!str_contains($bridge->get('html'),'Private article') && Factory::$identity === $operator, 'Public request inherited operator privileges');
check($bridge->get('usage.items')===1 && $bridge->get('usage.tokens')===1, 'maxBytes-only host must not need legacy limits/counters');
check($bridge->get('providers')===null && $bridge->get('warnings')===null, 'Successful v1 must preserve Bridge response whitelist');
foreach (['preview','test','send'] as $purpose) {
    $first=$call(['version'=>2,'publicOnly'=>false,'userId'=>21,'purpose'=>$purpose]);
    check($first->get('errors')===[] && $first->get('warnings',[])===[], 'Active reader 21 failed');
    check(str_contains($first->get('html'),'Private article 2') && str_contains($first->get('html'),'Private article 7')
        && !str_contains($first->get('html'),'Private article 3') && !str_contains($first->get('html'),'Private article 4')
        && !str_contains($first->get('html'),'Private article 5') && !str_contains($first->get('html'),'Private article 6'), 'Reader/category/ancestor/publication/language policy');
    check($first->get('providers.sourcesemail.effectivePublicOnly')===false && $first->get('providers.sourcesemail.effectiveUserId')===21, 'Effective policy not published');
    $queries=Factory::$db->queries;$again=$call(['version'=>2,'publicOnly'=>false,'userId'=>21,'purpose'=>$purpose]);
    check($again->get('html')===$first->get('html') && Factory::$db->queries===$queries, 'Same-user request cache should reuse detached data');
    $second=$call(['version'=>2,'publicOnly'=>false,'userId'=>22,'purpose'=>$purpose]);
    check(str_contains($second->get('html'),'Private article 3') && !str_contains($second->get('html'),'Private article 2'), 'Private data reused across readers');
    $anonymous=$call();check($anonymous->get('html')===$bridge->get('html'), 'Private data leaked into public request cache');
    check(Factory::$identity===$operator,'Identity not restored');
}
foreach ([[null,'user_missing'],['21','user_invalid'],[0,'user_invalid'],[404,'user_invalid'],[23,'user_blocked'],[24,'user_inactive'],[26,'private_access_unavailable']] as [$id,$code]) {
    $doc=$call(['version'=>2,'publicOnly'=>false,'userId'=>$id]);
    check($doc->get('warnings')===['sources.email.' . $code] && $doc->get('errors')===[] && $doc->get('html')===$bridge->get('html'), 'Private warning/public fallback ' . $code);
    check($doc->get('providers.sourcesemail.effectivePublicOnly')===true && Factory::$identity===$operator,'Fallback identity policy');
}
PluginHelper::$params=['email_private_enabled'=>1,'show_unavailable'=>'0']; // Obsolete content setting must not grant access.
PluginHelper::$emailParams=['email_private_enabled'=>0];
$denied=$call(['version'=>2,'publicOnly'=>false,'userId'=>21]);
check($denied->get('warnings')===['sources.email.private_disabled'] && $denied->get('html')===$bridge->get('html'),'Central gate must default closed');
PluginHelper::$emailParams=[];
$defaultGate=$call(['version'=>2,'publicOnly'=>false,'userId'=>21]);check($defaultGate->get('warnings')===['sources.email.private_disabled'],'Absent central switch must deny private output');
PluginHelper::$emailParams=['email_private_enabled'=>1];
$legacy=$call(['publicOnly'=>false,'userId'=>21]);check($legacy->get('warnings')===['sources.email.private_contract_required'] && !str_contains($legacy->get('html'),'Private article'),'v1 cannot silently opt into private rendering');
\Joomla\CMS\Layout\FileLayout::$override=static function($data) {
    if((int) Factory::getApplication()->getIdentity()->id===21)throw new RuntimeException('Private template failed');
    return '<span>' . htmlspecialchars($data['item']['title'],ENT_QUOTES,'UTF-8') . '</span>';
};
$failed=$call(['version'=>2,'publicOnly'=>false,'userId'=>21]);
check($failed->get('warnings')===['sources.email.private_render_failed'] && $failed->get('errors')===[]
    && !str_contains($failed->get('html'),'Private article') && $failed->get('usage.items')===1 && Factory::$identity===$operator,'Private failure must roll back and retry public');
\Joomla\CMS\Layout\FileLayout::$override=null;
$malformed=$call(['version'=>2,'publicOnly'=>false,'userId'=>21,'html'=>'{sources:{bad}}']);
check($malformed->get('html')==='{sources:{bad}}' && $malformed->get('errors')===['sources.email.failed'] && Factory::$identity===$operator,'Failure must preserve original content and identity');
$queries=Factory::$db->queries;$boots=Factory::$boots;
$probe=$call(['version'=>2,'html'=>'']);
check($probe->get('providers.sourcesemail.capabilities.versions')===[1,2] && $probe->get('providers.sourcesemail.capabilities.privateRendering')===true
    && $probe->get('providers.sourcesemail.capabilities.privateEnabled')===true && Factory::$db->queries===$queries && Factory::$boots===$boots,'Capability discovery must not resolve content');
$invalid=$call(['purpose'=>'invalid']);check($invalid->get('errors')===['sources.email.invalid_document'],'Purpose validation');
$unsupported=$call(['version'=>999]);check($unsupported->get('html')===$html && $unsupported->get('providers.sourcesemail')===null,'Unknown contract should remain unhandled');
echo "Host-neutral public v1, private v2, warning fallbacks, identity and cache isolation passed\n";
