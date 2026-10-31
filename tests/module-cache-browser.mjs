import { chromium } from 'playwright';
import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert/strict';

const result = spawnSync('php', ['tests/php-preview-styles.php', fileURLToPath(new URL('../../../JoomlaTest/site', import.meta.url)), '--map'], { encoding: 'utf8' });
assert.equal(result.status, 0, result.stdout + result.stderr);
const map = JSON.parse(result.stdout.split('\n')[0]);
const prefix = '/subdirectory/media/plg_editors-xtd_sourcesbutton/js/';
let stage = 'old'; const freshRequests = [];
const server = createServer(async (req, res) => {
  const url = new URL(req.url, 'http://localhost');
  if (url.pathname === '/') {
    res.setHeader('Content-Type', 'text/html'); res.setHeader('Cache-Control', 'no-store');
    res.end('<!doctype html><body>' + (stage === 'fresh' ? '<script type="importmap">' + JSON.stringify(map) + '</script>' : '') + '</body>'); return;
  }
  if (!url.pathname.startsWith(prefix)) { res.writeHead(404).end(); return; }
  const file = url.pathname.slice(prefix.length);
  if (!/^[a-z-]+\.js$/.test(file)) { res.writeHead(404).end(); return; }
  if (stage === 'fresh') freshRequests.push(url.pathname + url.search);
  try {
    const source = await readFile(new URL('../package/plugins/editors-xtd/sourcesbutton/media/js/' + file, import.meta.url), 'utf8');
    res.setHeader('Cache-Control', 'public,max-age=31536000,immutable'); res.setHeader('Content-Type', 'text/javascript');
    res.end(source + '\nexport const __cacheFixture = "' + stage + '";');
  } catch { res.writeHead(404).end(); }
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
try {
  const page = await browser.newPage(); const origin = 'http://127.0.0.1:' + server.address().port;
  await page.goto(origin);
  assert.equal(await page.evaluate(async path => (await import(path)).__cacheFixture, prefix + 'preview-ui.js'), 'old');
  stage = 'fresh'; await page.reload();
  const state = await page.evaluate(async prefix => {
    const ui = await import(prefix + 'preview-ui.js'); const inline = await import(prefix + 'inline.js');
    const config = inline.newContainer(); config.blocks[0].title = 'Fresh title';
    const node = ui.tokenNode(document, inline.scan(inline.serialize(config))[0], 'placeholder', { i18n: { sources: 'Sources' } });
    document.body.append(node);
    return { ui: ui.__cacheFixture, dependency: inline.__cacheFixture, title: node.querySelector('strong').textContent };
  }, prefix);
  assert.deepEqual(state, { ui: 'fresh', dependency: 'fresh', title: 'Fresh title' });
  assert.ok(freshRequests.length >= 4);
  assert.ok(freshRequests.every(url => /\?[a-f0-9]{64}$/.test(url)), 'Every relative dependency must bypass the warmed immutable URL');
  console.log('Warm immutable browser cache: new header and all relative dependencies refreshed without a hard reload');
} finally { await browser.close(); server.closeAllConnections(); await new Promise(resolve => server.close(resolve)); }
