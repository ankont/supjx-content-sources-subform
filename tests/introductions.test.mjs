import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { validate, newContainer, newBlock, serialize, scan } from '../package/plugins/editors-xtd/sourcesbutton/media/js/inline.js';
import { iconForUsage } from '../../pkg_smartbrowser/resources/js/core/fieldIcons.js';

test('Sources Usage icons pass through the actual SmartBrowser icon resolver',()=>{
  const result=spawnSync('php',['-r',String.raw`require $argv[1];echo json_encode(\SuperSoft\Plugin\Content\Sources\Support\IntroUsage::definitions());`,'package/plugins/content/sources/src/Support/IntroUsage.php'],{encoding:'utf8'});
  assert.equal(result.status,0,result.stdout+result.stderr);
  assert.deepEqual(JSON.parse(result.stdout).map(iconForUsage),['fas fa-th-large','fas fa-list-alt','fas fa-heading','fas fa-align-left','fas fa-code']);
});

test('Sources consumer uses actual SmartBrowser registry and PHP validation',()=>{
  const result=spawnSync('php',['tests/php-consumer-usage.php'],{encoding:'utf8'});
  assert.equal(result.status,0,result.stdout+result.stderr);
});
test('system Usage plugin supports actual Joomla non-injected loading and scoped first-install activation',()=>{
  const result=spawnSync('php',['tests/php-system-usage.php'],{encoding:'utf8'});
  assert.equal(result.status,0,result.stdout+result.stderr);
});

test('grouping and per-selection presentation round-trip without changing schema version or other Usage',()=>{
  const config=newContainer();config.blocks=[newBlock('tags')];config.blocks[0].grouped=true;
  config.blocks[0].filters.tags=[{selection:{adapter:'tags',id:'tag:12'},usage:{'ext.sources.introLayout':'collapsible','ext.sources.introElements':'title','ext.sources.introClass':'one two','ext.smartcrop.crop':{keep:true}}}];
  assert.deepEqual(scan(serialize(config))[0].config,validate(config));
  for(const invalid of ['bogus',1,{}]){const bad=structuredClone(config);bad.blocks[0].filters.tags[0].usage['ext.sources.introLayout']=invalid;assert.throws(()=>validate(bad));}
  const bad=structuredClone(config);bad.blocks[0].grouped='1';assert.throws(()=>validate(bad));
});

test('introductions are opt-in, typed and round-trip without altering existing blocks', () => {
  const config = newContainer();
  assert.equal('showIntro' in validate(config).blocks[0], false);
  for (const type of ['tags', 'categories']) {
    config.blocks = [newBlock(type)];
    config.blocks[0].showIntro = true;
    assert.equal(scan(serialize(config))[0].config.blocks[0].showIntro, true);
    const php = spawnSync('php', ['-r', 'require $argv[1]; echo json_encode(\\SuperSoft\\Plugin\\Content\\Sources\\Support\\InlineConfig::decode(stream_get_contents(STDIN)));', 'package/plugins/content/sources/src/Support/InlineConfig.php'], {input:JSON.stringify(config), encoding:'utf8'});
    assert.equal(php.status, 0, php.stdout + php.stderr);
    assert.deepEqual(JSON.parse(php.stdout), validate(config));
    config.blocks[0].showIntro = false;
    assert.equal('showIntro' in validate(config).blocks[0], false);
    config.blocks[0].showIntro = '1';
    assert.throws(() => validate(config));
  }
  config.blocks = [{...newBlock(), showIntro:true}];
  assert.throws(() => validate(config));
});

test('taxonomy introductions respect selection order, overrides and first-level-only rendering', () => {
  const result = spawnSync('php', ['tests/php-introductions.php'], {encoding:'utf8'});
  assert.equal(result.status, 0, result.stdout + result.stderr);
});
