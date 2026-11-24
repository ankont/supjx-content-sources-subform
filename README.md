# SuperSoftJx - Sources 6.0.0

5.1.1 moves the private email setting to Sources Email 1.4.1. Enable it there explicitly if needed.

5.1.0 accepts host-neutral email preparation requests. Sources Email 1.4.0 keeps
the public v1 Bridge contract and adds opt-in, recipient-scoped private rendering
through v2. The central private-email switch defaults to off. See the
[email contract, capability discovery and caller examples](docs/EMAIL_OUTPUT.md).

5.0.0 adds consumer-scoped introduction Usage in SmartBrowser 6.1.0+, optional
per-category/tag grouping, and paired introduction/results layout boundaries.
Install the complete package including **System - SuperSoftJx - Sources SmartBrowser Usage**
(1.1.0), enabled automatically on a clean install. Sources Email is
1.4.1. Existing version-2 configuration remains supported without migration.
See [the 5.0.0 presentation and group contract](docs/INTRODUCTIONS.md#sources-500).

Empty Sources custom fields start without an automatically created Articles block.
Existing saved blocks and the editor-button dialog's default are unchanged.

The Builder remembers the last manual filter disclosure choice in the browser,
independently of block expansion. Automatic validation reveals do not change it.

Optional tag/category introductions use native titles, descriptions and images,
with site/email layout overrides. Introductions appear only at the root Sources
level; their descriptions may contain Sources without adding further introductions.
See [introduction layouts and consumer contract](docs/INTRODUCTIONS.md).

Unavailable resources now use the regular site/email item layouts. See
[consumer contract and migration notes](docs/UNAVAILABLE_PRESENTATION.md).

4.4.2 remembers the last Expand All / Collapse All command as a browser preference
for newly opened dialog and embedded Builders. Individual block toggles do not
change it. Default is expanded; new blocks remain expanded for immediate editing.
No expansion preference is written into Sources JSON. Blocked browser storage
falls back to a page-local preference without preventing editing.

4.4.1 distinguishes preview dependency failures for Content Sources and Sources
Email: missing, disabled, restricted, public_required, unavailable (runtime/cache)
and incomplete installation. Checks inspect registration/access without switching
identity or mutating the plugin cache. Authoring ACL and public-only email output
remain enforced; email never silently falls back to site presentation.
The Builder validates applicable owned inputs before schema serialization, disables
inapplicable numeric controls without discarding their values and reveals collapsed
invalid inputs. Only its own standalone inner form uses explicit validation;
the enclosing Joomla form's validation remains enabled. Numeric empty drafts no
longer silently become zero. Legacy manual-article limits are omitted on
Builder/embedded saves; dynamic and merged-list limits remain unchanged.

Local diagnosis found both dependencies enabled/public and available, so the
reported plugin_disabled cause was not reproduced. On the affected installation,
provide the new AJAX result code and the two plugins' Enabled/Access settings;
an unavailable code indicates enabled/accessible registration but runtime helper
unavailability. Do not change Access blindly or bypass the checks.

Verification: targeted native-browser numeric validation passes in eight
TinyMCE/JCE/theme/viewport configurations, plus embedded owned-field tests.
The full integration suite still stops at SmartBrowser's empty required
`sb-address-v-0` controls invalidating the outer form; Sources does not remove
their constraints or disable the outer form's native validation.

4.4.0 requires SmartBrowser 4.1.2+ for shared resource output. The Content plugin
has a presentation setting `show_unavailable`: empty string = Use Global (default),
`1` = Show, `0` = Hide. The native SmartBrowser Unavailablevisibility field displays
the current translated global Show/Hide value. No selections, Usage or JSON schema
are changed. Missing manual references retain their ordered identity, pass through
readonly preparation and reach the shared renderer as neutral unavailable entries;
no private titles, URLs, images or bodies are fetched to fill missing results.
Dynamic category/tag queries remain access/publication filtered, without synthetic
references. Site output and editor summaries/live previews share the setting.
Email missing references use the same neutral renderer when SmartBrowser is enabled;
available email articles retain the existing email layout pipeline and public policy.
Available `sources.item` overrides additionally receive the original `showUnavailable`
choice and should forward it in shared-renderer options without a boolean cast.
Unavailable entries go directly to the SmartBrowser renderer, never through article
template overrides or a Sources-owned notification renderer.

4.3.5 adds a visible translated Expand all / Collapse all label beside the arrows,
with responsive wrapping of the display toolbar on narrow screens.

4.3.4 uses a plain plus for Duplicate, a 180px minimum add-action width and an
Expand/Collapse All control opposite Separate/Merged. Collapse state stays UI-only.

4.3.3 labels the add toolbar with an Add new block heading and short block-type
buttons, standardizes Greek block terminology and distinguishes Duplicate from Copy.

4.3.2 shortens the block-add toolbar labels, adds distinct article/category/tag
icons and retains descriptive tooltips with responsive button sizing.

4.3.1 adds block Copy/Paste using readable JSON. Duplicate stays in the block
toolbar; Paste block appends at the bottom, including when there are no blocks.
Clipboard access requires browser permission and a secure context (HTTPS or
localhost). Only validated single-block JSON is accepted; selections and Usage
are preserved without changing the stored Sources schema.

4.3.0 adds a shared `contents_levels` setting (default 3) for site/email.
Deeper Contents and cycles retain linked article headers instead of failing.
Email no longer imposes cumulative item/token counts. See the
[header-only override contract and template prompt](docs/CONTENTS_HEADERS.md).

4.2.1 explains email preview budget failures, including nested Sources in Contents,
without relaxing final email limits. Preview exceptions are logged to `sources.php`.

4.2.0 adds explicit per-editor site/email preview destinations. Hosts must opt in
to email; no host/component inference is performed. Email live previews use the
final email pipeline and overrides while preserving presentation markup inside
an isolated editor-only surface. See [the consumer contract and examples](docs/PRESENTATION_CONTEXT.md).

4.1.3 aligns the JSON preview's blue frame and surrounding spacing with the
other static preview modes, without changing its content-sized height.

4.1.2 makes the readonly JSON preview fit its actual text height while retaining
selection/copy support in JCE and TinyMCE.

4.1.1 fixes editor module-cache invalidation across upgrades, including relative
imports, and removes the template's preview-list container decoration.

4.1.0 adds the optional **Newsletter - SuperSoftJx - Sources Email 1.0.0**
provider for Newsletter Bridge protocol v1. It uses public-only shared selection
and independent, overrideable email layouts; native article emails do not require
SmartBrowser. Enable the provider with Public access after installation.
See [email output, overrides and limitations](docs/EMAIL_OUTPUT.md).

4.0.3 separates Sources-field data consumption from rendering, reuses bounded
request-local snapshots. Local Catichisi
profiling and consumer instructions are documented in [PERFORMANCE.md](docs/PERFORMANCE.md).

4.0.2 standardizes localized extension names and release metadata. See
[the changelog](CHANGELOG.md). Technical identities and existing functionality are unchanged.

4.0.1 fixes Details rows in final output: the Sources Details grid now uses one
full-width column independently of the template's Cards grid. Other view geometry
is unchanged. Run `node tests/views-browser.mjs` for the five-view regression suite.

Major release: [release notes and upgrade guidance](docs/RELEASE_4.0.0.md).
Frontend editing/output requires SmartBrowser 4.0.0+. Native email output does
not require it. The Sources storage schema remains version 2.

Sources item markup is overrideable at
`templates/YOUR_TEMPLATE/html/layouts/sources/item.php`.
It receives `item`, `layout`, prepared `selected`, `purpose`, and
`parent_article_id`. The packaged layout preserves the 3.3.0 output; overrides
own all visual rendering, with no forced pre-render or post-presentation by Sources.
See [the item layout contract](docs/SHARED_OUTPUT.md#sources-item-layout-331).

Field integration errors keep the original JSON available inside a closed advanced
disclosure instead of automatically displaying the raw textarea below the Builder.

## Upgrading to 6.0.0

The old Custom Fields bridge, numbered/rest tokens and automatic legacy append
are no longer supported. Convert old content to inline JSON or the modern Sources
field before upgrading. Existing Custom Field definitions, values and article
content are not deleted or rewritten.

Sources supports inline article-content Containers and an optional article Custom Field. The package **SuperSoftJx - Sources** installs **Content - SuperSoftJx - Sources**, **Button - SuperSoftJx - Sources** and **Fields - SuperSoftJx - Sources**. Inline editing remains independent of Custom Fields; no new database tables are added.

## Optional Sources Field

Enable **Fields - SuperSoftJx - Sources**, **Content - SuperSoftJx - Sources** and **Button - SuperSoftJx - Sources**.

In Greek Joomla these appear as **Πεδία - SuperSoftJx - Sources**,
**Περιεχόμενο - SuperSoftJx - Sources** and **Κουμπί - SuperSoftJx - Sources**.
The extension author is **SuperSoft — Kontarinis Andreas**; SuperSoft remains
the code/namespace identity, while SuperSoftJx is the extension display brand.
Create an article field of type **SupJx Sources (sources)**, assign your own Field Group and
categories, and set **Automatic Display: After Display** (the default for new
Sources fields). Hide the field label if only the block headings should appear.
Sources field output is limited to the full article view, not category/blog or edit views.
Joomla's normal Fields pipeline owns placement and permissions, so there is no
second automatic append mechanism. Disabling automatic display suppresses output.

The complete shared Builder appears directly in the field, with the same
SmartBrowser Selection Views and pickers. **Preview** renders the current draft
without saving the article, in a sandboxed, script-disabled preview dialog.
Save/Apply uses the normal Joomla field value: readable schema-v2 JSON, without
the inline token wrapper. Deleting every block clears an optional field.
Required fields retain Joomla's server-side required validation.

Inline Containers and field Containers are independent: neither copies nor
synchronizes the other's configuration. The field fallback is its original JSON
textarea when assets, SmartBrowser or schema initialization fail; it never
replaces malformed stored data with a default configuration.
No field definitions, Field Groups, assignments or migrations are created.
Old Custom Fields remain untouched and should not be reused by merely changing
their type: create a new Sources field instead.

3.0.4 fixes editor preview transport: the modern event subscriber now attaches
its response with AjaxEvent::addResult, as required by com_ajax, rather than
returning an array which the dispatcher ignores. A regression reproduces the
old empty result using the actual Joomla Dispatcher/AjaxEvent/JsonResponse.
Distinct translated preview errors identify permissions/session, missing plugin,
unsupported configuration, empty/non-JSON response and rendering failure.
Server diagnostics remain in Sources logs, not in browser messages.

2.0.1 fixes Joomla WebAsset registration paths: relative extension URIs omit the physical js/css directory because Joomla adds it. Incorrect 2.0.0 paths resolved to an empty URL, leaving the Sources button action unregistered. A regression test now exercises the actual Joomla resolver against installed-layout media fixtures, including subdirectory installs.

2.0.2 uses the shared selected-resources Selection View from SmartBrowser Fields, with its information panel and Add/Replace/remove/ordering actions. Each block groups Title/Layout/Limit in one row. Tags/categories reuse native Joomla searchable multi-select controls in another row, with advanced ellipsis buttons opening node-enabled SmartBrowser dialogs. Filters default open; block/filter chevrons track their expanded state. Storage/schema are unchanged.

2.0.3 polishes the translated builder title, icon layout dropdown, footer actions and direct block deletion. Native taxonomy widgets are synchronized from the draft after connecting/reordering and retain initial selected attributes for Joomla/Choices round trips. Open dropdowns have a local stacking layer above the Selection View. Editor controls are rehydrated after sanitization and handled before editor bubbling.

## Tokens And Schema

2.0.4 uses native Joomla footer buttons (each template supplies its own look)
and scopes details resets against Atum animation/padding/overflow styles.
Article usage enhancements are tracked in docs/SMARTBROWSER_UI_FOLLOWUP.md;
editable usage and persistence are now enabled in 3.2.0 as described below.

3.0.0 introduces schema version 2 and three explicit block types. No migration
or compatibility with temporary version-1 inline containers is included, as
requested. Existing Custom Field data is never deleted.

Each token is an independent Container, rendered exactly where it appears. An article may contain several Containers.

```text
{sources:{"version":2,"display":"merged","ordering":{"mode":"blocks","order":"published-desc"},"presentation":{"title":"Sources","layout":"cards","limit":18},"blocks":[{"type":"articles","title":"","articles":[11,4]},{"type":"categories","title":"","filters":{"categories":[14],"tags":[8,12],"order":"title-asc","groupByCategory":true,"limit":16}},{"type":"tags","title":"","filters":{"tags":[8],"categories":[],"order":"published-desc","limit":16}}]}}
```

The JSON document inside the token is ordinary JSON, not Base64. See [the version 2 schema](docs/inline-sources.schema.json).

- Container: `version`, `display`, `ordering`, `presentation`, and ordered `blocks`.
- Block: required `type` (`articles`, `categories`, `tags`), title, selection, filters and presentation.
- Articles blocks use only ordered manual IDs; category/tag criteria cannot be mixed into them.
- Category blocks require selected categories; tags optionally filter them (AND).
- Tag blocks require selected tags (AND); categories optionally restrict results.
- Empty primary criteria never query the whole site. Category selection uses exact categories, not implicit descendants.
- Dynamic orders: publication/creation ascending or descending, title ascending or descending, Joomla ordering; ID is the deterministic final tie-breaker.
- Category-and-article ordering follows Builder category order for category blocks, then Joomla article ordering. In tag blocks/Common Ordering it follows Joomla category tree order, then article ordering. Other sorts are ungrouped; no grouping checkbox is needed.
- `filters.limit` applies only to dynamic blocks in Separate. Merged hides and ignores per-block limits, combines/deduplicates results and applies only the final Container limit. Manual blocks include the complete accessible selection within safety caps.
- Separate display keeps each block's title/layout. Repeated Articles across separate blocks remain intentional.
- Merged `ordering.mode: blocks` concatenates results in block order and preserves their internal order. A translated note beside the selector states that each block is ordered separately.
- Merged `ordering.mode: global` overrides block order, deduplicates and sorts the combined results before the final limit. Block ordering values remain intact for returning to block sequence.
- Layouts: `cards`, `details`, `list`, `icons`, `contents`.

Defaults are filled only for omitted optional properties. Unknown properties/versions, wrong types, invalid IDs/layouts, malformed or oversized JSON are rejected. Invalid tokens remain unchanged; no saved article content is rewritten by rendering.

An unterminated/oversized token or missing outer delimiter stops scanning the remaining text rather than interpreting nested markers inside broken JSON. Repair that token in source view before expecting later Containers to render.

Tokens are parsed with nesting/string/escape awareness, not a first-closing-brace regex. Attributes, comments, scripts, styles, textarea, pre and code examples are excluded. Editor-created JSON escapes only markup-sensitive characters with normal JSON Unicode escapes. Greek and other ordinary Unicode remain readable after editor serialization.

## Editing

Fresh Builder drafts start with one specific-articles block. Every block, including the last,
can be deleted; Insert/Update is disabled for an empty draft. One stored title
appears as Label in Separate (also rendered as a heading) and Name in Merged
(only identifies the block in the Builder). Merged hides block Layout/Limit.
Common order hides block ordering; Block Sequence hides general ordering.
Hidden values are preserved. A lone Filters/advanced control spans the full row.
Previous name/groupByCategory properties are accepted as deprecated inputs but
are no longer emitted; an old name is used only when title is empty.

Enable both package plugins. Sources opens a native-dialog Builder at the editor cursor. Add, collapse, duplicate, delete, and reorder blocks; configure selection, filters and display; choose Separate or Merged; then Insert.

Activate an existing chip's Edit action, double-click it, or use Sources while that chip is selected to reopen its Container. Update replaces only that instance. Cancel leaves article content unchanged. Concurrent content changes abort replacement instead of guessing a target.

TinyMCE and JCE show temporary noneditable chips and support placeholder, raw JSON, selected-source, resolved-source and live-render preview modes. Their serialization hooks restore real tokens for source view and save; editor HTML, controls and previews are never persisted. JCE's native writable content dispatchers are used rather than its event-copying compatibility wrapper.

Plain-text/CodeMirror editing uses the Joomla editor API and cursor/token offsets where available. Keep a complete token in normal article text, not a code example or HTML attribute. The Builder edits a draft until confirmation. Source editing remains available when SmartBrowser cannot load.

## SmartBrowser

The integration requires SmartBrowser **3.16.0+**, including Shared Picker Operations and SelectionOutputRenderer.

- Blocks use their matching Articles/Categories/Tags adapters and public Collection/Picker APIs. No resource renderer or resource ordering UI is duplicated in Sources.
- Collection Add/Replace use SmartBrowser's shared operations via `pickerUrl`, without Sources callbacks or a second merge. Add opens empty and retains existing identities and Usage; Replace edits the complete selection with the current resource focused and Information open.
- One complete `onChange({ items })` updates the ordered Sources selection, including Usage/extension data. Resolved metadata/resource HTML are not serialized. Cancellation retains the original selection.
- Compact taxonomy fields and secondary filters retain their independent criteria Picker flow. External collection commands delegate to `add()` / `editSelection(resource)` and catch rejected promises.
- The Button - SuperSoftJx - Sources setting `taxonomy_editor` chooses Selection View (default) or compact native dropdown + picker for both primary categories and primary tags. Optional cross-filters remain native dropdowns. This setting changes only editing UI, not storage/selection semantics.
- Every adapter uses the full Selection View, including preview and ordering. Category sequence affects category-and-article order; tag order is retained but does not alter AND criteria.
- Collection instances are updated through setItems. A changed ordering policy requires remounting through the public API because SmartBrowser does not expose a configuration-update method.
- Picker and Builder use native dialog top-layer/focus handling, without Bootstrap modal traps.
- Missing SmartBrowser leaves the original token/configuration accessible. No substitute resource renderer is shipped in Sources.

## Rendering And Safety

3.2.3 versions the editor iframe CSS URL by its actual content hash, using the
same version for the outer Builder stylesheet. Re-decoration refreshes stale
Sources stylesheet links in reused editor documents. Rendered blocks now carry
an explicit sources-layout--LAYOUT marker; preview Details rows no longer rely
on the card being a direct child of the grid. Preview-only rules bound wrapper
and card widths without resetting SmartCrop images. The site template is unchanged.

3.2.2 reserves a stable scrollbar gutter in the editor's bounded preview and the
field Preview document. Adding a block heading can trigger vertical scrolling,
but no longer reduces the available card-grid width on classic-scrollbar systems.

3.2.1 scopes preview sizing rules to the live editor chip and the custom-field
Preview iframe. Details uses full-width rows and stacks according to the preview
container's width rather than the outer page viewport. Normal images cannot
overflow the preview; SmartCrop frames and oversized crop images retain their
own sizing. These rules do not alter frontend output or stored configuration.
Preview styling still depends on the stylesheets configured for the editor or
loaded in the preview document; this is not a new frontend-template asset loader.

### Selection Usage (3.2.0)

All Collection Views and Pickers enable editable SmartBrowser Usage. Displayed
name (articles only) and thumbnail override are primary; custom icon and
thumbnail/icon/both presentation use the native, initially closed Advanced group.
Old numeric selections remain valid in schema version 2. A selection with Usage
is stored directly in the same ordered array, for example:

```json
{"selection":{"adapter":"articles","id":"article:43"},"usage":{"article.displayedName":"Local heading","visual.iconOverride":"fas fa-book"}}
```

There is no separate Usage map. Reorder, remove, duplicate, replace, Picker
reopen, compact taxonomy fields, inline tokens and custom fields preserve
entries and reserved ext.* metadata. Category/tag Usage affects those selected
resources, not every dynamically matched article. Merged deduplication keeps the
first occurrence and its Usage.

Every rendered candidate, manual or dynamic, is prepared through the public
readonly SelectionFieldSupport API and passed to SelectionOutputRenderer.
The common Joomla layout is smartbrowser.resource, with normal template lookup;
without an override the SmartBrowser generic resource layout is used.
Image/icon composition and the once-only image presentation event belong entirely
to SmartBrowser. Sources retains block shells, summaries and embedded article bodies.
Article images are never rewritten. See docs/SHARED_OUTPUT.md for the verified
template contract, preview lifecycle and remaining site-template integration work.

SmartCrop metadata survives round trips, but Sources does not interpret opaque
extension payloads. Cropping an article's effective thumbnail requires the
SmartCrop overlay enhancement; editable Usage alone does not enable that.

Selection applies article/category publication and access checks, category ancestors, publication windows and active frontend language filtering. Missing/unpublished/inaccessible Articles are omitted.

Modern output no longer calls library.card/library.source-content or a Sources
visual fallback. Embedding Contents uses Joomla content preparation after the
shared resource identity.

Blog/category views hide valid Containers or show the configured bracketed placeholder. Editing contexts retain source tokens. Preview accepts unsaved inline configuration via authenticated, CSRF-protected POST, with content authoring permission checks; saving an article first is no longer required.

Safety bounds: 128 KiB per JSON document, 30 blocks, 500 IDs per selection/filter, 2000 UTF-8 bytes per title, 500 resolved items per Container, 2000 per request, bounded malformed scanning, and bounded content recursion.

## Upgrade From 1.x

This is a deliberate breaking architecture change. There is no migration or legacy token resolution for `{sources}`, `{sources:N}`, or `{sources:rest}`. Recreate the few old articles with inline Containers.

Removed from the legacy architecture: old Custom Fields configuration reads, article Sources tab injection, native subform Builder, Classic form bridge, numbered-block token mapping, article-ID-based saved-field previews, arbitrary legacy template inclusion, and automatic append. The optional 3.1 Sources field uses the new shared JSON schema, not those legacy fields.

Installation does **not** delete old Custom Field definitions/values or alter stored article content. Any manually configured old Joomla Custom Fields/tab remain Joomla-owned until you disable/clean them. Obsolete files from an earlier install may remain on disk, but nothing in 2.0 loads the legacy Builder.

## Verification

```text
npm.cmd test
node tests/browser-verify.mjs
build.bat
```

Tests cover JS/PHP schema/parser parity, hostile/malformed input, Builder lifecycle and SmartBrowser synchronization, renderer ordering/merge rules and generated ACL/publication query guards. Browser checks use actual local TinyMCE and JCE engines plus the actual sibling SmartBrowser Collection and Picker dialog code on desktop/mobile.

Browser transport and the picker child page are fixtures, not a live Joomla server. Actual Joomla database queries, site layouts, full JCE profile/source-tab extensions, frontend/backend Save/Apply, article history and reload still require installation QA. The browser tests verify editor serialization/save/reload, not Joomla database persistence.

The preview-event test uses real Joomla event/JSON classes and the real Sources
subscriber with fixture application/session/rendering boundaries. It covers
all three preview modes, error codes and CSRF/authoring checks. Browser checks
exercise successful preview, malformed/empty replies, permissions, render errors
and recovery while asserting that the stored token remains unchanged.

`tests/browser-verify.mjs` expects sibling `pkg_smartbrowser`, local `../../JoomlaTest/site` assets and installed Chrome (or `CHROME_PATH`).

## Build And Release

`build.bat` (or `powershell -ExecutionPolicy Bypass -File build/build.ps1`) creates `build/output/pkg_sources_v3.2.3.zip`. The package version comes from `package/pkg_sources.xml`; plugin versions stay aligned.

Push a matching `v3.2.3` tag through the existing GitHub Actions release workflow when ready. Generated ZIPs and browser screenshots are ignored by git.
# Dialog Controls

The Sources Builder uses the SmartBrowser dialog shell and its public maximize/restore helper. The Sources window-size preference is independent of the picker; window controls are optional if SmartBrowser cannot load.
