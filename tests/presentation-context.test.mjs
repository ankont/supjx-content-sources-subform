import test from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
import { newContainer, serialize, scan } from '../package/plugins/editors-xtd/sourcesbutton/media/js/inline.js';
import { bindPresentationDocument, getEditorPresentation, setEditorPresentation, observeEditorPresentation } from '../package/plugins/editors-xtd/sourcesbutton/media/js/presentation-context.js';
import { tokenNode, requestTokenPreview, refreshPresentation, mountNestedPreviews, disposeTokenPreview, previewErrorKey } from '../package/plugins/editors-xtd/sourcesbutton/media/js/preview-ui.js';

test('email dependency failures display their own message, never site output',async()=>{
  const dom=new JSDOM('<body></body>',{url:'https://host.invalid',pretendToBeVisual:true});
  global.window=dom.window;global.document=dom.window.document;window.Joomla={getOptions:()=>({})};
  const previous=global.fetch;
  try{
    for(const dependency of ['content','email'])for(const status of ['missing','disabled','restricted','public_required','unavailable','incomplete']){
      const code=dependency+'_plugin_'+status,key=previewErrorKey(code);
      const settings={presentation:'email',ajaxUrl:'/preview',csrfToken:'csrf',i18n:{[key]:'Specific '+code}};
      const token=scan(serialize(newContainer()))[0],node=tokenNode(document,token,'render',settings);document.body.append(node);
      global.fetch=async()=>({ok:true,status:200,json:async()=>({success:true,data:[{ok:false,html:'',code}]})});
      await requestTokenPreview(node,settings);
      assert.equal(node.querySelector('.sources-editor-token__body').textContent,'Specific '+code);
      assert.equal(node.querySelector('iframe'),null);disposeTokenPreview(node);node.remove();
    }
  }finally{global.fetch=previous;dom.window.close();}
});

test('explicit per-instance site/email contexts isolate requests, mode changes and stale responses', async () => {
  const a = new JSDOM('<body></body>', { url: 'https://host.invalid', pretendToBeVisual: true });
  const b = new JSDOM('<body></body>', { url: 'https://host.invalid', pretendToBeVisual: true });
  global.window = a.window; global.document = a.window.document;
  window.Joomla = { getOptions: key => key.endsWith('.preview') ? { editors: { emailInstance: { presentation: 'email' } } } : { ajaxUrl: '/preview', csrfToken: 'csrf' } };
  bindPresentationDocument(a.window.document, 'siteInstance'); bindPresentationDocument(b.window.document, 'emailInstance');
  const token = scan(serialize(newContainer()))[0];
  const site = tokenNode(a.window.document, token, 'render'), email = tokenNode(b.window.document, token, 'render');
  a.window.document.body.append(site); b.window.document.body.append(email);
  assert.equal(getEditorPresentation('siteInstance'), 'site'); assert.equal(getEditorPresentation('emailInstance'), 'email');
  assert.throws(() => setEditorPresentation('siteInstance', 'unknown'));
  const originalFetch = global.fetch, pending = [];
  global.fetch = (url, options) => new Promise(resolve => pending.push({ options, resolve }));
  const stop = observeEditorPresentation('siteInstance', value => refreshPresentation(a.window.document, value));
  const reply = (entry, presentation, html) => entry.resolve({ ok: true, status: 200, json: async () => ({ data: [{ ok: true, html, presentation, styles: ['/site.css'] }] }) });
  const settle = () => new Promise(resolve => setTimeout(resolve, 0));
  try {
    const old = requestTokenPreview(site), separate = requestTokenPreview(email);
    assert.equal(pending[0].options.body.get('presentation'), 'site'); assert.equal(pending[1].options.body.get('presentation'), 'email');
    setEditorPresentation('siteInstance', 'email');
    assert.equal(pending[0].options.signal.aborted, true);
    assert.equal(pending[2].options.body.get('presentation'), 'email');
    const html = '<table role="presentation" width="100%" style="border-collapse:collapse"><tr><td><img src="https://host.invalid/a.jpg" width="146" height="90" style="display:block;width:146px"><b>Email</b></td></tr></table>';
    reply(pending[2], 'email', html); reply(pending[1], 'email', html); await separate; await settle();
    reply(pending[0], 'site', '<p>Stale site</p>'); await old;
    assert.ok(site.querySelector('iframe.sources-email-preview')); assert.ok(email.querySelector('iframe.sources-email-preview'));
    assert.ok(site.querySelector('iframe').srcdoc.includes('border-collapse:collapse'));
    assert.ok(site.querySelector('iframe').srcdoc.includes('width="146" height="90"'));
    assert.equal(site.querySelector('iframe').getAttribute('sandbox'), 'allow-same-origin');
    assert.equal(a.window.document.querySelector('link[href$="site.css"]'), null);
    assert.equal(site.textContent.includes('Stale site'), false);
    email.dataset.sourcesPreviewMode = 'list_manual'; const summary = requestTokenPreview(email);
    assert.equal(pending[3].options.body.get('presentation'), 'email');
    reply(pending[3], 'email', '<ul><li>Public title</li></ul>'); await summary;
    assert.equal(email.querySelector('iframe'), null); assert.equal(email.querySelector('li').textContent, 'Public title');
    const marker = b.window.document.createElement('div'); marker.dataset.sourcesPreviewToken = token.raw; marker.innerHTML = '<ul><li>Nested</li></ul>';
    email.querySelector('.sources-editor-token__body').append(marker); mountNestedPreviews(b.window.document, { presentation: 'email' });
    assert.equal(b.window.document.querySelector('[data-sources-nested-preview]').dataset.sourcesPresentation, 'email');
    const uncertified = tokenNode(b.window.document, token, 'render'); b.window.document.body.append(uncertified);
    const missingContext = requestTokenPreview(uncertified);
    reply(pending[4], undefined, '<p>Old server site markup</p>'); await missingContext;
    assert.equal(uncertified.querySelector('iframe'), null);
    assert.equal(uncertified.querySelector('.sources-editor-token__body').textContent, 'previewResponse');
    disposeTokenPreview(uncertified);
    assert.equal(site.dataset.sourcesToken, token.raw); assert.equal(email.dataset.sourcesToken, token.raw);
  } finally { stop(); disposeTokenPreview(site); disposeTokenPreview(email); global.fetch = originalFetch; a.window.close(); b.window.close(); }
});
