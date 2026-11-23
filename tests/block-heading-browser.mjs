import { chromium } from 'playwright';
import { readFile, mkdir } from 'node:fs/promises';
import assert from 'node:assert/strict';

const css = await readFile(new URL('../package/plugins/editors-xtd/sourcesbutton/media/css/sourcesbutton.css', import.meta.url), 'utf8');
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
try {
  const page = await browser.newPage();
  for (const width of [390, 1000]) {
    await page.setViewportSize({ width, height: 400 });
    await page.setContent(`<style>${css}</style><div class="sources-builder-dialog sources-builder-embedded"><section class="sources-block"><header class="sources-block-heading"><button class="sources-command sources-block-toggle"><span class="fas">v</span><span class="sources-block-identity"><strong></strong><span class="sources-block-summary">Cards - Specific articles - 1 selected</span></span></button><div class="sources-block-toolbar">${['up', 'down', '+', 'copy', 'delete'].map(x => `<button class="sources-command">${x}</button>`).join('')}</div></header></section></div>`);
    await page.locator('strong').evaluate(el => { el.textContent = 'Very long article heading '.repeat(20); });
    assert.ok(await page.locator('strong').evaluate(el => el.scrollWidth > el.clientWidth && getComputedStyle(el).textOverflow === 'ellipsis'));
    assert.ok(await page.locator('.sources-block-heading').evaluate(el => el.scrollWidth <= el.clientWidth));
    const heading = await page.locator('.sources-block-heading').boundingBox();
    const toolbar = await page.locator('.sources-block-toolbar').boundingBox();
    assert.ok(toolbar.x >= heading.x && toolbar.x + toolbar.width <= heading.x + heading.width);
    if (width > 600) {
      const title = await page.locator('strong').boundingBox();
      assert.ok(title.x + title.width <= toolbar.x);
    }
    await mkdir(new URL('./artifacts/', import.meta.url), { recursive: true });
    await page.screenshot({ path: new URL(`./artifacts/block-heading-${width}.png`, import.meta.url).pathname.replace(/^\/(\w:)/, '$1') });
  }
  console.log('Long block headings: desktop/mobile ellipsis and toolbar bounds passed.');
} finally { await browser.close(); }
