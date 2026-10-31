const presentationOverrides = new Map();
const presentationListeners = new Map();
const presentationDocuments = new WeakMap();

export function validatePresentation(presentation) {
  if (!['site', 'email'].includes(presentation)) throw new TypeError('Sources presentation must be site or email.');
  return presentation;
}

export function editorPresentationId(editor) {
  return typeof editor === 'string' ? editor : editor?.getId?.() || editor?.id || null;
}

export function getEditorPresentation(editor) {
  const id = editorPresentationId(editor);
  const configured = window.Joomla?.getOptions('plg_editorsxtd_sourcesbutton.preview', {})?.editors?.[id];
  return validatePresentation(presentationOverrides.get(id) ?? configured?.presentation ?? 'site');
}

export function setEditorPresentation(editor, presentation) {
  const id = editorPresentationId(editor);
  if (!id) throw new TypeError('An explicit Sources editor ID is required.');
  presentation = validatePresentation(presentation);
  const previous = getEditorPresentation(id); presentationOverrides.set(id, presentation);
  if (previous !== presentation) for (const notify of presentationListeners.get(id) || []) notify(presentation);
}

export function observeEditorPresentation(editor, callback) {
  const id = editorPresentationId(editor); if (!id) return () => {};
  if (!presentationListeners.has(id)) presentationListeners.set(id, new Set());
  presentationListeners.get(id).add(callback);
  return () => { const callbacks = presentationListeners.get(id); callbacks?.delete(callback); if (!callbacks?.size) presentationListeners.delete(id); };
}

export function bindPresentationDocument(doc, editor) {
  if (doc) presentationDocuments.set(doc, editorPresentationId(editor));
}

export function documentPresentation(doc) {
  return getEditorPresentation(presentationDocuments.get(doc));
}
