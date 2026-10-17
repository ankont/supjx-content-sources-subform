import { selectionValues } from './usage.js';
export const MAX_BYTES = 131072;
export const LAYOUTS = ['cards', 'details', 'list', 'icons', 'contents'];
export const TYPES = ['articles', 'categories', 'tags'];
export const ORDERS = ['published-desc', 'published-asc', 'created-desc', 'created-asc', 'title-asc', 'title-desc', 'ordering-asc'];
const object = (value, keys) => {
  if (!value || typeof value !== 'object' || Array.isArray(value) || Object.keys(value).some(key => !keys.includes(key))) throw new Error('Unknown or invalid Sources properties.');
  return value;
};
const title = value => {
  if (typeof value !== 'string' || new TextEncoder().encode(value).length > 2000) throw new Error('Invalid Sources title.');
  return value;
};
const ids = value => {
  if (!Array.isArray(value) || value.length > 500 || value.some(id => !Number.isInteger(id) || id < 1 || id > 2147483647)) throw new Error('Invalid Sources selection.');
  return [...new Set(value)];
};
const limit = value => {
  if (!Number.isInteger(value) || value < 0 || value > 500) throw new Error('Invalid Sources limit.');
  return value;
};
const presentation = (value, container = true) => {
  object(value, container ? ['title', 'layout', 'limit'] : ['layout']);
  const { layout = 'cards' } = value;
  if (!LAYOUTS.includes(layout)) throw new Error('Invalid Sources presentation.');
  return container ? { title: title(value.title ?? ''), layout, limit: limit(value.limit ?? 0) } : { layout };
};
export function validate(value) {
  object(value, ['version', 'display', 'ordering', 'presentation', 'blocks']);
  if (value.version !== 2 || !['separate', 'merged'].includes(value.display)) throw new Error('Unsupported Sources version or display.');
  if (!Array.isArray(value.blocks) || !value.blocks.length || value.blocks.length > 30) throw new Error('Sources requires 1 to 30 blocks.');
  const ordering = object(value.ordering ?? {}, ['mode', 'order']);
  if (!['blocks', 'global'].includes(ordering.mode ?? 'blocks') || !ORDERS.includes(ordering.order ?? 'published-desc')) throw new Error('Invalid container ordering.');
  return { version: 2, display: value.display, ordering: { mode: ordering.mode ?? 'blocks', order: ordering.order ?? 'published-desc' }, presentation: presentation(value.presentation ?? {}), blocks: value.blocks.map(block => {
    object(block, ['type', 'name', 'title', 'articles', 'filters', 'presentation']);
    if (!TYPES.includes(block.type)) throw new Error('Invalid Sources block type.');
    const filters = object(block.filters ?? {}, ['tags', 'categories', 'order', 'limit', 'groupByCategory']);
    if (!ORDERS.includes(filters.order ?? 'published-desc') || typeof (filters.groupByCategory ?? false) !== 'boolean') throw new Error('Unsupported Sources ordering.');
    const articles = selectionValues(block.articles ?? [], 'articles'), tags = selectionValues(filters.tags ?? [], 'tags'), categories = selectionValues(filters.categories ?? [], 'categories');
    if (block.type === 'articles' ? tags.length || categories.length : articles.length) throw new Error('Mixed Sources blocks are not supported.');
    if (block.type !== 'categories' && filters.groupByCategory === true) throw new Error('Category grouping requires a category block.');
    if (block.name !== undefined) title(block.name);
    return { type: block.type, title: title(block.title ?? '') || title(block.name ?? ''), articles, filters: {
      tags, categories, order: filters.order ?? 'published-desc', limit: limit(filters.limit ?? 0),
    }, presentation: presentation(block.presentation ?? {}, false) };
  }) };
}
export const newBlock = (type = 'articles') => ({ type, title: '', articles: [], filters: { tags: [], categories: [], order: 'published-desc', limit: type === 'articles' ? 0 : 16 }, presentation: { layout: 'cards' } });
export const newContainer = () => ({ version: 2, display: 'separate', ordering: { mode: 'blocks', order: 'published-desc' }, presentation: { title: '', layout: 'cards', limit: 18 }, blocks: [newBlock()] });
export function serialize(value) {
  // Normal JSON escapes only for HTML-sensitive text; no opaque encoding or editor markup in storage.
  const json = JSON.stringify(validate(value)).replace(/[<>&\u2028\u2029]/g, char => '\\u' + char.charCodeAt(0).toString(16).padStart(4, '0'));
  if (new TextEncoder().encode(json).length > MAX_BYTES) throw new Error('Sources configuration is too large.');
  return '{sources:' + json + '}';
}
export function scan(text) {
  const tokens = [];
  let offset = 0, start;
  let budget = Math.max(text.length, MAX_BYTES) * 4;
  while ((start = text.indexOf('{sources:', offset)) !== -1) {
    const markup = skipMarkup(text, offset, start);
    if (markup > start) { offset = markup; continue; }
    let begin = start + 9;
    while (/\s/.test(text[begin] || '') && begin < text.length) begin++;
    if (text[begin] !== '{') { offset = start + 9; continue; }
    let depth = 0, quoted = false, escaped = false, end = -1;
    for (let i = begin; i < Math.min(text.length, begin + MAX_BYTES + 1); i++) {
      if (--budget <= 0) break;
      const char = text[i];
      if (quoted) {
        if (escaped) escaped = false;
        else if (char === '\\') escaped = true;
        else if (char === '"') quoted = false;
      } else if (char === '"') quoted = true;
      else if (char === '{' || char === '[') depth++;
      else if (char === '}' || char === ']') { if (--depth === 0) { end = i + 1; break; } }
    }
    if (budget <= 0 || end === -1) break;
    let close = end;
    while (end !== -1 && close < text.length && /\s/.test(text[close])) close++;
    if (end !== -1 && text[close] === '}') {
      const raw = text.slice(start, close + 1), json = text.slice(begin, end);
      let config = null;
      try { if (new TextEncoder().encode(json).length <= MAX_BYTES) config = validate(JSON.parse(json)); } catch { /* Invalid tokens stay untouched. */ }
      tokens.push({ start, end: close + 1, raw, config });
      offset = close + 1;
    } else break;
  }
  return tokens;
}
function skipMarkup(text, from, until) {
  let tag;
  while ((tag = text.indexOf('<', from)) !== -1 && tag < until) {
    const match = /^<\/?([a-z][a-z0-9:-]*)\b/i.exec(text.slice(tag, tag + 150));
    if (text.startsWith('<!--', tag)) {
      const end = text.indexOf('-->', tag + 4); from = end === -1 ? text.length : end + 3;
    } else if (match) {
      let quote = null, end = text.length - 1;
      for (let i = tag + 1; i < text.length; i++) {
        const char = text[i];
        if (quote !== null) { if (char === quote) quote = null; }
        else if (char === '"' || char === "'") quote = char;
        else if (char === '>') { end = i; break; }
      }
      from = end + 1;
      if (text[tag + 1] !== '/' && ['script', 'style', 'pre', 'code', 'textarea'].includes(match[1].toLowerCase())) {
        const close = text.toLowerCase().indexOf('</' + match[1].toLowerCase(), from);
        const endTag = close === -1 ? -1 : text.indexOf('>', close); from = endTag === -1 ? text.length : endTag + 1;
      }
    } else from = tag + 1;
    if (from > until) return from;
  }
  return until;
}
export function articleIds(items) {
  return ids([...new Set(items.map(item => {
    const reference = item?.selection ?? item;
    if (reference?.adapter && reference.adapter !== 'articles') throw new Error('Sources accepts Articles only.');
    const value = typeof reference === 'object' ? reference.id : reference;
    if (!/^(?:article:)?[1-9]\d*$/.test(String(value))) throw new Error('Invalid article reference.');
    return Number(String(value).replace(/^article:/, ''));
  }))]);
}
