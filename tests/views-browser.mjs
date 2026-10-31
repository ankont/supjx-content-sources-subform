import { chromium } from 'playwright';
import { readFile, mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert/strict';

const root = fileURLToPath(new URL('../', import.meta.url));
const sourcesCss = await readFile(resolve(root, 'package/plugins/editors-xtd/sourcesbutton/media/css/sourcesbutton.css'), 'utf8');
const templateCss = await readFile(resolve(root, '../../Catichisi.gr/templates/site/jl_kidsacademy_free/custom/css-compiled/custom.css'), 'utf8');
const artifacts = resolve(root, 'tests/artifacts');
await mkdir(artifacts, { recursive: true });
const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
try {
  for (const width of [390, 900, 1440]) for (const override of [false, true]) for (const templateLast of [false, true]) {
    const page = await browser.newPage({ viewport: { width, height: 1100 } });
    await page.setContent('<!doctype html><html><head><style>body{margin:16px}*{box-sizing:border-box}</style></head><body><main></main></body></html>');
    const baseCss = sourcesCss.replace(/\/\* Details are full rows[^]*$/, '');
    const sheet = await page.addStyleTag({ content: templateLast ? sourcesCss : templateCss });
    const last = await page.addStyleTag({ content: templateLast ? templateCss : sourcesCss });
    const sourceSheet = templateLast ? sheet : last;
    await page.evaluate(override => {
      const canvas = document.createElement('canvas'); canvas.width = 1200; canvas.height = 900;
      const paint = canvas.getContext('2d'); paint.fillStyle = '#548c9a'; paint.fillRect(0, 0, 1200, 900);
      paint.fillStyle = '#e0d888'; paint.fillRect(180, 180, 840, 540);
      const photo = canvas.toDataURL();
      const title = 'Υλικό για την εθνική επέτειο της 28ης Οκτωβρίου';
      const item = (mode, index) => override
        ? '<article class="library-card library-card--source library-card--' + mode + '"><div class="library-card__media"><a class="library-card__thumb"><img src="' + photo + '" width="1200" height="900" alt="Test resource"></a><span class="library-card__type-pin">i</span></div><div class="library-card__body"><h4 class="library-card__title">' + title + ' ' + index + '</h4><p>Περιγραφή του επιλεγμένου υλικού.</p></div></article>'
        : '<article class="sources-item sources-item--' + mode + '"><div class="sources-item__identity"><figure><img data-sb-primary-image="1" data-sb-image-presentation="server" src="' + photo + '" width="1200" height="900" alt="Test resource"></figure><a>' + title + ' ' + index + '</a></div>' + (['cards', 'details'].includes(mode) ? '<div class="sources-item__summary"><p>Περιγραφή του επιλεγμένου υλικού.</p></div>' : '') + '</article>';
      for (const mode of ['cards', 'details', 'list', 'icons', 'contents']) {
        const section = document.createElement('section'); section.className = 'sources-section'; section.dataset.mode = mode;
        const gridClass = mode === 'list' ? 'sources-list' : mode === 'contents' ? 'sources-contents' : 'sources-grid';
        section.innerHTML = '<h2>Προβολή ' + mode + '</h2><div class="' + gridClass + ' sources-layout--' + mode + '">' + [1, 2].map(index => item(mode, index)).join('') + '</div>';
        document.querySelector('main').append(section);
      }
    }, override);
    const measure = () => page.evaluate(() => Object.fromEntries([...document.querySelectorAll('section')].map(section => {
      const grid = section.lastElementChild, bounds = grid.getBoundingClientRect();
      return [section.dataset.mode, {
        width: grid.clientWidth, overflow: grid.scrollWidth > grid.clientWidth + 1,
        items: [...grid.children].map(card => {
          const r = card.getBoundingClientRect(), media = card.querySelector('.library-card__media');
          const body = card.querySelector('.library-card__body');
          return { width: r.width, left: r.left - bounds.left, top: r.top - bounds.top,
            overlap: media && body ? Math.max(0, Math.min(media.getBoundingClientRect().right, body.getBoundingClientRect().right) - Math.max(media.getBoundingClientRect().left, body.getBoundingClientRect().left)) *
              Math.max(0, Math.min(media.getBoundingClientRect().bottom, body.getBoundingClientRect().bottom) - Math.max(media.getBoundingClientRect().top, body.getBoundingClientRect().top)) : 0 };
        }),
      }];
    })));
    const current = await measure();
    await sourceSheet.evaluate((node, css) => { node.textContent = css; }, baseCss);
    const before = await measure();
    await sourceSheet.evaluate((node, css) => { node.textContent = css; }, sourcesCss);
    if (width === 1440 && override) {
      assert.ok(before.details.items[0].width < before.details.width * 0.6, 'Fixture must reproduce the original multi-column Details bug');
    }
    for (const [mode, metrics] of Object.entries(current)) {
      assert.equal(metrics.overflow, false, mode + ' horizontal overflow at ' + width);
      if (mode !== 'details') assert.deepEqual(metrics, before[mode], mode + ' geometry changed outside Details');
      if (['details', 'list', 'contents'].includes(mode)) {
        assert.ok(metrics.items.every(item => Math.abs(item.width - metrics.width) < 2), mode + ' must use full rows');
        assert.ok(metrics.items[1].top > metrics.items[0].top, mode + ' must not share a row');
      }
      if (mode === 'details') assert.ok(metrics.items.every(item => item.overlap < 1), 'Details media/body overlap');
    }
    await page.locator('[data-mode=details]').screenshot({ path: resolve(artifacts, 'final-details-' + width + '-' + override + '-' + templateLast + '.png') });
    console.log(width + 'px ' + (override ? 'template' : 'default') + ' / CSS order ' + templateLast + ': all five views passed');
    await page.close();
  }
} finally { await browser.close(); }
