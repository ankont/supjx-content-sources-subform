<?php
require __DIR__ . '/../package/plugins/content/sources/src/Support/InlineConfig.php';
require __DIR__ . '/../package/plugins/content/sources/src/Support/InlineTokens.php';
use SuperSoft\Plugin\Content\Sources\Support\InlineConfig;
use SuperSoft\Plugin\Content\Sources\Support\InlineTokens;
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
$data = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
$tokens = InlineTokens::scan('before ' . $data['valid'] . ' middle ' . $data['future'] . ' after ' . $data['valid']);
check(count($tokens) === 3, 'Multiple containers');
check(json_decode(json_encode($tokens[0]['config']), true) === $data['expected'], 'Client/server schema parity');
check(InlineConfig::decode('{"version":2.0,"display":"separate","blocks":[{"type":"articles","articles":[1e1],"filters":{"limit":16.0}}]}')['blocks'][0]['articles'] === [10], 'JSON integer numeric forms');
check($tokens[1]['config'] === null, 'Unknown version is preserved');
$rendered = InlineTokens::replace('before ' . $data['valid'] . ' middle ' . $data['future'] . ' after', fn() => '<section>rendered</section>');
check($rendered === 'before <section>rendered</section> middle ' . $data['future'] . ' after', 'Exact token position and invalid content retention');
check(InlineTokens::scan('{sources:{"unterminated":"}') === [], 'Malformed input');
check(array_filter(InlineTokens::scan('{sources:{"unterminated":"' . $data['valid']), fn($token) => $token['config'] !== null) === [], 'Malformed outer token never executes an embedded marker');
check(InlineTokens::scan('{sources:{"huge":"' . str_repeat('x', InlineConfig::MAX_BYTES + 5) . '"}}') === [], 'Size bound');
check(count(InlineTokens::scan('<a title=\'' . $data['valid'] . '\'>link</a><!--' . $data['valid'] . '--><pre>' . $data['valid'] . '</pre><p>' . $data['valid'] . '</p>')) === 1, 'HTML text-only parsing');
check(InlineTokens::scan(str_repeat('ignored {sources:{', 20000)) === [], 'Bounded malformed scanning');
foreach (['{"version":1,"display":"merged","blocks":[]}', '{"version":1,"display":"merged","blocks":[{"articles":["1"]}]}',
    '{"version":1,"display":"merged","blocks":[{"filters":{"limit":501}}]}', '{"version":1,"display":"merged","blocks":[{"html":"<script>"}]}'] as $invalid) {
    $rejected = false; try { InlineConfig::decode($invalid); } catch (Throwable $error) { $rejected = true; }
    check($rejected, 'Schema must reject unsafe values');
}
echo 'PHP inline assertions passed';
