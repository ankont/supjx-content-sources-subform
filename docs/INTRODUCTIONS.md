# Tag and category introductions (Sources 4.5.0)

## Sources 5.0.0

Install SmartBrowser **6.1.0+ with consumer-selection-usage support** first. The
package now includes System - SuperSoftJx - Sources Usage 1.0.1. It registers
server-owned `sources` capabilities on every request, including Picker/API
validation, and is enabled on first installation only. Updates do not re-enable
a deliberately disabled plugin. Keep it enabled and available to editor users.
It also exports the capability label translations on HTML responses so that the
Picker's child page can translate them without serializing a host callback.
Sources Email is 1.3.0. There is no new table, database migration or deletion.

### Per-selection Usage

Both Collection and shared Add/Replace Picker receive `consumer: 'sources'`,
the trusted `usageCapabilities`, the existing profile and translated labels.
Properties apply only to category/tag resources, never to article selections
or unrelated consumers. Usage stays with its identity through copy, replacement,
save/reload and reordering. Unrelated provider metadata stays opaque/preserved.

Primary dropdowns:

- `ext.sources.introLayout`: default / simple / heading / card / collapsible.
- `ext.sources.introElements`: both / title / description / none (text only;
  visual image/icon presentation remains independently controlled).

Secondary controls:

- `ext.sources.introTitle`: plain-text local title, empty/whitespace uses native.
- `ext.sources.introDescription`: plain text, up to 10,000 characters. Empty uses
  native description; nonempty is escaped with preserved line breaks. It cannot
  inject HTML or executable Sources. Hide description through introElements.
- `ext.sources.introClass`: space-separated ASCII CSS identifiers (255 chars),
  not arbitrary styles, selectors, attributes or HTML.

Title limit is 2,000 characters. Invalid enum/type/class values, unknown reserved
ext.sources keys and use on article resources are rejected by JS/PHP schema
validation. PHP also uses SmartBrowser's trusted consumer Usage validation when
available; browser definitions are never trusted server-side. No override changes
the actual tag/category record. Absent values retain native/default behavior.

Native descriptions retain the original site/email preparation policies below.
Description-only/hidden text never causes preparation of the hidden description.
Thumbnail/icon/crop Usage is resolved read-only through SmartBrowser for visible
introductions. Layout overrides receive the prepared `selected` entry; they decide
where/how to invoke the shared visual pipeline. Sources never renders a visual
before or after the introduction override. The generic layout invokes the common
presentation provider once per marked main image. No image needs no provider.

### Grouped lists

Optional block `grouped:true` defaults off/omitted. Its toggle sits beside Show
introduction, but is independent of it. It is hidden in Merged mode without
discarding its stored value. Grouped Separate blocks resolve each primary
selection independently, in selection order, retaining secondary filters. Order
and limit apply per resolved group, inside the existing overall item budgets.
Articles may repeat across groups. This intentionally replaces multi-tag AND
with one independent query per primary tag. Ungrouped tags retain AND semantics.
Empty groups do not render. A configured block caption is repeated per list.

Merged mode ignores grouping, merges/deduplicates/sorts articles as before and
places all enabled introductions before the common list, first type/id occurrence
wins including its Usage. No introduction is inserted between individual results.

### Paired layout boundaries

Existing tag-intro/category-intro and email counterparts additionally receive:

- `presentation`: normalized `{layout, elements, class}` (not the article view).
- `selected`: read-only SmartBrowser entry with effective image/icon and provider
  Usage, or null when only the native fallback is available. Never mutate it.
- `entity` title/description include local overrides. `description_html` remains
  prepared site HTML. Email entity.description remains prepared email HTML.

New overrides:

```text
templates/YOUR_TEMPLATE/html/layouts/sources/group.php
templates/YOUR_TEMPLATE/html/layouts/sources/email/group.php
```

These receive `introductions_html`, `results_html`, ordered `introductions` records
(native filtered entity, type, criteria, Usage and normalized presentation),
`group` (null or type/id/index/block_index), destination article `layout`,
`display`, `purpose`. Site adds parent_article_id and `collapsible`. Email adds
public_only=true. HTML fragments have already rendered through their normal
overrides: output them once, do not render the items/introductions a second time.
These boundaries are used when there is an introduction or explicit group;
ordinary no-introduction blocks retain their original output path.

Email group records contain the email-safe entity array (not the raw native
description), and also expose `collapsible` as intent. Site group records retain
the access-filtered native object. Neither boundary should re-render a fragment.

Generic card is a horizontal Details-like section heading, stacking on mobile.
Heading is a compact horizontal identity; simple/default are unframed. Generic
site collapsible wraps the **results**, with introductions (title and description
subject to visibility settings) in the summary bar, initially open. In Merged,
any collapsible introduction requests one shared disclosure for the common list;
all introductions are in that summary. Template overrides own exact markup,
interaction and styling. No framework or Catichisi behavior is imposed.

Generic email has inline-styled tables and always-visible results, including for
collapsible intent, because disclosure is not portable across mail clients. It
does not output Font Awesome icons; the safe icon identifier is available to an
override with an email-safe representation. Image providers remain responsible
for email-compatible effects; mail-client support varies. No SmartCrop API is
called directly and no provider metadata is decoded by Sources.

Update introduction/group overrides to honor these fields to enable the new
choices. Existing overrides continue rendering, but may ignore new presentation
intent until adapted. The first-level/no-further-introductions policy remains.

## Configuration and selection semantics

The Builder exposes **Show introduction** / **Εμφάνιση εισαγωγής** on category
and tag blocks only, in both standalone and embedded editing. As of 4.5.1 it is
a pressed/unpressed button in the first advanced/filter row, beside the limit;
the secondary taxonomy filter and ordering controls appear below. It is off by
default. Enabling it adds `"showIntro":true` to that block in the existing
version-2 configuration. False/absent canonicalizes to an omitted property.
Manual article blocks cannot enable it. Existing stored values are not rewritten
by installation, and old configurations retain their output and query behavior.

Only the block's primary entities contribute introductions:

- A category block introduces every selected category, in selection order.
- A tag block introduces every selected tag, in selection order. The tag selector's
  existing AND matching remains unchanged; no one tag is chosen as representative.
- Secondary tag/category filters do not add their own introductions. Selection
  Usage does not replace these native entity titles/images/descriptions.
- Each visible entity is introduced once per separate list, not per article.
  Reusing it in another separate block intentionally repeats that introduction.
- In merged presentation, introductions from contributing resolved blocks are
  collected before the common list, deduplicated by type/id, first occurrence wins.
  They describe those block criteria, not individual results or grouping; common
  article ordering/limits do not reorder the introductions.
- Empty result lists have no introduction. Missing, unpublished, wrong-language
  or inaccessible entities have no introduction and no unavailable notice.

The query enforces native publication/view levels, the active Joomla language
filter, com_content category ownership and category ancestor publication/access.
Email uses public view levels regardless of the editing user's privileges.
No entity page, article list model, filter URL, HTTP request or site mapping is used.
Only native identity/description/image data is fetched, with request-scoped cloned
snapshots; prepared HTML is not cached across callers.

## First-level policy and descriptions

Introductions appear only in root Sources rendering, not inside embedded Contents
articles or inside another introduction. Their descriptions may nevertheless
contain full inline Sources tokens. These use the real existing selector/renderer
and keep the requested destination and preview/output purpose. They cannot add
further taxonomy introductions. Contents depth/cycle guards and rendering budgets
continue to apply; the new flag does not raise or bypass them.

For site output and site live preview, description content preparation runs once
with `com_tags.tag` or `com_content.category`. Inline Sources tokens are protected
from the category/blog placeholder policy while other content plugins run, then
rendered through the same InlineRenderer. The introduction guard covers preparation
and the override, and is restored even after exceptions.

For email, no general content plugins execute. The existing EmailRenderer protects
Sources tokens during email sanitization and expands them through its public-only
pipeline. The same layouts/path serve final preparation and live preview. No
scripts, Font Awesome, site styles or arbitrary site plugin execution are added.

Manual-list/summary preview modes do not fetch or render introductions. Opening an
independent preview request starts a new root; rendering a nested tree within one
request obeys the first-level policy above.

## Site layout contract

Override either or both:

```text
templates/YOUR_TEMPLATE/html/layouts/sources/tag-intro.php
templates/YOUR_TEMPLATE/html/layouts/sources/category-intro.php
```

Normal Joomla layout precedence and template inheritance apply, including the
site template when the editor runs in administrator. Packaged layouts are fallbacks.
The display data contains:

| Key | Value |
| --- | --- |
| `entity` | Detached native object: id, title, alias, description, params, language; tags also have images. Already access-filtered. |
| `type` | `tags` or `categories`. |
| `description_html` | Prepared description with nested Sources rendered; output directly, without preparing it again. |
| `purpose` | `output` or `preview`. |
| `layout` | Actual cards/details/list/icons/contents view of the destination list. |
| `display` | separate or merged. |
| `criteria` | Ordered numeric `tags` and `categories` arrays; no resolved selection metadata/Usage. |
| `parent_article_id` | Host article id, or 0 for an unsaved/context-free preview. |
| `public_only` | Whether this resolver is explicitly public-only. Normal site ACL uses the current viewer. |
| `depth` | 1 (introductions are root-only). |

The generic fallback renders an escaped h2 title, optional native image and
`description_html`. Category images come from params.image; tag images use
images.image_intro then images.image_fulltext. Invalid image URLs are omitted.
It does not load the full category/tag page or dispatch content events again.

An override may add a site's linked descriptive page, but owns that mapping,
permissions, presentation and recursion safety. No mapping is inferred by Sources.
Use `description_html` as-is. Escape raw native strings/attributes appropriately;
`entity.description` is original stored content, not pre-escaped text.

## Email layout contract

```text
templates/YOUR_TEMPLATE/html/layouts/sources/email/tag-intro.php
templates/YOUR_TEMPLATE/html/layouts/sources/email/category-intro.php
```

The context keys match the site contract except:

- `entity` is an email-safe array with id, title (plain text; escape it), image
  (safe absolute URL or empty), description (sanitized HTML including rendered
  email Sources). No raw native entity or site HTML is passed.
- There is no separate `description_html`; use `entity['description']` directly.
- `public_only` is always true; `parent_article_id` is 0 because email preparation
  is document-based, not tied to a Joomla article host.
- `purpose` is normalized to preview/output; email test/send/preparation map to output.

Fallbacks use presentation tables, inline styles and bounded image dimensions.
No icon font or external stylesheet is required. Templates must retain email-safe
HTML and must not reprepare descriptions through general site content events.

## Upgrade and verification

Install the whole Sources 4.5.0 package (email provider 1.2.0) before saving new
showIntro-enabled configurations; older strict validators do not know the optional
key. No database/storage migration or template change is required for default output.

Focused fixtures cover opt-in/schema parity, Builder mode changes, primary order,
filtered/missing references, merged deduplication, native content contexts,
first-level recursion and exception cleanup, site output/preview, email final/preview
equivalence and sanitization. Real Joomla FileLayout tests exercise both override
paths and the packaged title/description/image fallbacks. These are fixture checks,
not a production deployment or actual email delivery test.
