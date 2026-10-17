// Exercise the installed SmartBrowser workflow, not a second Sources merge implementation.
import { createCollectionPicker } from '../../pkg_smartbrowser/resources/js/core/collectionPicker.js';
import { referenceKey } from '../../pkg_smartbrowser/resources/js/core/selectionIdentity.js';

export function mountSharedCollection(host, config) {
  let items = structuredClone(config.items);
  const instance = { config, updates: [], destroyed: false, ready: Promise.resolve() };
  const model = {
    revision: { value: 0 }, browser: { state: {} },
    getItems: () => structuredClone(items),
    async setItems(next, detail) {
      items = structuredClone(next); model.revision.value++;
      instance.updates.push(items);
      if (detail) config.onChange({ items: model.getItems(), ...detail });
    },
  };
  const picker = createCollectionPicker({
    config, model, isDisposed: () => instance.destroyed,
    open: options => window.SmartBrowserPicker.open(options),
    api: { async collection(next) {
      return { resources: next.map(item => ({
        ...item.selection, selection: item.selection, selectionKey: referenceKey(item.selection),
        type: { articles: 'article', categories: 'category', tags: 'tag' }[config.adapter],
        kind: config.selectionTarget || 'item',
      })) };
    } },
  });
  return Object.assign(instance, picker, {
    setItems: model.setItems,
    destroy() { instance.destroyed = true; },
  });
}
