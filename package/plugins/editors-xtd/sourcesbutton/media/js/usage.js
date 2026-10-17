export const usageProfile = key => ({
  ...(key === 'articles' ? { 'article.displayedName': {} } : {}),
  'visual.thumbnailOverride': {},
  'visual.iconOverride': { presentation: 'secondary' },
  'visual.presentation': { presentation: 'secondary' },
});
const prefixes = { articles: 'article', categories: 'category', tags: 'tag' };
function jsonValue(value, depth = 0, budget = { nodes: 0 }) {
  if (++budget.nodes > 8192 || depth > 20) throw new Error('Sources extension data is too complex.');
  if (value === null || typeof value === 'string' || typeof value === 'boolean' || typeof value === 'number' && Number.isFinite(value)) return;
  if (!value || typeof value !== 'object' || !Array.isArray(value) && Object.prototype.toString.call(value) !== '[object Object]') throw new Error('Invalid Sources extension data.');
  for (const child of Object.values(value)) jsonValue(child, depth + 1, budget);
}
export function validateUsage(value, adapter) {
  jsonValue(value);
  if (!value || typeof value !== 'object' || Array.isArray(value) || Object.keys(value).length > 100
    || new TextEncoder().encode(JSON.stringify(value)).length > 65536) throw new Error('Invalid Sources usage.');
  const result = {};
  for (const [key, data] of Object.entries(value)) {
    if (!Object.hasOwn(usageProfile(adapter), key) && !/^ext\.[a-zA-Z][a-zA-Z0-9_-]*(?:\.[a-zA-Z][a-zA-Z0-9_-]*)*$/.test(key)) throw new Error('Unsupported Sources usage.');
    if (data === null) { if (key.startsWith('ext.')) result[key] = null; continue; }
    if (key === 'article.displayedName' && (typeof data !== 'string' || [...data].length > 255)) throw new Error('Invalid Sources displayed name.');
    if (key === 'visual.presentation' && !['thumbnail', 'icon', 'both', ''].includes(data)) throw new Error('Invalid Sources visual presentation.');
    if (key === 'visual.iconOverride' && (typeof data !== 'string' || data.length > 160 || data !== '' && !/^[a-zA-Z0-9_-]+(?: [a-zA-Z0-9_-]+)*$/.test(data))) throw new Error('Invalid Sources icon.');
    if (key === 'visual.thumbnailOverride' && (!data || typeof data !== 'object' || Array.isArray(data)
      || Object.keys(data).some(key => !['adapter', 'id'].includes(key)) || data.adapter !== 'media'
      || typeof data.id !== 'string' || !data.id.includes(':') || new TextEncoder().encode(data.id).length > 2048 || /[\u0000-\u001f\u007f]/.test(data.id))) throw new Error('Invalid Sources thumbnail.');
    if (key === 'ext.address' && (typeof data !== 'string' || data.length > 2048 || /[\u0000-\u001f\u007f]|^\s*(?:javascript|data|vbscript):/i.test(data))) throw new Error('Invalid Sources extension address.');
    result[key] = structuredClone(data);
  }
  return result;
}
export function selectionValues(values, adapter) {
  if (!Array.isArray(values) || values.length > 500 || !prefixes[adapter]) throw new Error('Invalid Sources selection.');
  const seen = new Set(), result = [];
  for (const value of values) {
    let id, usage = {};
    if (Number.isInteger(value)) id = value;
    else {
      if (!value || typeof value !== 'object' || Array.isArray(value) || Object.keys(value).some(key => !['selection', 'usage'].includes(key))) throw new Error('Invalid Sources entry.');
      const reference = value.selection;
      if (!reference || typeof reference !== 'object' || Object.keys(reference).some(key => !['adapter', 'id'].includes(key))
        || reference.adapter !== adapter || !new RegExp('^' + prefixes[adapter] + ':[1-9]\\d*$').test(reference.id)) throw new Error('Invalid Sources reference.');
      id = Number(reference.id.split(':')[1]); usage = validateUsage(value.usage ?? {}, adapter);
    }
    if (!Number.isInteger(id) || id < 1 || id > 2147483647) throw new Error('Invalid Sources identifier.');
    if (seen.has(id)) continue;
    seen.add(id);
    result.push(Object.keys(usage).length ? { selection: { adapter, id: prefixes[adapter] + ':' + id }, usage } : id);
  }
  return result;
}
export const valueId = value => typeof value === 'number' ? value : Number(value.selection.id.split(':')[1]);
export const selectionIds = values => values.map(valueId);
export const referenceItems = (values, adapter) => selectionValues(values, adapter).map(value => typeof value === 'number'
  ? { selection: { adapter, id: prefixes[adapter] + ':' + value }, usage: {} } : structuredClone(value));
