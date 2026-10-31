import { chromium } from 'playwright';
import { readFile, mkdir } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert/strict';

const css = await readFile(new URL('../package/plugins/editors-xtd/sourcesbutton/media/css/sourcesbutton.css', import.meta.url), 'utf8');
const artifacts = new URL('./artifacts/', import.meta.url);
await mkdir(artifacts, { recursive: true });
const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
try {
  for (const width of [390, 1440, 1920]) {
    const page = await browser.newPage({ viewport: { width, height: 900 } });
    await page.setContent('<!doctype html><style>body{margin:16px}</style><div class="sources-editor-token sources-editor-token--render"><span class="sources-editor-token__header">Sources preview</span><span class="sources-editor-token__body"><section class="sources-section"><span class="visually-hidden">More information</span><div style="height:1250px;background:#e0eef2">Tall preview</div></section></span></div><p id="after">Following article content</p>');
    await page.addStyleTag({ content: css });
    await page.addStyleTag({ content: '.sources-list{background:white;border:1px solid gray;border-radius:18px;box-shadow:0 14px 32px #0002}' });
    await page.evaluate(() => {
      const note = document.createElement('span'); note.className = 'sources-editor-token sources-editor-token--placeholder';
      note.innerHTML = '<span class="sources-editor-token__header"><span class="sources-editor-token__label">Nested Sources</span></span>';
      document.querySelector('.sources-section').prepend(note);
      const list = document.createElement('div'); list.className = 'sources-list';
      list.innerHTML = '<ul class="sources-preview-list"><li>Plain preview list</li></ul>'; document.querySelector('.sources-section').prepend(list);
    });
    const measure = () => page.evaluate(() => {
      const body = document.querySelector('.sources-editor-token__body');
      const token = body.parentElement, hidden = body.querySelector('.visually-hidden');
      const note = body.querySelector('.sources-editor-token--placeholder');
      const rect = body.getBoundingClientRect(), outer = token.getBoundingClientRect();
      return { height: rect.height, width: rect.width, overflow: getComputedStyle(body).overflowY,
        listBorder: getComputedStyle(body.querySelector('.sources-list')).borderTopWidth,
        listRadius: getComputedStyle(body.querySelector('.sources-list')).borderTopLeftRadius,
        left: rect.left - outer.left, right: outer.right - rect.right,
        hiddenWidth: hidden.getBoundingClientRect().width, noteAlign: getComputedStyle(note.querySelector('.sources-editor-token__label')).textAlign,
        noteFits: note.scrollWidth <= note.clientWidth,
        after: document.querySelector('#after').getBoundingClientRect().top, bottom: outer.bottom,
        horizontalOverflow: document.documentElement.scrollWidth > innerWidth };
    });
    const normal = await measure();
    assert.ok(normal.height >= 1250);
    assert.equal(normal.overflow, 'visible');
    assert.equal(normal.listBorder, '0px'); assert.equal(normal.listRadius, '0px');
    assert.ok(Math.abs(normal.left - normal.right) < 1);
    assert.equal(normal.hiddenWidth, 1);
    assert.equal(normal.noteAlign, 'center'); assert.equal(normal.noteFits, true);
    assert.ok(normal.after >= normal.bottom);
    assert.equal(normal.horizontalOverflow, false);
    await page.addStyleTag({ content: ':root{--sources-preview-max-width:960px}' });
    const limited = await measure();
    assert.ok(limited.width <= 960);
    assert.ok(Math.abs(limited.left - limited.right) < 1);
    assert.ok(width < 1000 || Math.abs(limited.width - 960) < 1);
    await page.screenshot({ path: fileURLToPath(new URL('preview-surface-' + width + '.png', artifacts)) });
    console.log(width + 'px: natural height, balanced spacing, accessible label and template width passed');
    await page.close();
  }
} finally { await browser.close(); }
