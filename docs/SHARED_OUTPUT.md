# Shared Sources Output (3.3.0)

## Sources Item Layout (3.3.1)

The complete item presentation is now delegated to `sources.item`.
The packaged fallback is:

`plugins/content/sources/layouts/sources/item.php`

Override at:

`templates/YOUR_TEMPLATE/html/layouts/sources/item.php`

Normal Joomla com_content-specific layout overrides are also supported.
The packaged directory is appended at the lowest priority, not supplied as a
high-priority base path. Frontend lookup retains Joomla parent-template lookup.
Administrator previews also search the default site's template override.

The five displayData keys are:

| Key | Contract |
| --- | --- |
| item | A clone of the access-filtered article candidate; original article image fields remain unchanged. |
| layout | Validated Sources mode: cards, details, list, icons or contents. |
| selected | Complete readonly-prepared SmartBrowser selection, including resource metadata, Usage and effective image references. |
| purpose | output or preview. |
| parent_article_id | Host article ID, or 0 for unsaved/configuration-only preview. |

No visual has been rendered before this layout runs. Sources does not finalize
or present its returned HTML. The override may call SelectionOutputRenderer for
each visual it owns, or intentionally emit no visual at all:

```php
use SuperSoft\Component\Smartbrowser\Administrator\Support\SelectionOutputRenderer;

echo SelectionOutputRenderer::render($displayData['selected'], [
    'layout' => 'smartbrowser.resource',
    'purpose' => $displayData['purpose'],
]);
```

Do not call present() again on that HTML. Preserve server image markers. An
override choosing present() for its own trusted HTML should use it instead of,
not in addition to, render().

The default item layout retains the 3.3.0 wrappers, default List/Icons behavior,
summaries and prepared full Contents body. Source access filtering, readonly
selection preparation, self-embedding checks and bounded recursion stay in
InlineRenderer and surround template overrides as well. Failed overrides release
the recursion guard and their output buffers.

The template can now identify Sources explicitly and provide its own action bars,
chips and article chrome in this layout while delegating visuals to SmartBrowser.
No template files are modified by the package.

Requires SmartBrowser 3.16.0+. No storage/schema or other-extension changes.

## Ownership and Verified Layout

Sources owns block selection, access/publication/language SQL guards, category/tag
filters, ordering, merging/deduplication, headings and the five layout shells.
Every final candidate is additionally resolved by SelectionFieldSupport::prepare
with readonly=true, including numeric/dynamic articles without Usage. Unavailable
prepared entries are omitted. SmartBrowser owns effective images, icons, Usage
validation, metadata and thumbnail resolution.

Each visual delegates once to SelectionOutputRenderer::render with
layout=smartbrowser.resource and purpose=output or preview. No high-priority
layout base path is installed. Joomla resolves the override at:

templates/YOUR_TEMPLATE/html/layouts/com_smartbrowser/smartbrowser/resource.php

Without it, the component's generic layout is used. Sources never calls present()
again, nor interprets crop payloads or calls a concrete presentation provider.
List/Icons default to icon presentation when no explicit selection presentation
exists; explicit author Usage remains authoritative.

The inspected Catichisi override delegates composition to catichisi.identity:

templates/site/jl_kidsacademy_free/html/layouts/com_smartbrowser/catichisi/identity.php

Its input is the SmartBrowser displayData contract: selected, resource (including
metadata), opaque usage, title, safe url, image attributes/imageAddress, icon,
caption and target. It preserves data-sb-primary-image on the main image only.
SelectionOutputRenderer finalizes that image and retains
data-sb-image-presentation="server". Sources passes the complete prepared entry,
not a reduced reconstruction of its visuals. No Usage JSON is emitted into HTML.

## Preview Lifecycle

The authenticated/CSRF-protected existing Sources preview endpoint uses the exact
same PHP renderer with purpose=preview. It returns HTML and activated style URLs
from Joomla's WebAssetManager; editor/field preview documents load those styles
without executing scripts. HTTP(S) style URLs are deduplicated.

Token/Usage/mode changes abort prior editor requests and also check request
identity and the current token before publication. Detached nodes ignore late
results. Field draft changes refresh an open preview; old dialogs/responses are
aborted/ignored. HTML publication preserves server presentation markers.

Sources does not register a SmartBrowser JS preview renderer or run image
presentation providers itself, so it has no setImageElement publication to own.
Picker/Collection image publication remains SmartBrowser-owned. If Sources later
adds a registerPreviewRenderer integration, it must publish through setImageElement,
release before replacing HTML, observe cancellation and retain the server marker.

## Compatibility and Remaining Template Work

All five modes, Separate/Merged rules, stored Usage and legacy compatibility
remain. Contents retains recursive content preparation and self/recursion guards;
Cards/Details retain escaped summaries. Original article image fields are untouched.

Modern output intentionally no longer calls the old library.card or
library.source-content full article renderers: those also composed visuals and
would bypass or duplicate the central presentation. Their site-specific action
bars, category chips and full article header chrome are not supplied by the
SmartBrowser resource contract. Sites needing that exact chrome must adapt the
common template resource layout; no template or SmartVisuals files were edited.
This is not a claim of pixel-identical old card/action-bar output.

Visual effects require a provider implementing the generic PHP presentation
event. Preserving Usage alone cannot reproduce effects from JS-only providers.
No SmartCrop dependency or special interpretation is added. Inline/field values
are never rewritten by rendering; the legacy sources.php bridge is untouched.

## Verification

Focused tests use actual sibling SmartBrowser SelectionOutputRenderer and its
generic layout with Joomla collaborators, plus an override fixture. They cover
all five modes for output/preview, full Contents body, readonly unavailable
resources, Usage overrides, retained metadata, original article immutability,
one hook per primary image and no second event on server-marked HTML.
Existing SQL authorization/order and real editor browser suites remain separate.
Authenticated rendering on the installed site's template/provider combination
still requires installation QA; fixture checks do not replace that.
