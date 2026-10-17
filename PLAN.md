# Sources Package Plan

_Last updated: 2026-10-17_

### 4.0.0 Major Release

- Publish the complete Sources redesign as 4.0.0; all packaged plugin versions aligned.
- Include inline JSON Containers, shared Builder/optional Sources field, SmartBrowser shared Picker/output and overrideable sources.item.
- Preserve schema version 2 and temporary legacy rendering; require SmartBrowser 3.16.0+.
- Upgrade guidance and known template/frontend-form limitations documented in docs/RELEASE_4.0.0.md.

### 3.3.1 Overrideable Sources Item

- Moved default item composition into packaged layouts/sources/item.php.
- Pass item, layout, prepared selected, purpose and parent_article_id to sources.item.
- Default fallback comes after normal Joomla template/parent paths; backend preview also checks the default site template override.
- Sources owns access/preparation, recursion and buffer cleanup around the override.
- No visual pre-render or finalization is imposed; overrides can call SelectionOutputRenderer exactly where needed.
- Tests cover actual Joomla FileLayout priority, default behavior, custom/no-visual output, one presentation, data contract, recursion and error recovery.

### 3.3.0 Shared Resource Output

- Require SmartBrowser 3.16.0; use readonly prepared selections and SelectionOutputRenderer per resource.
- Confirmed smartbrowser.resource -> template catichisi.identity composition contract; generic fallback remains SmartBrowser-owned.
- Removed Sources image/icon composition, article image rewriting and direct legacy card/content layout calls from modern output.
- All five layout shells, block ordering/filtering, summaries and Contents bodies remain Sources-owned; stored schema and legacy bridge unchanged.
- AJAX previews pass purpose=preview, carry presentation styles, retain server image markers and ignore stale responses.
- Shared PHP renderer tests cover generic/override output, all modes, unavailable selections, Usage/metadata and exactly-once hooks.
- Site-specific article action bars/chips from old library layouts are not part of the new resource contract; see docs/SHARED_OUTPUT.md.

### 3.2.6 SmartBrowser Shared Picker Operations

- Require SmartBrowser 3.14.0+; primary Collections supply pickerUrl and adapter/type/target constraints.
- Removed Sources onAdd/onReplace overrides and per-item replacement merging.
- Collection onChange stores the full selection once, retaining Usage; independent compact/filter pickers remain.
- Tests exercise actual sibling SmartBrowser shared Picker code for all three adapters, cancellation, duplicate Add, full replacement, focus, extension preservation and errors.
- No JSON schema, frontend rendering or legacy-compatibility changes.

### 3.2.5 Field Error Presentation

- SmartBrowser/Builder errors no longer automatically expose the raw JSON textarea.
- Original data remains accessible through a closed, translated advanced disclosure.
- Storage, validation and compatibility behavior remain unchanged.

### 3.2.4 Temporary Legacy Rendering

- Reintroduced read-only legacy Custom Field rendering via the existing template sources.php.
- Compatibility defaults on, with a content-plugin switch; supports numbered/rest tokens and append.
- Modern inline/field configurations take precedence; no old definitions or values are deleted.
- Native forms and the modern Builder remain unchanged. Frontend form binding is a separate issue.

### 3.2.3 Details Follow-Up and Preview CSS Cache

- Found an unversioned iframe CSS URL: package upgrades could leave cached preview styles even when the outer page assets refreshed.
- CSS content hash now versions both the outer Builder stylesheet and iframe contentCss URL; reused editor documents refresh stale stylesheet links.
- Rendered blocks expose additive sources-layout--LAYOUT classes. Preview Details no longer depends on direct-child template markup; wrappers/cards have bounded full widths.
- Added actual SourcesButton asset-bootstrap regression plus real-editor stale-link/wrapped-card tests. No template edits or claim of authenticated live-site reproduction.
- Corrected the JCE fixture's default 300px iframe and explicitly assert a preview wider than 767px in desktop runs; checked both wide and narrow layouts and SmartCrop bounds.

### 3.2.2 Stable Preview Columns With Headings

- Stable scrollbar gutters in the bounded editor preview and field Preview document prevent vertical-scroll onset from reducing available card width.
- Browser regression adds/removes a heading across the preview's 600px scroll threshold and verifies equal width/column count, with unchanged stored tokens.
- Preview-only change; no frontend layout, storage or selection semantics changed.

### 3.2.1 Editor Preview Dimensions

- Preview-only layout constraints for library cards, with full-width Details rows and container-width stacking instead of outer viewport breakpoints.
- Custom-field Preview iframe uses the same scoped surface class/rules. No frontend layout or stored JSON changes.
- Ordinary thumbnail bounds are constrained; SmartCrop frames and overscan are not overridden.
- Actual template CSS browser regressions cover 520/900px constraints, desktop/mobile, TinyMCE/JCE and Atum/Cassiopeia; tokens remain unchanged.
- Full site styling still requires the editor/preview document's configured stylesheets; automatic frontend-template asset loading is not introduced.

### 3.2.0 Selection Usage

- Editable Usage profiles in every Sources Collection View/Picker, including optional taxonomy pickers. Name/image primary; icon/presentation secondary and initially closed.
- Schema-v2 arrays accept old numeric IDs and canonical SmartBrowser entries carrying Usage. No parallel reference/Usage stores.
- Usage and ext.* metadata survive ordering, replacement, duplication, dropdown changes, inline serialization and native custom-field save/reopen.
- Article title/image overrides use clones and the real public SmartBrowser readonly resolution API. Native ACL/selection criteria remain unchanged.
- Template displayData includes sources_usage and sources_visual. Advanced visuals are implemented by the standalone fallback; existing template overrides must consume this contract themselves.
- SmartCrop effective-article-thumbnail editing remains extension-owned. Reserved metadata is preserved, not executed or interpreted.
- Added client/server parity, actual SmartBrowser PHP API, renderer and browser editable/Advanced UI tests.

### 3.1.0 Optional Sources Custom Field

- Added Fields - Sources as a third, optional packaged plugin; no automatic Field Group, category assignment, legacy conversion or data deletion.
- Shared SourcesBuilder supports embedded editing without its own nested form/dialog. Inline editing is unchanged.
- Native custom-field JSON is the sole persisted configuration for each field. Schema-v2 parsing, RAW filtering, validation, field edit permissions and readable-JSON fallback use Joomla Form APIs.
- Standard Joomla Fields rendering owns placement and access; new Sources fields default to After Display, without a second append hook.
- Preview renders the unsaved draft using the existing protected AJAX renderer, isolated in a script-disabled iframe.
- Tests cover real Joomla Form/Rule/asset APIs and embedded editing/preview/reload across frontend/backend, TinyMCE/JCE and desktop/mobile. No authenticated live-site installation claim.

### 3.0.5 Preview Lists and Initial Block

- Editor list previews now use bullet lists with padding and separated criteria.
- New Containers start with one specific-articles block; deleting the last block remains supported.

### 3.0.4 Editor Preview AjaxEvent Fix

- Root defect: the Sources AJAX listener is a modern SubscriberInterface handler. com_ajax reads the dispatched event's result, not its PHP return value. The handler now accepts AjaxEvent and calls addResult().
- Added real Joomla Dispatcher/AjaxEvent/JsonResponse regression, explicitly reproducing the old ignored return. App/session/rendering collaborators are fixtures; no live-site database claim.
- Preview diagnostics now distinguish permission/session, malformed/empty responses, unsupported config, disabled content plugin and rendering failures; private server details stay in logs.
- Fifteen automated tests plus browser error/recovery coverage across the eight editor/template/viewport combinations. Source JSON is unchanged on failure.

### 3.0.3 Block Sequence Note

- A translated note beside Merged ordering explains that each block is ordered separately when Block Sequence is selected. Common Ordering replaces the note with the existing sort selector; no storage or rendering changes.

### 3.0.2 Consolidated Ordering And Identity

- Removed Group by selected category UI/behavior. Category-and-article ordering uses Builder category sequence for category blocks; tag blocks and Common Ordering use Joomla category tree order, then article ordering/ID.
- Other sorts remain ungrouped. Tags remain AND; OR/grouped tag lists use separate merged blocks.
- Merged ignores all per-block limits and hides their controls; only the final Container limit applies after combining, deduplication and sorting. Separate limits are preserved. Internal candidate/request/output safety caps remain.
- One stored title: Label/Λεζάντα in Separate, Name/Όνομα in Merged. Header uses this value or the type fallback. Previous name/group fields are accepted only as deprecated inputs, not emitted.
- A single visible Filters/advanced control occupies the full row.
- Validation and browser regressions cover both merged modes, limit visibility, one-field naming, removed grouping and full-width controls.

### 3.0.1 Builder Clarity

- Fresh Builder drafts start with one specific-articles block; delete-last is allowed. Empty drafts cannot be inserted/updated as invalid Containers.
- Editor list previews use semantic bullet lists with padding and separated criteria; live rendering and token storage stay unchanged.
- Add actions have explicit Add/Προσθήκη wording and gray/bold block-header styling.
- Full Selection View (grid, preview, list and ordering) is shared across Articles/Categories/Tags, with per-adapter references.
- Manual blocks have no limit control and always include all accessible explicitly selected articles (bounded safety cap). Merged container limits still apply.
- Optional name is the administrative Title; existing title is now the Separate Label/Λεζάντα. Names never render.
- Merged hides block Layout/Label. Common order hides block order/grouping; Block Sequence hides the general order. Values are retained.
- Category grouping creates contiguous results, not new headings. Tags remain AND; their selection order does not change output semantics.
- Fourteen automated regressions include empty drafts, last deletion and name/label separation.

### 3.0.0 Typed Blocks / Schema 2

- Agreed architecture: three add actions for specific Articles, Articles from Categories and Articles from Tags; no mixed blocks.
- Dynamic primary criterion must be nonempty. Optional cross-filters are conjunctive; tags continue to use AND and categories match exact IDs.
- Category grouping respects category selection order; dynamic articles support publication, creation, title and Joomla ordering with ID tie-breakers.
- Merged containers choose block sequence or common ordering; common ordering disables ignored internal ordering controls but retains settings.
- Primary category/tag UI uses matching SmartBrowser Collections by default; a shared Button plugin setting switches both to compact native fields with node pickers. Optional filters remain dropdowns.
- Insert is native btn-success. No legacy inline migration/compatibility and no Custom Field deletions, per user direction.
- Twelve automated tests cover validation, both UI modes, typed queries, merging and ACL. Eight browser runs cover Atum/Cassiopeia, TinyMCE/JCE, desktop/mobile, node pickers, save/reopen, green Insert and dropdown hit-tests.

### 2.0.4 Native Controls And Atum

- Footer buttons now use only native Joomla btn classes and icon classes, not Sources button skins. Both frontend and backend inherit their respective template appearance.
- Scoped details resets neutralize Atum margins/padding and details-content overflow clipping for layout, advanced filters and block menus.
- Verification loads actual Atum and Cassiopeia styles with both TinyMCE/JCE on desktop/mobile.
- Article Selection Usage remains a SmartBrowser enhancement (agreed with user): displayed-name fallback, thumbnail/icon/both and SmartCrop. Sources follow-up must persist/apply usage rather than silently discard it. See docs/SMARTBROWSER_UI_FOLLOWUP.md.

### 2.0.3 Builder Details

- Greek title: Compose Sources (Σύνθεση πηγών); all titles remain language keys.
- Footer uses red cancel and icon submit actions; delete is directly beside reorder controls.
- Icon layout dropdown preserves native select value and the JSON schema.
- Reconcile connected native taxonomy widgets from the block draft; preserve default selections, clear Choices store before replacing choices, avoid needless DOM reconnects.
- Local dropdown stacking above Selection View; source controls recovered after editor sanitization with capture-phase click handling.
- Browser regressions cover Update/reopen native selections and real editor gear/mode clicks.
- Sources already isolates SmartBrowser view preferences with supjx.sources.selection; all Sources collections intentionally share that UI preference (not stored in content).
- URI editing and preview position presentation belong to SmartBrowser: see docs/SMARTBROWSER_UI_FOLLOWUP.md. No shared SmartBrowser code changed.

## Current Architecture - 2.0.0

### 2.0.2 Selection And Filter UI

- Sources Collections now use the same selected-resources presentation as SmartBrowser Fields, including Info panel and integrated Add/Replace actions; one persistent reference collection per block.
- Block Title/Layout/selection Limit use the same one-row layout as merged settings. The per-block layout remains visible but disabled in merged mode; its value is retained.
- Tags/categories use native Joomla Tag/Category fancy-select templates without submitted field names or new storage. Advanced ellipsis actions open the matching SmartBrowser adapters with node selection and synchronize the controls.
- Filters default open with an explicit rotating chevron. Block collapse now switches between right/down chevrons.
- Eleven automated tests pass, plus actual TinyMCE/JCE, Choices.js and SmartBrowser Selection/Picker browser checks across desktop/mobile. Verified picker-to-field and field-to-JSON synchronization, per-item replacement, unique control IDs and one-row alignment.

### 2.0.1 Asset Loading Fix

- Fixed Sources button and frontend CSS WebAsset registrations: Joomla's relative resolver inserts js/css itself.
- Confirmed the old paths resolve to an empty URL, explaining the missing sourcesbutton:insert handler.
- Added a real Joomla WebAssetItem/HTMLHelper regression test for root and subdirectory installations; all nine automated tests pass.
- The user identified the separate LOGIC_VERSION error as coming from the Pinterest Save Button browser extension; it is unrelated to Sources.

- Replaced external Custom Fields configuration with self-contained version 1 inline JSON Containers.
- Added bounded PHP/JS schema validation and nesting-aware token parsing, preserving invalid/future tokens.
- Sources button now opens a draft-based Container Builder: Separate/Merged, collapsible blocks, duplicate/delete/reorder, selection, presentation and filters.
- Integrated public SmartBrowser reference Collection/Picker APIs, initial selections, independent instances, unavailable references and native dialog focus handling.
- Kept visual chips and preview modes with real JSON restoration for source/save. Tested native JCE content dispatchers, Unicode serialization, cursor insertion, independent edits, cancellation, undo/redo and editor reload.
- Reused the resource layout pipeline and manual-first / all-tags matching behavior. Added explicit article/category/ancestor access, publication-window and language guards.
- Removed the native-subform/Classic bridge, numbered tokens, saved-field AJAX previews and automatic append. No installer deletes existing articles or Custom Field data.
- Remaining installation QA: real frontend/backend Joomla Save/Apply/Reload, article history, live database ACL/scheduling, site-specific layouts and full JCE profile/source-tab extensions.
- README and docs/inline-sources.schema.json describe the new contract. Sections below are historical 1.x notes, not requirements for 2.0.

## Goal

Make `pkg_sources` a complete Joomla authoring package for Sources blocks:

- `Content - Sources` owns token replacement and frontend rendering.
- `Button - Sources` owns the editor experience: inserting tokens, decorating existing tokens, and showing editor-only previews.
- Saved article content should remain token-based and compatible with `{sources}`, `{sources:N}`, and `{sources:rest}`.

## Current State

- The project has been reorganized as `pkg_sources`.
- The package installs two plugins:
  - `plg_content_sources`
  - `plg_editors_xtd_sourcesbutton`
- Current package version: `1.1.11`.
- Latest build artifact:

```text
build/output/pkg_sources_v1.1.11.zip
```

- Versions are bumped together in:
  - `package/pkg_sources.xml`
  - `package/plugins/content/sources/sources.xml`
  - `package/plugins/editors-xtd/sourcesbutton/sourcesbutton.xml`

## Completed

### Package Structure

- Converted the extension from a standalone content plugin layout to a package layout.
- Package manifest now installs the content plugin and the editors-xtd button plugin together.
- Package language files were added for `pkg_sources`.
- Build process creates `pkg_sources_vX.Y.Z.zip` under `build/output`.

### Content Plugin

- `Content - Sources` still owns frontend rendering.
- It replaces article tokens:
  - `{sources}`
  - `{sources:1}` / `{sources:N}`
  - `{sources:rest}`
- It can append rendered output when configured to do so and no token is present.
- It supports existing editor wrappers by reading the token from `data-sources-token` or wrapped token markup.
- Admin preview parsing was hardened in `1.1.11` so it can read more possible subform field names and value structures.
- **v1.1.11**: Added `onAjaxSources` frontend AJAX endpoint for render previews (see below).

### Button Plugin

- Added `Button - Sources` as an editors-xtd plugin.
- The button appears in TinyMCE and JCE.
- The button action is registered through Joomla's editor button API.
- The button now opens a token insertion dialog instead of silently inserting a default token.
- The dialog supports:
  - all blocks: `{sources}`
  - one block: `{sources:N}`
  - remaining blocks: `{sources:rest}`
- Existing tokens in the editor can be decorated as visual editor elements.
- The simple token badge remains editable in raw-token mode so authors can adjust token options by hand.

### Editor Preview Modes

The editor display settings now live in `Button - Sources`, because this plugin controls the live editor UI.

Implemented modes:

- `none`: leave/edit raw tokens.
- `placeholder`: show a compact placeholder title such as the Sources block label.
- `list_manual`: show manual selections plus automatic criteria summary.
- `list_full`: show resolved titles where possible.
- `render`: show rendered HTML preview from the server.

The placeholder title is shown as context for preview modes, so the author can still see what block is expected even before server preview finishes.

### Server-Side Preview

- Added an AJAX preview endpoint to `Button - Sources` via `onAjaxSourcesbutton`.
- The endpoint reads the saved article and its Sources subform field through Joomla field APIs.
- The preview requires a saved article ID. If the article is unsaved or the field data is unavailable, it shows the save notice.
- AJAX responses are now direct JSON responses, avoiding the previous `com_ajax` response wrapping issue.
- JavaScript cache busting was added with a media file version query string.
- Debug data is exposed in the browser console through `window.SuperSoftSourcesButtonDebug` and related last-response globals.

### Render HTML Preview (v1.1.11 — fixed)

The render preview went through several iterations:

1. **Problem**: Direct template include from admin context failed because `JPATH_THEMES` pointed to admin templates.
2. **Fix #1**: Resolved site template path via `JPATH_SITE` + DB query for default template. Template was found but rendered empty grid — Joomla's `subform_rows` had JSON string rawvalues (not decoded arrays), and `LayoutHelper::render('library.card')` couldn't find site template layouts from admin context.
3. **Final fix**: Replaced direct template include with an **HTTP request to the frontend**:
   - `Content - Sources` exposes `onAjaxSources` endpoint on the frontend via `com_ajax`.
   - `Button - Sources` calls `GET /index.php?option=com_ajax&plugin=sources&group=content&format=raw&article_id=X&indices=0,1&mode=all` from the admin.
   - The template runs in its natural site context — all layouts, models, CSS, and helpers work correctly.
   - On failure, diagnostic info (HTTP status code, response body) is included in the console debug message.

### Manual Selection Parsing

In `1.1.11`, preview parsing was broadened for manual selections:

- More possible field aliases are supported, including `block-manual-articles`, `selected-sources`, `selected-articles`, `articles`, `items`, and related hyphen/underscore variants.
- Values can be read from scalars, arrays, objects, `rawvalue`, `value`, `id`, nested structures, comma-separated strings, JSON strings, and labels ending in `[id]`.
- The AJAX debug message now includes `manual=...;keys=...` so field-name mismatches can be diagnosed quickly from the console.

### JCE Icon

- The JCE external button now shows an icon.
- The exact icon can still be refined later.

## Current Verification Status

- v1.1.11 installed and tested on new.catichisi.gr.
- All preview modes verified working: placeholder, list_manual, list_full, render.
- Render HTML preview correctly shows article cards with images from the frontend template.
- PHP lint was not run locally because `php` is not available in this machine's PATH.

---

## v1.2.0 — Polish Roadmap

### 1. Per-article preview mode switching

> Gear icon ή double-click πάνω στο sources block στον editor.

- Προσθήκη UI στοιχείου (⚙ icon πάνω-δεξιά ή context menu) πάνω στο rendered preview block.
- Αλλαγή preview mode on-the-fly: `placeholder` → `list_manual` → `list_full` → `render`.
- Η ρύθμιση plugin ορίζει το **default** mode. Ο editor μπορεί να το αλλάξει per-article/per-session.
- Η επιλογή αποθηκεύεται σε localStorage ή session (δεν αλλάζει το saved content).

**Αρχεία**: `sourcesbutton.js`, `sourcesbutton.css`

### 2. Full-width preview

> Το preview να παίρνει το πλάτος του editor, όπως θα φαίνεται στο πραγματικό άρθρο.

- Τα list/render preview blocks σήμερα είναι σε compact container. Πρέπει να γίνουν full-width μέσα στο editor area.
- Το **placeholder** badge μπορεί να μείνει compact/inline (δεν χρειάζεται full-width).

**Αρχεία**: `sourcesbutton.css`, πιθανώς `sourcesbutton.js` (wrapper markup)

### 3. Badge display → Placeholder text

> Η ρύθμιση Badge display να ελέγχει τι εμφανίζεται στο Placeholder mode.

- Σήμερα ελέγχει μόνο το compact badge text. Να αξιοποιηθεί πλήρως στο placeholder mode.
- Αλλαγή label/description στα language files για σαφήνεια.

**Αρχεία**: `sourcesbutton.xml`, `sourcesbutton.js`, language INI files

### 4. Badge label → Placeholder label

> Αντί "Suggested Sources Block", να εμφανίζει ό,τι ορίζει η ρύθμιση Badge label.

- Σήμερα default: "Sources". Αλλαγή σε κενό πεδίο με hint "Sources".
- Η τιμή ελέγχει το κείμενο στο placeholder mode.
- Ξεκαθάρισμα ότι δεν αλλάζει το token name (`{sources}` μένει σταθερό).

**Αρχεία**: `sourcesbutton.xml`, `sourcesbutton.js`, language INI files

### 5. Καλύτερα labels για preview modes

> Τα ονόματα `list_manual` / `list_full` δεν είναι κατανοητά στον editor.

Προτεινόμενα labels:

| Τιμή | Τρέχον label | Νέο label (EN) | Νέο label (EL) |
|------|-------------|----------------|----------------|
| `none` | Token badge only | Just {sources} token | Μόνο το {sources} token |
| `placeholder` | Placeholder | Placeholder badge | Ενδεικτική ετικέτα |
| `list_manual` | Manual + auto summary | Settings summary | Σύνοψη ρυθμίσεων |
| `list_full` | Full resolved titles | Resolved articles | Τελικά άρθρα |
| `render` | Full HTML preview | Live preview | Ζωντανό preview |

**Αρχεία**: language INI files (content + sourcesbutton), `sourcesbutton.xml`

### 6. Tags/Categories ονόματα + cleaner layout

> Στο list_manual mode, tags/categories εμφανίζονται ως IDs. Πρέπει να δείχνουν ονόματα.

- Fetch tag/category titles from DB στο `buildListPreview()` (SourcesButton.php).
- Αφαίρεση ή απλοποίηση sub-labels ("MANUAL SELECTIONS" / "AUTO CRITERIA") — π.χ.:
  - Χωρίς headers, απλά: `• Τίτλος 1  • Τίτλος 2  ─  Tags: Ζωγραφική · Κατηγορίες: ...`
  - Ή πιο subtle divider αντί bold sub-headings.

**Αρχεία**: `SourcesButton.php` (`buildListPreview`, `fetchTagTitles`, `fetchCategoryTitles`)

### 7–8. Heading tag/class config

> Ο τίτλος κάθε block (π.χ. "Σχετικά άρθρα") εμφανίζεται σε hardcoded `<h3>` στο template.

- Προσθήκη ρυθμίσεων στο **Content plugin** config:
  - **Heading tag**: dropdown (`h2` / `h3` / `h4` / `h5` / `div` / `none`)
  - **Heading CSS class**: text field (κενό = default template class)
- Αυτά περνάνε στο template ως μεταβλητές (`$sources_heading_tag`, `$sources_heading_class`).
- Αν tag = `none`, ο τίτλος δεν εμφανίζεται (ο editor βάζει δικό του πάνω από `{sources}`).
- Το template (`sources.php`) θα πρέπει να ενημερωθεί να τιμά αυτές τις μεταβλητές.

**Αρχεία**: `sources.xml`, `Sources.php`, language INI files, `sources.php` (template)

---

## Decisions

- Save raw tokens where possible; avoid persisting full visual preview HTML as article content.
- Require the article to be saved before server-side preview if reading unsaved subform data is brittle.
- Keep `Content - Sources` as the source of truth for token name, subform field name, and frontend rendering settings.
- Avoid deep JCE-specific work unless a generic Joomla editor-button implementation cannot cover the behavior.
- Token name `{sources}` is fixed — not configurable.
- Render HTML preview uses HTTP request to frontend (not direct template include from admin).

## Later Improvements

### Sources Builder - 1.3.0

- Implemented a shared frontend/backend Builder over the unchanged native Joomla subform.
- Added collapsible numbered blocks, add/duplicate/delete/reorder, grouped field controls, and a Classic form fallback.
- Integrated the public SmartBrowser Articles Collection View with one reusable instance per block; resource rendering, ordering, removal, and unavailable states stay owned by SmartBrowser.
- Add Sources uses the existing picker and appends/deduplicates its ordered selection. Existing Articles multi-selection preselection is not supported by the current public picker API.
- Native fields remain the submitted source of truth. No Sources button/modal changes, storage changes, or token changes.
- Passed seven synchronization/lifecycle tests using the real Joomla subform implementation, plus desktop/mobile Chrome checks using the actual SmartBrowser Collection renderer with mocked transport/picker.
- Remaining installation QA: frontend and backend Save/Apply/Reload, legacy articles, and actual site-specific fancy-select/SmartBrowser endpoints. Builder requires SmartBrowser 1.7.0+; unavailable integration keeps Classic accessible.

- Make the insertion dialog list actual saved Sources blocks with their titles.
- Allow choosing a block by title instead of manually entering a number.
- Show whether each block has manual selections and/or automatic criteria inside the insertion dialog.
- Consider a shared helper if parsing/rendering logic between the two plugins grows further.
- Add automated PHP lint/build checks once PHP is available in the local environment.
## Sources Builder Dialog Polish (2.0.2)

- Shared SmartBrowser dialog shell and public maximize/restore helper, with independent remembered size.
- Header window controls, accessible dialog title, scroll-lock cleanup and optional-helper fallback.
- Browser verification covers maximize/restore dimensions on desktop/mobile in TinyMCE and JCE.
