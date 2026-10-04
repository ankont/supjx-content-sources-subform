const config = window.Joomla?.getOptions('plg_content_sources.builder', {}) || {};
const t = key => config.i18n?.[key] || key;
let nextId = 0;

export function articleValues(items) {
  return [...new Set(items.map(item => {
    const id = String(typeof item === 'object' ? item.id : item);
    const match = /^(?:article:)?([1-9]\d*)$/.exec(id);
    if (!match) throw new Error('Invalid Articles collection identifier');
    return match[1];
  }))];
}

function element(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

function button(label, icon, action, iconOnly = false) {
  const node = element('button', iconOnly ? 'sources-builder-icon' : 'sources-builder-action');
  node.type = 'button';
  node.title = label;
  node.setAttribute('aria-label', label);
  if (icon) {
    const glyph = element('span', 'fas fa-' + icon);
    glyph.setAttribute('aria-hidden', 'true');
    node.append(glyph);
  }
  if (!iconOnly) node.append(document.createTextNode(label));
  node.addEventListener('click', action);
  return node;
}

function fieldName(control) {
  const parts = (control.name || '').match(/\[([^\]]+)\]/g) || [];
  const key = (parts.at(-1) || '').slice(1, -1);
  return config.fieldNames?.[key] || key;
}

function directControls(row, root) {
  return Array.from(row.querySelectorAll('input[name], select[name], textarea[name]'))
    .filter(control => control.closest('joomla-field-subform') === root);
}

function values(control) {
  if (control.tagName === 'SELECT' && control.multiple) {
    return Array.from(control.selectedOptions, option => option.value).filter(Boolean);
  }
  return [control.value];
}

function writeSelection(control, ids, resources = []) {
  if (control.tagName !== 'SELECT' || !control.multiple) throw new Error('Unsupported Sources selection field');
  const labels = new Map(resources.map(resource => [articleValues([resource])[0], resource.title]));
  const options = new Map(Array.from(control.options, option => [option.value, option]));
  ids.forEach(id => {
    if (!options.has(id)) options.set(id, new Option(labels.get(id) || id, id));
  });
  const selected = new Set(ids);
  const ordered = [...ids.map(id => options.get(id)), ...Array.from(options.values()).filter(option => !selected.has(option.value))];
  const choices = control.closest('joomla-field-fancy-select')?.choicesInstance;
  if (choices) {
    choices.removeActiveItems();
    choices.setChoices(ordered.map(option => ({ value: option.value, label: option.text, selected: selected.has(option.value), disabled: option.disabled })), 'value', 'label', true);
  } else {
    ordered.forEach(option => { option.selected = selected.has(option.value); control.append(option); });
  }
  // The DOM option order is also the native Joomla submission order.
  ids.forEach(id => {
    const option = Array.from(control.options).find(item => item.value === id);
    if (!option) throw new Error('Native field did not accept the selected article');
    option.selected = true;
    control.append(option);
  });
  control.dispatchEvent(new Event('change', { bubbles: true }));
}

function groups(controls) {
  const result = new Map();
  controls.filter(control => control.type !== 'hidden').forEach(control => {
    if (!result.has(control.name)) result.set(control.name, []);
    result.get(control.name).push(control);
  });
  return [...result.values()];
}

function proxyField(controls, onWrite) {
  const first = controls[0];
  const wrapper = element('div', 'sources-builder-field');
  const nativeGroup = first.closest('.control-group, .field-group, td');
  const nativeLabel = nativeGroup?.querySelector('label, .control-label');
  const label = element('label', '', nativeLabel?.textContent.trim() || fieldName(first));
  const id = 'sources-builder-control-' + ++nextId;
  label.htmlFor = id;
  wrapper.append(label);
  const proxies = controls.map((native, index) => {
    const proxy = native.cloneNode(true);
    proxy.removeAttribute('name');
    proxy.removeAttribute('onchange');
    proxy.removeAttribute('oninput');
    proxy.removeAttribute('aria-describedby');
    proxy.removeAttribute('hidden');
    proxy.id = id + (index ? '-' + index : '');
    proxy.className = native.type === 'checkbox' || native.type === 'radio' ? '' : 'sources-builder-input';
    if (proxy.multiple) proxy.size = Math.min(6, Math.max(3, proxy.options.length));
    proxy.addEventListener('input', () => {
      if (native.tagName === 'SELECT') return;
      native.value = proxy.value;
      if (native.type === 'checkbox' || native.type === 'radio') native.checked = proxy.checked;
      native.dispatchEvent(new Event('input', { bubbles: true }));
      onWrite();
    });
    proxy.addEventListener('change', () => {
      if (native.tagName === 'SELECT' && native.multiple) writeSelectionLike(native, values(proxy));
      else {
        native.value = proxy.value;
        if (native.type === 'checkbox' || native.type === 'radio') native.checked = proxy.checked;
        native.dispatchEvent(new Event('change', { bubbles: true }));
      }
      onWrite();
    });
    if (native.type === 'radio' || native.type === 'checkbox') {
      // UI radio names are separate from the submitted native group.
      if (native.type === 'radio') proxy.addEventListener('change', () => {
        proxies.forEach((peer, i) => { controls[i].checked = peer === proxy; peer.checked = peer === proxy; });
      });
      const optionLabel = element('label', 'sources-builder-check');
      const original = document.querySelector('label[for="' + CSS.escape(native.id) + '"]');
      optionLabel.append(proxy, document.createTextNode(original?.textContent.trim() || native.value));
      wrapper.append(optionLabel);
    } else {
      const nativeFancy = native.closest('joomla-field-fancy-select');
      if (nativeFancy && customElements.get('joomla-field-fancy-select')) {
        const fancy = nativeFancy.cloneNode(false);
        fancy.removeAttribute('id');
        fancy.removeAttribute('name');
        fancy.append(proxy);
        wrapper.append(fancy);
      } else wrapper.append(proxy);
    }
    return proxy;
  });
  const sync = () => controls.forEach((native, index) => {
    const proxy = proxies[index];
    if (native.tagName === 'SELECT') {
      const snapshot = JSON.stringify(Array.from(native.options, option => [option.value, option.text, option.selected, option.disabled]));
      if (proxy.dataset.nativeSnapshot === snapshot) return;
      proxy.dataset.nativeSnapshot = snapshot;
      const choices = proxy.closest('joomla-field-fancy-select')?.choicesInstance;
      if (choices) {
        choices.removeActiveItems();
        choices.setChoices(Array.from(native.options, option => ({ value: option.value, label: option.text, selected: option.selected, disabled: option.disabled })), 'value', 'label', true);
      } else {
        proxy.replaceChildren(...Array.from(native.options, option => option.cloneNode(true)));
        Array.from(proxy.options).forEach((option, i) => { option.selected = native.options[i].selected; });
      }
    } else {
      proxy.value = native.value;
      proxy.checked = native.checked;
    }
  });
  sync();
  return { node: wrapper, sync };
}

function writeSelectionLike(control, next) {
  const choices = control.closest('joomla-field-fancy-select')?.choicesInstance;
  if (choices) {
    choices.removeActiveItems();
    choices.setChoices(Array.from(control.options, option => ({ value: option.value, label: option.text, selected: next.includes(option.value), disabled: option.disabled })), 'value', 'label', true);
  }
  else Array.from(control.options).forEach(option => { option.selected = next.includes(option.value); });
  control.dispatchEvent(new Event('change', { bubbles: true }));
}

export class SourcesBuilder {
  constructor(root) {
    this.root = root;
    this.records = new Map();
    this.proxies = [];
    this.active = false;
    this.writing = false;
    this.refreshQueued = false;
    this.native = root.closest('.control-group') || root.closest('.subform-repeatable-wrapper') || root;
    this.nativeHidden = this.native.hidden;
    this.section = element('section', 'sources-builder');
    this.section.hidden = true;
    this.notice = element('p', 'sources-builder-notice');
    this.notice.hidden = true;
    this.notice.setAttribute('role', 'status');
    this.content = element('div', 'sources-builder-content');
    const help = element('span', 'sources-builder-help fas fa-info-circle');
    help.tabIndex = 0;
    help.title = t('help');
    help.setAttribute('aria-label', t('help'));
    this.section.append(help, this.content);
    this.globalFields = element('div', 'sources-builder-global');
    this.list = element('div', 'sources-builder-blocks');
    this.empty = element('p', 'sources-builder-empty', t('empty'));
    this.add = button(t('addBlock'), 'plus', () => this.run(() => {
      const row = this.root.addRow();
      if (row) { this.changed(); this.queueRefresh(); }
    }));
    this.content.append(this.globalFields, this.empty, this.list, this.add);
    this.toggle = button(t('classic'), 'sliders-h', () => this.active ? this.showClassic() : this.showBuilder());
    this.footer = element('div', 'sources-builder-footer');
    this.footer.append(this.toggle);
    this.native.after(this.section, this.notice, this.footer);
    this.onNativeChange = () => { if (!this.writing) this.queueRefresh(); };
    this.root.addEventListener('input', this.onNativeChange);
    this.root.addEventListener('change', this.onNativeChange);
    ['subform-row-add', 'subform-row-remove'].forEach(type => this.root.addEventListener(type, this.onNativeChange));
    this.observer = new MutationObserver(() => this.queueRefresh());
    this.observer.observe(this.root.containerWithRows, { childList: true });
    this.section.addEventListener('keydown', event => {
      if (event.key === 'Escape') this.section.querySelectorAll('.sources-builder-menu[open]').forEach(menu => { menu.open = false; });
    });
    this.onInvalid = () => this.showClassic();
    this.native.closest('form')?.addEventListener('invalid', this.onInvalid, true);
    this.prepareGlobalFields();
  }

  prepareGlobalFields() {
    this.globalNative = [];
    const form = this.root.closest('form');
    if (!form) return;
    const controls = Array.from(form.querySelectorAll('input[name],select[name],textarea[name]'))
      .filter(control => !this.root.contains(control) && fieldName(control).startsWith('merge-'));
    groups(controls).forEach(group => {
      const native = group[0].closest('.control-group');
      if (!native || this.globalNative.some(item => item.node === native)) return;
      this.globalNative.push({ node: native, hidden: native.hidden });
      const proxy = proxyField(group, () => this.changed());
      this.proxies.push(proxy);
      this.globalFields.append(proxy.node);
      native.addEventListener('change', () => proxy.sync());
    });
  }

  changed() {
    this.root.dispatchEvent(new Event('change', { bubbles: true }));
  }

  run(action) {
    try { return action(); } catch { this.fail(); return null; }
  }

  fail() {
    this.showClassic();
    this.notice.textContent = t('unavailable');
    this.notice.hidden = false;
  }

  showClassic() {
    this.active = false;
    this.section.hidden = true;
    this.native.hidden = this.nativeHidden;
    this.globalNative?.forEach(item => { item.node.hidden = item.hidden; });
    this.toggle.textContent = t('builder');
    this.toggle.setAttribute('aria-label', t('builder'));
    this.toggle.title = t('builder');
  }

  async showBuilder() {
    try {
      await this.refresh();
      this.proxies.forEach(proxy => proxy.sync());
      await Promise.all([...this.records.values()].map(record => record.instance.ready));
      this.active = true;
      this.section.hidden = false;
      this.native.hidden = true;
      this.globalNative.forEach(item => { item.node.hidden = true; });
      this.notice.hidden = true;
      this.toggle.textContent = t('classic');
      this.toggle.setAttribute('aria-label', t('classic'));
      this.toggle.title = t('classic');
    } catch { this.fail(); }
  }

  queueRefresh() {
    if (this.refreshQueued) return;
    this.refreshQueued = true;
    queueMicrotask(() => {
      this.refreshQueued = false;
      this.refresh().catch(() => this.fail());
    });
  }

  async refresh() {
    const rows = this.root.getRows();
    for (const [row, record] of this.records) {
      if (!rows.includes(row)) { record.instance.destroy(); record.card.remove(); this.records.delete(row); }
    }
    const updates = [];
    rows.forEach((row, index) => {
      if (!this.records.has(row)) this.records.set(row, this.createBlock(row));
      const record = this.records.get(row);
      record.number.textContent = 'BLOCK ' + (index + 1);
      record.title.textContent = record.titleField?.value.trim() || t('untitled');
      record.collapse.setAttribute('aria-label', 'BLOCK ' + (index + 1) + ': ' + record.title.textContent);
      record.collapse.title = record.title.textContent;
      const ids = articleValues(values(record.selection));
      const layout = record.layoutField?.selectedOptions?.[0]?.textContent || '';
      const tags = record.tagsField ? values(record.tagsField).filter(Boolean).length : 0;
      record.summary.textContent = [layout, t('sourceCount').replace('%s', ids.length), t('tagCount').replace('%s', tags)].filter(Boolean).join(' · ');
      record.up.disabled = index === 0;
      record.down.disabled = index === rows.length - 1;
      record.remove.disabled = rows.length <= Number(this.root.minimum || 0);
      record.duplicate.disabled = rows.length >= Number(this.root.maximum || Infinity);
      record.proxies.forEach(proxy => proxy.sync());
      if (record.ids !== ids.join('|')) {
        record.ids = ids.join('|');
        updates.push(record.instance.setItems(ids));
      }
      if (this.list.children[index] !== record.card) this.list.insertBefore(record.card, this.list.children[index] || null);
    });
    this.empty.hidden = rows.length > 0;
    this.add.disabled = rows.length >= Number(this.root.maximum || Infinity);
    await Promise.all(updates);
  }

  createBlock(row) {
    const controls = directControls(row, this.root);
    const selection = controls.find(control => ['block-manual-articles', 'block-selected-sources', 'block-selected-articles', 'manual-articles', 'manual_articles', 'selected-sources', 'sources', 'articles'].includes(fieldName(control)));
    if (!selection || selection.tagName !== 'SELECT' || !selection.multiple) throw new Error('Unsupported Sources block form');
    const titleField = controls.find(control => ['block-title', 'title', 'list-title', 'list_title'].includes(fieldName(control)));
    const layoutField = controls.find(control => ['block-layout', 'layout', 'block-format'].includes(fieldName(control)));
    const tagsField = controls.find(control => ['block-tags', 'tags'].includes(fieldName(control)));
    const card = element('article', 'sources-builder-block');
    const header = element('div', 'sources-builder-header');
    const number = element('span', 'sources-builder-number');
    const title = element('strong', 'sources-builder-title');
    const summary = element('span', 'sources-builder-summary');
    const content = element('div', 'sources-builder-block-body');
    content.id = 'sources-builder-block-' + ++nextId;
    const collapse = button(t('untitled'), 'chevron-down', () => {
      content.hidden = !content.hidden;
      collapse.setAttribute('aria-expanded', String(!content.hidden));
      card.classList.toggle('is-collapsed', content.hidden);
    }, true);
    collapse.className = 'sources-builder-collapse';
    collapse.setAttribute('aria-controls', content.id);
    collapse.setAttribute('aria-expanded', 'true');
    collapse.append(number, title, summary);
    const up = button(t('up'), 'arrow-up', () => this.move(row, -1), true);
    const down = button(t('down'), 'arrow-down', () => this.move(row, 1), true);
    const menu = element('details', 'sources-builder-menu');
    const menuToggle = element('summary', 'sources-builder-icon fas fa-ellipsis-v');
    menuToggle.title = t('actions');
    menuToggle.setAttribute('aria-label', t('actions'));
    const menuBody = element('div', 'sources-builder-menu-body');
    const duplicate = button(t('duplicate'), 'clone', () => this.run(() => { menu.open = false; this.duplicate(row); }));
    const remove = button(t('delete'), 'trash-alt', () => this.run(() => {
      menu.open = false;
      if (window.confirm(t('deleteConfirm'))) { this.root.removeRow(row); this.changed(); this.queueRefresh(); }
    }));
    menuBody.append(duplicate, remove);
    menu.append(menuToggle, menuBody);
    header.append(collapse, up, down, menu);
    const identity = element('div', 'sources-builder-identity');
    const presentation = element('div', 'sources-builder-presentation');
    const advanced = element('details', 'sources-builder-advanced');
    advanced.append(element('summary', '', t('advanced')));
    const advancedBody = element('div', 'sources-builder-advanced-body');
    advanced.append(advancedBody);
    const proxies = [];
    groups(controls).filter(group => group[0] !== selection).forEach(group => {
      const proxy = proxyField(group, () => this.changed());
      proxies.push(proxy);
      (group[0] === titleField ? identity : group[0] === layoutField ? presentation : advancedBody).append(proxy.node);
    });
    const sources = element('section', 'sources-builder-sources');
    sources.append(element('h3', '', t('sources')));
    const collection = element('div', 'sources-builder-collection');
    const addSources = button(t('addSources'), 'plus', () => this.pick(row));
    sources.append(collection, addSources);
    if (presentation.children.length) presentation.prepend(element('h3', '', t('presentation')));
    content.append(identity, sources, presentation);
    if (advancedBody.children.length) content.append(advanced);
    card.append(header, content);
    this.list.append(card);
    const record = { row, card, collapse, number, title, summary, up, down, duplicate, remove, proxies, titleField, layoutField, tagsField, selection, ids: articleValues(values(selection)).join('|') };
    record.instance = window.SmartBrowser.mountCollection(collection, {
      adapter: 'articles', items: articleValues(values(selection)), layout: 'grid',
      allowRemove: true, allowOrdering: true, contextActions: true, contextActionIds: ['edit', 'preview', 'download'],
      onChange: detail => {
        if (!this.active || !row.isConnected) return;
        this.run(() => {
          this.writing = true;
          try { writeSelection(selection, articleValues(detail.items), detail.resources); }
          finally { this.writing = false; }
          record.ids = articleValues(values(selection)).join('|');
          this.queueRefresh();
        });
      },
      onError: () => this.fail(),
    });
    record.instance.ready.catch(() => this.fail());
    return record;
  }

  async pick(row) {
    const record = this.records.get(row);
    if (!record) return;
    try {
      const result = await window.SmartBrowserPicker.open({ adapter: 'articles', multiple: true, selectionTarget: 'item', allowedResourceTypes: ['article'] });
      if (result === null || !row.isConnected) return;
      const ids = articleValues([...values(record.selection), ...result]);
      this.writing = true;
      try { writeSelection(record.selection, ids, result); }
      finally { this.writing = false; }
      await this.refresh();
    } catch { this.fail(); }
  }

  move(row, delta) {
    this.run(() => {
      const rows = this.root.getRows();
      const index = rows.indexOf(row);
      const target = rows[index + delta];
      if (!target) return;
      if (delta < 0) target.before(row); else target.after(row);
      this.changed();
      this.queueRefresh();
    });
  }

  duplicate(row) {
    const added = this.root.addRow(row);
    if (!added) return;
    const originals = directControls(row, this.root);
    const copies = directControls(added, this.root);
    copies.forEach(copy => {
      const original = originals.find(item => fieldName(item) === fieldName(copy) && item.type === copy.type && (copy.type !== 'radio' || item.value === copy.value));
      if (!original) return;
      if (copy.tagName === 'SELECT' && copy.multiple) {
        copy.replaceChildren(...Array.from(original.options, option => option.cloneNode(true)));
        if (copy === copies.find(item => ['block-manual-articles', 'sources', 'articles'].includes(fieldName(item)))) writeSelection(copy, values(original));
        else writeSelectionLike(copy, values(original));
      } else {
        copy.value = original.value;
        copy.checked = original.checked;
        copy.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
    this.changed();
    this.queueRefresh();
  }
}

const mounted = new WeakSet();
export async function initializeBuilders() {
  if (!config.fieldName || !window.SmartBrowser?.mountCollection || !window.SmartBrowserPicker?.open) return;
  if (!customElements.get('joomla-field-subform')) return;
  const roots = Array.from(document.querySelectorAll('joomla-field-subform'))
    .filter(root => root.getAttribute('name') === 'jform[com_fields][' + config.fieldName + ']');
  for (const root of roots) {
    if (mounted.has(root) || typeof root.getRows !== 'function') continue;
    mounted.add(root);
    let builder;
    try { builder = new SourcesBuilder(root); await builder.showBuilder(); }
    catch { builder?.fail(); }
  }
}

document.addEventListener('smartbrowser:collection-ready', initializeBuilders);
document.addEventListener('joomla:updated', initializeBuilders);
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeBuilders);
else initializeBuilders();
customElements.whenDefined('joomla-field-subform').then(initializeBuilders);
