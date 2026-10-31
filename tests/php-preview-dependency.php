<?php
namespace Joomla\Database { interface DatabaseInterface {} }
namespace Joomla\CMS\User { class User { public function __construct($id=0) {} public function getAuthorisedViewLevels(){return [1];} } }
namespace Joomla\CMS\Plugin { class PluginHelper { public static bool $available=true; public static function isEnabled(...$args){return self::$available;} } }
namespace Joomla\CMS {
    class Factory {
        public static $db;
        public static function getContainer(){return new class {public function get($name){return Factory::$db;}};}
        public static function getApplication(){return new class {public function getIdentity(){return new class {public function getAuthorisedViewLevels(){return [1,2];}};} };}
    }
}
namespace {
    define('JPATH_PLUGINS',dirname(__DIR__).'/package/plugins');
    require JPATH_PLUGINS.'/editors-xtd/sourcesbutton/src/Support/PreviewDependency.php';
    use SuperSoft\Plugin\EditorsXtd\SourcesButton\Support\PreviewDependency;
    $db=new class {
        public $row;
        public function getQuery($new){return new class {public function __call($name,$args){return $this;}};}
        public function quote($value){return "'".$value."'";}
        public function setQuery(...$args){return $this;}
        public function loadObject(){return $this->row;}
    };
    \Joomla\CMS\Factory::$db=$db;
    foreach(['content'=>'sources','newsletter'=>'sourcesemail'] as $group=>$element){
        $prefix=$group==='content'?'content_plugin_':'email_plugin_';
        foreach([
            [null,'missing'],
            [(object)['enabled'=>0,'state'=>0,'access'=>1],'disabled'],
            [(object)['enabled'=>1,'state'=>-1,'access'=>1],'unavailable'],
            [(object)['enabled'=>1,'state'=>0,'access'=>3],'restricted'],
            [(object)['enabled'=>1,'state'=>0,'access'=>2],'public_required'],
        ] as [$row,$expected]){
            $db->row=$row;
            if(PreviewDependency::check($group,$element,true)!==$prefix.$expected)throw new \RuntimeException('Wrong dependency status: '.$expected);
        }
        $db->row=(object)['enabled'=>1,'state'=>0,'access'=>1];
        if(PreviewDependency::check($group,$element,true)!==null)throw new \RuntimeException('Public enabled package unavailable');
        \Joomla\CMS\Plugin\PluginHelper::$available=false;
        if(PreviewDependency::check($group,$element,true)!==$prefix.'unavailable')throw new \RuntimeException('Runtime cache mismatch was bypassed');
        \Joomla\CMS\Plugin\PluginHelper::$available=true;
        if(PreviewDependency::check($group,'absent-files',true)!==$prefix.'incomplete')throw new \RuntimeException('Missing package files not detected');
        $db->row->access=2;
        if(PreviewDependency::check($group,$element,false)!==null)throw new \RuntimeException('Site author access changed');
    }
    echo "Dependency states, public policy, runtime availability and package files passed\n";
}
