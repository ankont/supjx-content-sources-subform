# Changelog

## 4.4.3 - 2026-10-30

- Route visible unavailable selections through sources.item and sources.email.item,
  preserving the requested view and order with SmartBrowser-sanitized data only.
- Keep unavailable resources distinct from Contents depth/cycle headers.
- Email provider 1.1.1 uses link-free email fallback markup rather than site HTML.
- Document the placeholder image-presentation API gap in SmartBrowser.

## 4.4.2 - 2026-10-28

- Persist initial Builder expansion from the last Expand All / Collapse All command,
  without changing it on individual toggles or storing UI state in Sources JSON.
- Apply to dialog and embedded Builders; retain a page-local fallback if storage is blocked.

## 4.4.1 - 2026-10-28

- Distinguish Content/Email preview dependencies by registration, enabled/access,
  public policy, runtime availability and package files, with translated errors.
- Validate applicable Builder controls explicitly; reveal collapsed invalid fields
  and preserve numeric drafts/mode values without disabling outer form validation.
- Remove obsolete manual-article limit keys on canonical save; retain dynamic
  and merged limits. No bridge/template edits or real email sends.

## 4.4.0 - 2026-10-28

- Add Joomla-like unavailable visibility through SmartBrowser 4.1.2's native field
  and shared renderer: Use Global (empty), Show (1), Hide (0).
- Retain ordered missing manual references through readonly preparation, with
  neutral notices owned by SmartBrowser and no private data in template rendering.
- Keep dynamic results filtered, preserve selections/Usage and apply the same
  presentation policy to final output and editor previews.

## 4.3.5 - 2026-10-28

- Add a visible translated Expand all / Collapse all label beside the arrows,
  updating together with the icon and wrapping the toolbar on narrow screens.

## 4.3.4 - 2026-10-28

- Simplify Duplicate to a plain plus and reduce add-toolbar grid minimum to 180px.
- Add Expand/Collapse All opposite Separate/Merged, synchronized with individual
  toggles and add/delete operations, without changing stored container data.

## 4.3.3 - 2026-10-28

- Add a labeled Add new block section and short block-type action names.
- Use Greek μπλοκ consistently in translated labels, tooltips and errors.
- Distinguish Duplicate with a mirrored clone/plus glyph, retaining standard Copy.

## 4.3.2 - 2026-10-28

- Shorten block-add/Paste labels, retain full accessible action descriptions,
  use distinct resource icons and prevent toolbar text overflow responsively.

## 4.3.1 - 2026-10-28

- Replace the block menu with move, duplicate, copy and delete icon controls.
- Add Paste block alongside the add actions; append validated clipboard block JSON,
  including into an empty Builder. Preserve ordered selections and Usage, reject
  invalid/oversized data and report browser clipboard permission failures.

## 4.3.0 - 2026-10-28

- Add the shared Content plugin setting contents_levels (default 3) for site and
  email. Deeper Contents and cycles retain linked headers through existing layouts.
- Publish header_only, fallback_reason, depth and translated read_more_text to
  Sources item overrides. No storage/schema changes or template edits.
- Remove email cumulative 60-item/8-token caps; retain byte validation, public
  access checks and shared selector bounds. Email provider version is 1.1.0.

## 4.2.1 - 2026-10-28

- Explain email preview budget failures in editor and Builder previews rather
  than displaying a generic error. Contents includes nested Sources in the same
  final-email budget; security limits and stored data remain unchanged.
- Register the Sources error log explicitly so preview exceptions reach sources.php.

## 4.2.0 - 2026-10-26

- Add explicit per-editor site/email presentation configuration and runtime API,
  independent of preview mode, purpose, URL, component and stored Sources JSON.
- Route email live preview through the final EmailRenderer/overrides under a
  temporary public identity, retaining authorization and restoring identity on errors.
- Propagate context through nested and embedded Builder previews; include it in
  request identity and abort/ignore stale mode/context responses.
- Isolate email tables/styles/image dimensions in sandboxed preview iframes,
  without global editor schema bypass or rendered HTML in saved article content.
- Add multi-instance, public-policy, exact preview/provider equivalence and
  actual TinyMCE/JCE HTML-preservation checks. Sources Email stays at 1.0.0.

## 4.1.3 - 2026-10-26

- Match the JSON preview's outer blue frame, margins and padding to the other
  static preview modes, preserving intrinsic height and readonly selection.
- Use border-box consistently to keep mode changes within the same outer width.
  Sources Email remains 1.0.0.

## 4.1.2 - 2026-10-26

- Size the selectable readonly JSON preview to its wrapped content, preserving
  native JCE/TinyMCE selection and the existing frame without a resize handle.
- Use native field-sizing with a resize-aware fallback for older browsers.
  Sources Email remains 1.0.0; stored article content is unchanged.

## 4.1.1 - 2026-10-26

- Fingerprint the complete editor module graph and map relative imports through
  Joomla's native import map; version both editor and field entry scripts with
  that hash instead of the unrelated global Joomla media version.
- Restore current preview headers on surviving older widgets and remove preview
  list-container decoration without affecting actual frontend list layouts.
- Add a warmed immutable-browser-cache regression. Sources Email remains 1.0.0.

## 4.1.0 - 2026-10-26

- Add optional newsletter/sourcesemail provider version 1.0.0 for the Newsletter
  Bridge protocol v1, independent of bridge classes and SmartBrowser rendering.
- Reuse the data-only block resolver for public-only, bounded email selection;
  preserve native frontend selection, merge/order semantics and stored schema.
- Add overrideable sources.email.block and sources.email.item layouts for all
  five modes, safe native Contents and nested inline/field Sources, explicit
  unsupported Usage failures, cumulative budgets and no send-side effects.
- Add fixture and real Joomla loader/bridge lifecycle tests with transient
  tables, synthetic data, memory-only mail, snapshot and batch-resume checks.
- Package and existing plugins advance to 4.1.0; the new provider starts at 1.0.0.

## 4.0.3 - 2026-10-19

- Remember the editor Sources preview mode in browser storage, independently of
  article JSON and frontend presentation; retain plugin defaults until selected.
- Let inline previews grow naturally without an inner scrollbar; restore the
  standard visually-hidden utility in isolated previews and expose an optional
  template-owned --sources-preview-max-width CSS property.
- Keep Contents preview bodies without automatic nested live rendering; expose
  each nested Container through the shared manual-list preview UI with explicit
  live controls, isolated from saved editor preferences and article data.
- Make JSON preview selectable/read-only and center the block-level placeholder.
  Normal output retains its existing embedded Sources behavior.
- Give field previews the shared toolbar and title/summary header; remove the
  legacy badge/count heading and decorative preview-list borders. Fix native
  JSON selection in JCE and expose an optional in-article nested preview slot.
- Preserve inline nested Container positions in Contents preview; reserve the
  end-of-article slot for Sources fields and never mutate original article text.
- Add SourceFieldData scopes for data-only field consumers, preserving other
  field/content plugins and explicit legitimate embedded Sources rendering.
- Reuse bounded request-local serialized snapshots of article queries and complete
  readonly selections, partitioned by access/language/context and full Usage.
- Expose prepareOutput alongside resolve for data-only selection preparation;
  keep HTML layouts/presentation providers outside all data caches.
- Preflight legacy fields without rendering; preserve append_if_missing and only
  fully prepare genuine legacy subforms. Add modern-field and legacy cycle guards.
- Add regression coverage and reversible local before/after profiling tooling.
- See docs/PERFORMANCE.md for real Joomla measurements and remaining template work.

## 4.0.2 - 2026-10-19

- Standardize plugin display names as Group - SuperSoftJx - Sources; localize
  Greek group labels to Περιεχόμενο, Κουμπί and Πεδία in runtime/installer translations.
- Keep the package display name SuperSoftJx - Sources and update current README
  and preview-warning references consistently.
- Set author SuperSoft — Kontarinis Andreas and release date 2026-10-19 in all
  four manifests; fix missing author/date metadata in the Fields plugin.
- Increment package and all plugin versions to 4.0.2. Preserve technical names,
  namespaces, language keys, filenames, stored data and rendering behavior.

## 4.0.1 - 2026-10-18

- Fix full-width Details rows in final output, independently of the template's
  Cards grid, without changing the other four views.
- Add five-view regression coverage at desktop/mobile widths and both CSS orders.

## 4.0.0 - 2026-10-17

- Introduce inline Sources Containers, a shared Builder and an optional Sources field.
- Integrate shared SmartBrowser selection/output and an overrideable sources.item.
- Retain temporary legacy rendering without deleting existing field values.
- See [the major-release notes](docs/RELEASE_4.0.0.md) and PLAN.md for prior history.
