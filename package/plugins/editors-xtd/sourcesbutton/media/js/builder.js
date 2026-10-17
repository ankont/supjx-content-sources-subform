import { newContainer, newBlock, validate, serialize, LAYOUTS, TYPES, ORDERS } from './inline.js';
import { referenceItems as references, selectionValues, selectionIds as storedIds, valueId, usageProfile } from './usage.js';

const options = () => window.Joomla?.getOptions('plg_editorsxtd_sourcesbutton.inline', {}) || {};
const t = key => options().i18n?.[key] || key;
const prefix = key => ({ articles: 'article', categories: 'category', tags: 'tag' })[key];
const layoutIcons = { cards: 'th-large', details: 'list-alt', list: 'list-ul', icons: 'icons', contents: 'align-left' };
let fieldSequence = 0;
function el(tag, className = '', text = '') {
  const node = document.createElement(tag); node.className = className; node.textContent = text; return node;
}
function button(label, icon, action) {
  const node = el('button', 'sources-command'); node.type = 'button'; node.title = label; node.setAttribute('aria-label', label);
  const glyph = el('span', 'fas fa-' + icon); glyph.setAttribute('aria-hidden', 'true'); node.append(glyph);
  node.addEventListener('click', action); return node;
}

export class SourcesBuilder {
  constructor(config = null, editing = false, embedding = null) {
    this.config = validate(structuredClone(config || newContainer()));
    this.embedding = embedding;
    this.editing = editing; this.records = new Map(); this.closed = false; this.pending = 0;
    this.dialog = el(embedding ? 'div' : 'dialog', 'smartbrowser-editor sources-builder-dialog' + (embedding ? ' sources-builder-embedded' : ''));
    this.form = el(embedding ? 'div' : 'form', 'sources-builder');
    this.form.noValidate = false;
    const header = el('header', 'sources-builder-heading');
    const heading = el('h2', '', t('builder')); heading.id = 'sources-builder-title-' + ++fieldSequence;
    this.dialog.setAttribute('aria-labelledby', heading.id);
    const controls = el('div', 'sources-builder-window-controls');
    this.sizeButton = button(t('maximize'), 'expand', () => {}); this.sizeButton.hidden = true;
    controls.append(this.sizeButton, button(t('cancel'), 'times', () => this.finish(null)));
    header.append(heading, controls);
    const body = el('div', 'sources-builder-body');
    this.alert = el('div', 'sources-builder-alert'); this.alert.role = 'alert'; this.alert.hidden = true;
    const display = el('div', 'sources-display'); display.setAttribute('role', 'group'); display.setAttribute('aria-label', t('display'));
    this.modes = ['separate', 'merged'].map(mode => {
      const choice = button(t(mode), mode === 'merged' ? 'layer-group' : 'columns', () => {
        this.config.display = mode; this.refreshDisplay();
      });
      choice.append(document.createTextNode(t(mode))); choice.dataset.mode = mode; display.append(choice); return choice;
    });
    this.containerSettings = el('div', 'sources-settings sources-container-settings');
    this.containerSettings.append(this.field(t('title'), this.config.presentation, 'title'), ...this.presentation(this.config.presentation));
    this.mergeOrdering = el('div', 'sources-settings sources-merge-ordering');
    this.mergeOrdering.append(this.selectField(t('mergeOrdering'), this.config.ordering, 'mode', ['blocks', 'global']), this.selectField(t('order'), this.config.ordering, 'order', ORDERS));
    this.orderingHint = el('p', 'sources-ordering-hint', t('blockOrderingHint'));
    this.mergeOrdering.insertBefore(this.orderingHint, this.mergeOrdering.lastElementChild);
    this.list = el('div', 'sources-blocks');
    this.add = el('div', 'sources-add-actions');
    this.addButtons = TYPES.map(type => {
      const command = button(t('add_' + type), 'plus', () => {
      if (this.config.blocks.length >= 30) return;
      const block = newBlock(type); this.config.blocks.push(block); this.renderBlocks();
      this.records.get(block)?.titleInput.focus();
      });
      command.classList.add('sources-add'); command.append(document.createTextNode(t('add_' + type))); this.add.append(command); return command;
    });
    body.append(this.alert, display, this.containerSettings, this.mergeOrdering, this.list, this.add);
    const footer = el('footer', 'sources-builder-footer');
    const cancel = button(t('cancel'), 'times', () => this.finish(null)); cancel.className = 'btn btn-danger'; cancel.append(document.createTextNode(t('cancel')));
    cancel.firstElementChild.className = 'icon-times';
    this.confirm = button(t(editing ? 'update' : 'insert'), editing ? 'save' : 'plus', () => {});
    this.confirm.className = editing ? 'btn btn-outline-secondary' : 'btn btn-success'; this.confirm.append(document.createTextNode(t(editing ? 'update' : 'insert'))); this.confirm.type = 'submit';
    this.confirm.firstElementChild.className = (editing ? 'icon-save text-success' : 'icon-plus');
    footer.append(cancel, this.confirm); this.form.append(header, body, footer); this.dialog.append(this.form);
    if (embedding) { header.hidden = true; footer.hidden = true; }
    this.dialog.addEventListener('cancel', event => { event.preventDefault(); if (!this.pending) this.finish(null); });
    this.dialog.addEventListener('close', () => this.finish(null));
    this.form.addEventListener('submit', event => {
      event.preventDefault(); if (this.pending) return;
      try { serialize(this.config); this.finish(validate(this.config)); } catch (error) { this.error(error); }
    });
    this.refreshDisplay();
  }

  open() {
    this.previousFocus = document.activeElement;
    document.body.append(this.dialog); this.dialog.showModal(); this.renderBlocks();
    this.prepareSize();
    return new Promise(resolve => { this.resolve = resolve; });
  }

  mount(host) {
    if (!this.embedding) throw new Error('Embedded Builder configuration is required.');
    host.append(this.dialog); this.renderBlocks(); return this;
  }

  notifyChange() {
    if (this.embedding && this.dialog.isConnected && !this.closed) this.embedding.onChange?.(this.config, this.pending);
  }

  destroy() {
    this.pending = 0; this.finish(null);
  }

  finish(value) {
    if (this.closed || this.pending) return;
    this.closed = true;
    this.size?.destroy();
    for (const record of this.records.values()) record.collection?.destroy();
    if (this.dialog.open) this.dialog.close();
    this.dialog.remove(); this.previousFocus?.focus?.(); this.resolve?.(value);
  }

  async prepareSize() {
    const url = options().editorSizeUrl;
    if (!url) return;
    try {
      const { createEditorSize } = await import(url);
      if (this.closed) return;
      this.size = createEditorSize(this.dialog, this.sizeButton,
        key => t(key === 'COM_SMARTBROWSER_EDITOR_RESTORE' ? 'restore' : 'maximize'), 'sources.builderMaximized');
      this.sizeButton.hidden = false;
    } catch {
      // Optional SmartBrowser window controls must not prevent editing.
    }
  }

  error(error) {
    if (this.closed) return;
    this.alert.textContent = t('unavailable') + (error?.message ? ' ' + error.message : ''); this.alert.hidden = false;
    this.embedding?.onError?.(error);
  }

  field(label, object, key, numeric = false) {
    const wrap = el('label', 'sources-field'); wrap.append(el('span', '', label));
    const input = el('input'); input.type = numeric ? 'number' : 'text'; input.value = object[key];
    if (numeric) { input.min = '0'; input.max = '500'; input.step = '1'; input.required = true; }
    else input.maxLength = 500;
    input.addEventListener('input', () => { object[key] = numeric ? Number(input.value) : input.value; this.refreshHeaders(); });
    wrap.append(input); return wrap;
  }

  selectField(label, object, key, values) {
    const wrap = el('label', 'sources-field'); wrap.append(el('span', '', label));
    const input = el('select');
    for (const value of values) { const option = el('option', '', t(value)); option.value = value; input.append(option); }
    input.value = object[key]; input.addEventListener('change', () => { object[key] = input.value; this.refreshDisplay(); this.refreshHeaders(); });
    wrap.append(input); return wrap;
  }

  commonOrder() { return this.config.display === 'merged' && this.config.ordering.mode === 'global'; }
  canOrder(block) { return !this.commonOrder(); }

  presentation(settings) {
    const label = el('label', 'sources-field'); label.append(el('span', '', t('layout')));
    const select = el('select');
    for (const layout of LAYOUTS) { const option = el('option', '', t(layout)); option.value = layout; select.append(option); }
    select.value = settings.layout;
    const menu = el('details', 'sources-layout-menu'), summary = el('summary');
    summary.setAttribute('role', 'button'); summary.setAttribute('aria-label', t('layout'));
    const icon = el('span'), text = el('span'), arrow = el('span', 'fas fa-chevron-down');
    icon.setAttribute('aria-hidden', 'true'); arrow.setAttribute('aria-hidden', 'true'); summary.append(icon, text, arrow);
    const choices = el('div', 'sources-layout-options'); choices.setAttribute('role', 'listbox'); choices.setAttribute('aria-label', t('layout'));
    for (const layout of LAYOUTS) {
      const choice = button(t(layout), layoutIcons[layout], () => {
        if (select.disabled) return;
        select.value = layout; select.dispatchEvent(new select.ownerDocument.defaultView.Event('change', { bubbles: true })); menu.open = false; summary.focus();
      });
      choice.setAttribute('role', 'option'); choice.dataset.layout = layout; choice.append(document.createTextNode(t(layout))); choices.append(choice);
    }
    const refresh = () => {
      icon.className = 'fas fa-' + layoutIcons[select.value]; text.textContent = t(select.value);
      choices.querySelectorAll('[data-layout]').forEach(choice => choice.setAttribute('aria-selected', String(choice.dataset.layout === select.value)));
    };
    select.addEventListener('change', () => { settings.layout = select.value; refresh(); this.refreshHeaders(); });
    menu.addEventListener('toggle', () => summary.setAttribute('aria-expanded', String(menu.open)));
    summary.addEventListener('click', event => { if (select.disabled) event.preventDefault(); });
    menu.addEventListener('keydown', event => {
      if (event.key === 'Escape') { event.preventDefault(); menu.open = false; summary.focus(); return; }
      if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key) || select.disabled) return;
      event.preventDefault(); menu.open = true;
      const buttons = [...choices.children], index = buttons.indexOf(document.activeElement);
      buttons[event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1 : (index + (event.key === 'ArrowUp' ? -1 : 1) + buttons.length) % buttons.length].focus();
    });
    select.hidden = true; menu.append(summary, choices); refresh(); label.append(select, menu);
    return 'limit' in settings ? [label, this.field(t('limit'), settings, 'limit', true)] : [label];
  }

  refreshDisplay() {
    this.modes.forEach(choice => choice.setAttribute('aria-pressed', String(choice.dataset.mode === this.config.display)));
    this.containerSettings.hidden = this.config.display !== 'merged';
    this.mergeOrdering.hidden = this.config.display !== 'merged';
    this.mergeOrdering.querySelectorAll('select')[1].disabled = !this.commonOrder();
    this.mergeOrdering.lastElementChild.hidden = !this.commonOrder();
    this.orderingHint.hidden = this.commonOrder();
    for (const record of this.records.values()) {
      record.display.querySelector('select').disabled = this.config.display === 'merged';
      record.display.querySelector('summary').setAttribute('aria-disabled', String(this.config.display === 'merged'));
      if (this.config.display === 'merged') record.display.querySelector('details').open = false;
      record.display.hidden = this.config.display === 'merged';
      record.captionField.firstElementChild.textContent = t(this.config.display === 'merged' ? 'name' : 'caption');
      if (record.limitField) record.limitField.hidden = this.config.display === 'merged';
      record.settings.classList.toggle('sources-settings-merged', this.config.display === 'merged');
      record.orderField?.querySelector('select').toggleAttribute('disabled', this.commonOrder());
      if (record.orderField) record.orderField.hidden = this.commonOrder();
      record.filters.classList.toggle('sources-filter-single', [...record.filters.children].filter(child => !child.hidden).length === 1);
      if (record.collection && record.orderingEnabled !== this.canOrder(record.block)) this.mountSelection(record.block, record);
    }
    this.notifyChange();
  }

  renderBlocks() {
    for (const [block, record] of this.records) {
      if (!this.config.blocks.includes(block)) { record.collection?.destroy(); record.card.remove(); this.records.delete(block); }
    }
    this.config.blocks.forEach(block => {
      if (!this.records.has(block)) this.createBlock(block);
      const record = this.records.get(block);
      const current = this.list.children[this.config.blocks.indexOf(block)];
      if (current !== record.card) this.list.insertBefore(record.card, current || null);
      for (const key of Object.keys(record.filterFields)) this.setFilterSelection(block, key, record);
    });
    this.addButtons.forEach(command => command.disabled = this.config.blocks.length >= 30); this.refreshDisplay(); this.refreshHeaders();
    this.confirm.disabled = !this.config.blocks.length || !!this.pending;
  }

  refreshHeaders() {
    this.config.blocks.forEach((block, index) => {
      const record = this.records.get(block); if (!record) return;
      record.title.textContent = block.title || t('type_' + block.type);
      record.toggle.setAttribute('aria-label', record.title.textContent);
      record.summary.textContent = [t(this.config.display === 'merged' ? this.config.presentation.layout : block.presentation.layout),
        t(block.type), this.items(block, block.type).length + ' ' + t('selected')].join(' · ');
      record.up.disabled = index === 0; record.down.disabled = index === this.config.blocks.length - 1;
      record.remove.disabled = false;
    });
    this.notifyChange();
  }

  move(block, delta) {
    const index = this.config.blocks.indexOf(block), next = index + delta;
    if (next < 0 || next >= this.config.blocks.length) return;
    [this.config.blocks[index], this.config.blocks[next]] = [this.config.blocks[next], this.config.blocks[index]]; this.renderBlocks();
  }

  createBlock(block) {
    const card = el('section', 'sources-block');
    card.dataset.type = block.type;
    const heading = el('header', 'sources-block-heading');
    const title = el('strong'), summary = el('span', 'sources-block-summary');
    const toggle = button(t('untitled'), 'chevron-down', () => {
      content.hidden = !content.hidden; toggle.setAttribute('aria-expanded', String(!content.hidden));
      toggle.firstElementChild.className = 'fas fa-' + (content.hidden ? 'chevron-right' : 'chevron-down');
    });
    toggle.classList.add('sources-block-toggle'); toggle.setAttribute('aria-expanded', 'true');
    const identity = el('span', 'sources-block-identity'); identity.append(title, summary); toggle.append(identity);
    const up = button(t('up'), 'arrow-up', () => this.move(block, -1));
    const down = button(t('down'), 'arrow-down', () => this.move(block, 1));
    const menu = el('details', 'sources-block-menu'), menuToggle = el('summary');
    menuToggle.title = t('actions'); menuToggle.setAttribute('aria-label', t('actions')); menuToggle.append(el('span', 'fas fa-ellipsis-v'));
    const commands = el('div', 'sources-block-actions');
    const duplicate = button(t('duplicate'), 'clone', () => {
      if (this.config.blocks.length >= 30) return;
      const index = this.config.blocks.indexOf(block); this.config.blocks.splice(index + 1, 0, structuredClone(block)); menu.open = false; this.renderBlocks();
    }); duplicate.append(document.createTextNode(t('duplicate')));
    const remove = button(t('delete'), 'trash', () => {
      this.config.blocks.splice(this.config.blocks.indexOf(block), 1); this.renderBlocks();
    }); remove.classList.add('sources-block-delete'); commands.append(duplicate); menu.append(menuToggle, commands);
    heading.append(toggle, up, down, remove, menu);
    const content = el('div', 'sources-block-content');
    const captionField = this.field(t('caption'), block, 'title');
    const display = this.presentation(block.presentation)[0];
    const settings = el('div', 'sources-settings sources-block-settings');
    settings.append(captionField, display);
    const limitField = block.type === 'articles' ? null : this.field(t('limit'), block.filters, 'limit', true);
    if (limitField) settings.append(limitField);
    else settings.classList.add('sources-settings-manual');
    const collectionHost = el('div', 'sources-collection sb-selection-field');
    const advanced = el('details', 'sources-advanced'); advanced.open = true; advanced.hidden = block.type === 'articles';
    const filterSummary = el('summary'); filterSummary.setAttribute('aria-expanded', 'true');
    const filterIcon = el('span', 'fas fa-chevron-down'); filterIcon.setAttribute('aria-hidden', 'true');
    filterSummary.append(filterIcon, document.createTextNode(t('filters'))); advanced.append(filterSummary);
    advanced.addEventListener('toggle', () => {
      filterIcon.className = 'fas fa-' + (advanced.open ? 'chevron-down' : 'chevron-right');
      filterSummary.setAttribute('aria-expanded', String(advanced.open));
    });
    const filters = el('div', 'sources-filter-actions');
    const record = { block, card, title, summary, toggle, content, up, down, remove, collectionHost, display, captionField, limitField, settings, filters, advanced,
      filterFields: {}, titleInput: captionField.querySelector('input') };
    if (block.type !== 'articles') {
      const other = block.type === 'categories' ? 'tags' : 'categories';
      filters.append(this.filterField(block, other, record));
      record.orderField = this.selectField(t('order'), block.filters, 'order', ORDERS);
      filters.append(record.orderField);
      if (options().taxonomyEditor === 'compact') {
        const primary = this.filterField(block, block.type, record); primary.firstElementChild.hidden = true;
        record.filterFields[block.type].input.setAttribute('aria-label', t(block.type)); collectionHost.append(primary);
      }
    }
    advanced.append(filters);
    content.append(settings, el('h3', 'sources-section-label', t(block.type)), collectionHost, advanced);
    card.append(heading, content);
    this.records.set(block, record); this.list.append(card);
    this.mountSelection(block, record);
  }

  items(block, key) { return key === 'articles' ? block.articles : block.filters[key]; }

  mountSelection(block, record) {
    const generation = Symbol(); record.generation = generation;
    record.collection?.destroy(); record.collection = null;
    if (block.type !== 'articles' && options().taxonomyEditor === 'compact') return;
    if (!window.SmartBrowser?.mountCollection || !window.SmartBrowserPicker?.open) { this.error(new Error('SmartBrowser is required.')); return; }
    record.orderingEnabled = this.canOrder(block);
    try {
      record.collection = window.SmartBrowser.mountCollection(record.collectionHost, {
        adapter: block.type, referenceItems: true, allowedAdapters: [block.type], homogeneous: true, multiple: true,
        pickerUrl: window.Joomla?.getOptions('com_smartbrowser.picker', {})?.url,
        allowedResourceTypes: [prefix(block.type)], selectionTarget: block.type === 'articles' ? 'item' : 'node',
        items: references(this.items(block, block.type), block.type), allowRemove: true, allowOrdering: record.orderingEnabled, contextActions: false, layout: 'grid',
        selectedResources: true, informationPanel: true, selectionUseData: 'editable', selectionProfile: usageProfile(block.type),
        viewPreferenceKey: 'supjx.sources.selection.' + block.type, maxSelections: 500,
        addLabel: t('add_' + block.type), defaultResourceActionId: 'collectionReplace',
        onChange: ({ items }) => {
          if (this.closed || record.generation !== generation || !this.config.blocks.includes(block)) return;
          try { const values = selectionValues(items, block.type); if (block.type === 'articles') block.articles = values; else block.filters[block.type] = values; this.refreshHeaders(); } catch (error) { this.error(error); }
        },
        onError: error => { if (record.generation === generation && this.config.blocks.includes(block)) this.error(error); },
      });
      Promise.resolve(record.collection.ready).catch(error => { if (record.generation === generation && this.config.blocks.includes(block)) this.error(error); });
    } catch (error) { this.error(error); }
  }

  filterField(block, key, record) {
    const wrap = el('div', 'sources-field sources-filter-field');
    const label = el('label', '', t(key));
    const row = el('div', 'sources-filter-row'), host = el('div', 'sources-filter-input');
    const template = document.createElement('template'); template.innerHTML = options().filterFields?.[key] || '';
    const widget = template.content.querySelector('joomla-field-fancy-select') || template.content.querySelector('select') || el('select');
    const input = widget.matches('select') ? widget : widget.querySelector('select');
    if (!input) throw new Error('Invalid native filter field.');
    input.multiple = true; input.id = 'sources-filter-' + ++fieldSequence; label.htmlFor = input.id;
    widget.querySelectorAll('[id]').forEach(node => { if (node !== input) node.removeAttribute('id'); });
    widget.removeAttribute('name'); widget.querySelectorAll('[name]').forEach(node => node.removeAttribute('name'));
    record.filterFields[key] = { widget, input };
    this.setFilterSelection(block, key, record);
    input.addEventListener('change', () => {
      if (record.syncingFilters || this.closed) return;
      const values = widget.choicesInstance ? widget.choicesInstance.getValue(true) : Array.from(input.selectedOptions, option => option.value);
      const previous = new Map(block.filters[key].map(value => [valueId(value), value]));
      block.filters[key] = values.map(value => previous.get(Number(value)) ?? Number(value)); this.refreshHeaders();
    });
    host.append(widget);
    const picker = button(t(key === 'tags' ? 'advancedTags' : 'advancedCategories'), 'ellipsis-h', () => this.pick(block, key, record));
    picker.classList.add('sources-filter-picker'); picker.dataset.filterPicker = key;
    record.filterFields[key].picker = picker;
    row.append(host, picker); wrap.append(label, row); return wrap;
  }

  setFilterSelection(block, key, record, resources = []) {
    const { input, widget } = record.filterFields[key];
    const selected = new Set(storedIds(block.filters[key]).map(String));
    const titles = new Map(resources.map(resource => [String(resource.id).split(':').pop(), resource.title]));
    for (const id of selected) {
      if (!Array.from(input.options).some(option => option.value === id)) {
        const option = el('option', '', titles.get(id) || '#' + id); option.value = id; input.append(option);
      }
    }
    record.syncingFilters = true;
    try {
      for (const option of input.options) {
        if (titles.has(option.value)) option.textContent = titles.get(option.value);
        option.defaultSelected = selected.has(option.value); option.selected = selected.has(option.value);
      }
      if (widget.choicesInstance) {
        const ranks = new Map([...selected].map((id, index) => [id, index]));
        const choices = Array.from(input.options, option => ({ value: option.value, label: option.textContent, selected: option.selected, disabled: option.disabled }))
          .sort((a, b) => (ranks.get(a.value) ?? 500) - (ranks.get(b.value) ?? 500));
        widget.choicesInstance.clearStore(); widget.choicesInstance.setChoices(choices, 'value', 'label', true);
      }
    } finally { record.syncingFilters = false; }
  }

  async pick(block, key, record, resource = null) {
    if (this.pending || !window.SmartBrowserPicker?.open) return;
    if (key === block.type && record.collection) {
      this.pending++; this.confirm.disabled = true; this.notifyChange();
      try {
        if (resource) await record.collection.editSelection(resource);
        else await record.collection.add();
      } catch (error) { this.error(error); }
      finally {
        this.pending--; this.confirm.disabled = !this.config.blocks.length; this.notifyChange();
      }
      return;
    }
    // Compact taxonomy fields and secondary filters edit their own complete criteria list.
    const current = this.items(block, key);
    const adapter = key === 'categories' ? 'categories' : key;
    const prefix = key === 'categories' ? 'category' : key === 'tags' ? 'tag' : 'article';
    const snapshot = structuredClone(current);
    this.pending++; this.confirm.disabled = true;
    this.notifyChange();
    try {
      const result = await window.SmartBrowserPicker.open({ adapter, allowedAdapters: [adapter],
        resultFormat: 'collection', multiple: true, homogeneous: true, selectionTarget: key === 'articles' ? 'item' : 'node',
        allowedResourceTypes: [prefix],
        initialCollection: references(current, key), selectionUseData: 'editable', selectionProfile: usageProfile(key), maxSelections: 500,
      });
      if (!result || this.closed) return;
      const latest = key === 'articles' ? block.articles : block.filters[key];
      if (JSON.stringify(latest) !== JSON.stringify(snapshot)) throw new Error(t('conflict'));
      {
        const picked = selectionValues(result.items, key);
        const values = picked;
        if (key === 'articles') block.articles = values; else block.filters[key] = values;
        if (key === block.type) await record.collection?.setItems(references(values, key));
        if (record.filterFields[key]) this.setFilterSelection(block, key, record, result.resources || []);
      }
      this.refreshHeaders();
    } catch (error) { this.error(error); }
    finally {
      this.pending--; this.confirm.disabled = !this.config.blocks.length;
      this.notifyChange();
      if (!this.closed) record.filterFields[key]?.picker.focus();
    }
  }
}
