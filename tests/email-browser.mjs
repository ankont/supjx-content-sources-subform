import { chromium } from 'playwright';
import { readFile, mkdir } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert/strict';

const fixtures = spawnSync('php', ['tests/php-email.php', '--artifacts'], { encoding: 'utf8' });
assert.equal(fixtures.status, 0, fixtures.stdout + fixtures.stderr);
const artifacts = new URL('./artifacts/', import.meta.url); await mkdir(artifacts, { recursive: true });
const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
try {
  for (const width of [390, 900]) for (const layout of ['cards', 'details', 'list', 'icons', 'contents', 'header']) {
    const page = await browser.newPage({ viewport: { width, height: 900 } });
    const html = await readFile(new URL('email-' + layout + '.html', artifacts), 'utf8');
    await page.setContent('<!doctype html><body style="margin:16px;font:16px/1.5 Arial,sans-serif"><main style="max-width:600px;margin:auto">' + html + '</main>');
    await page.evaluate(() => {
      const canvas = document.createElement('canvas'); canvas.width = 600; canvas.height = 400;
      const context = canvas.getContext('2d'); context.fillStyle = '#cce5e1'; context.fillRect(0, 0, 600, 400);
      context.fillStyle = '#f6df8f'; context.fillRect(60, 60, 480, 280); context.fillStyle = '#264744'; context.font = '36px Arial'; context.fillText('Article image fixture', 130, 210);
      for (const image of document.images) image.src = canvas.toDataURL();
    });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, layout + ' must fit ' + width);
    assert.equal(await page.locator('script,iframe,form').count(), 0);
    assert.ok((await page.locator('main').textContent()).includes('Public title'));
    if (layout === 'header') {
      assert.ok((await page.locator('main').textContent()).includes('Read the full article'));
      assert.equal(await page.locator('img[width="96"]').count(), 1);
      assert.equal(await page.locator('ul').count(), 0);
    }
    await page.screenshot({ path: fileURLToPath(new URL('email-' + layout + '-' + width + '.png', artifacts)), fullPage: true });
    console.log(layout + ' ' + width + 'px: email layout fits; browser fixture only, not Outlook certification');
    await page.close();
  }
} finally { await browser.close(); }
