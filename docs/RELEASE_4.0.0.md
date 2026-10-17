# Sources 4.0.0

Major architectural release, 2026-10-17.

## Highlights

- Self-contained, readable inline JSON Sources Containers, with visual insertion
  and editing through the Sources Builder.
- Optional SupJx Sources custom field using the same Builder and configuration.
- Specific Articles, Articles from Categories and Articles from Tags blocks.
- Separate/Merged presentation, selection ordering, dynamic filters, per-block
  presentation and container-level ordering/limits.
- SmartBrowser shared Picker operations and Selection Views, retaining ordered
  selections, Usage, thumbnail/icon overrides and opaque extension metadata.
- Shared PHP resource presentation through SmartBrowser SelectionOutputRenderer,
  common Joomla resource layouts and once-only generic image presentation.
- Overrideable sources.item with item, layout, selected, purpose and
  parent_article_id. No forced visual rendering or second presentation.
- Server-rendered editor/field previews with stale-response protection, retained
  server markers and shared presentation styles.
- Temporary, configurable legacy Custom Fields rendering through the existing
  template sources.php, without deleting old fields or values.

## Requirements and Upgrade

- Install SmartBrowser 3.16.0 or newer before this package.
- Back up database and site files and verify the upgrade locally/staging first.
- Install pkg_sources_v4.0.0.zip through Joomla's extension installer.
- Keep temporary legacy compatibility enabled while recreating old articles.
  Modern inline Containers or populated modern Sources fields take precedence
  over legacy rendering; old values remain stored.
- No automatic data migration, new database tables, field definitions or Field
  Groups are created. Assign modern Sources fields/categories/groups explicitly.
- Package version 4.0.0 does not change the existing version-2 Sources JSON schema.

## Template Integration

Modern resource visuals delegate to SmartBrowser's smartbrowser.resource layout,
with a generic fallback when the site has no override. For Sources-specific
article chrome, actions or category chips, override:

templates/YOUR_TEMPLATE/html/layouts/sources/item.php

The default preserves the current Sources output. Old library.card and
library.source-content are not called automatically by modern rendering.
See [the full output and item layout contract](SHARED_OUTPUT.md).
Do not apply image presentation a second time to server-presented HTML.
Legacy sources.php must remain installed until legacy content has been migrated.

## Known Limitations

- No automatic migration of old mixed/manual/dynamic source blocks.
- Existing site action bars/chips require the Sources item override above.
- Visual extension effects require a provider for SmartBrowser's generic PHP
  presentation event; preserved metadata alone does not activate JS-only effects.
- The previously observed blank frontend article form is a separate, unresolved
  site integration issue; use administrator editing until diagnosed.
- Verify actual site-template/provider behavior after installation. Automated
  fixtures do not constitute an authenticated live-site deployment test.

## Verification

Unit/integration coverage includes schema/parser parity, access/publication
guards, ordering/deduplication, Usage preservation, shared Picker operations,
preview cancellation, actual SmartBrowser PHP output, exactly-once image
presentation and actual Joomla template/parent layout lookup.
The browser suite covers TinyMCE/JCE and Cassiopeia/Atum on desktop/mobile.
