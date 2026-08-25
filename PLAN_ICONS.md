# Goal Description
Add per-token preview mode switching in the editor via a small inline toolbar, and add the same icons to the global plugin settings for better user understanding.

## Proposed Changes

### sourcesbutton.js
- Add data-sources-preview-mode attribute tracking.
- Inject a .sources-editor-token__toolbar containing 5 SVG icons into the header of token blocks.
- If a token is in 
one (raw token) mode, it is an editable text node, so a toolbar inside it isn't easily supported without breaking editability. We will allow switching TO 
one, but returning FROM 
one will require changing the global setting, or we can make 
one non-editable and just an inline element, but that breaks the core "editable token" concept. I will keep 
one as raw text, and the toolbar will only be visible when the token is rendered as a block or badge.
- Add event delegation in decorateDocument to handle clicks on the toolbar buttons, updating the token's attribute and re-triggering efreshPreview.

### sourcesbutton.xml
- Add SVG icons to the editor_preview_mode options to match the editor UI.

### CSS
- Add styles for .sources-editor-token__toolbar and .sources-editor-toolbar-btn.

