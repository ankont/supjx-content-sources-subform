# Unavailable item presentation (4.4.3)

Visibility is resolved centrally by SmartBrowser `showUnavailable()` from the
unchanged Sources `show_unavailable` choice. Hidden entries never reach layouts.
Manual references retain their positions; dynamic queries remain access-filtered.
No unavailable body is loaded or expanded. Stored selections are not modified.

## Site: sources.item

The normal layout receives its actual `layout` (cards/details/list/icons/contents),
`purpose`, `parent_article_id` and `showUnavailable`, plus:

- `unavailable`: boolean, false for normal resources.
- `unavailable_presentation`: null for normal resources; otherwise the data-only
  result of `SelectionOutputRenderer::displayData(['unavailable' => true])`.
  `title` is the generic configured message, `image` is null or sanitized image
  attributes, `imageAddress` is null or its safe address, `icon` is null or an
  allowed configured icon class. These strings still require HTML escaping.
- `selected`: for unavailable entries only the centrally generated neutral
  selection, with null selection identity, empty Usage and empty resource metadata.
- `item`: detached neutral object: id 0, generic title, empty introtext/fulltext,
  images '{}'. Never a copy of the missing/private article.

Unavailable does NOT imply `header_only`: it is false, `fallback_reason` is null.
Do not use id 0 as an article lookup. Do not build links, query original data,
apply saved Usage or infer why the item is unavailable.

The override owns its normal frame (including an empty image frame), configured
icon/pin, message-as-title, and per-view overlay/alignment/ellipsis rules. Sources
does not run a renderer or presentation hook before/after that override. The
packaged fallback is neutral, link-free and does not run content plugins.
Summary-only editor modes retain their existing SmartBrowser notice; this contract
concerns actual site output and live previews, not the diagnostic summary modes.

## Email: sources.email.item

The usual `item` array contains `unavailable=true`, id 0, generic `title`, safe
absolute `image` or '', optional sanitized `icon` identifier, and empty
url/summary/content/read_more_text. `header_only=false`, fallback_reason=null;
depth is supplied normally. Top-level `unavailable=true`, `layout`, `purpose`
and depth/header flags are also passed. The email block receives these models
in selection order, each with the override-produced `html`.

Render inline-styled email tables, never site HTML, scripts, external styles or
Font Awesome classes. The icon identifier is optional data for a consumer that
has an email-safe representation; the generic email fallback omits it. Always
show the escaped message even without an image. Never link unavailable items.
Preview and final preparation share this exact pipeline and guest-only policy.

## Image presentation API gap

SmartBrowser already supplies the required data-only sanitization through public
`displayData()`. No new data API is required, no HTML is rendered/parsed to get it.
However its current `present()` returns an empty string for unavailable selections,
and `render()` deliberately skips presentation for them. A placeholder cannot
currently use that public image-provider pipeline without SmartBrowser changes.
Sources deliberately does not fake an available resource or call provider events.

Needed upstream: allow `present()` (or a dedicated public equivalent) to finalize
one marked primary image for a centrally sanitized unavailable model, with empty
Usage/metadata and no original identity. Then an override may invoke it once on
its visual HTML. With no image, no provider call is needed. Until then the safe
configured image renders normally without provider processing. This is a real
remaining limitation, not a second Sources implementation.

## Migration

Update both item overrides to handle `unavailable` before their normal article
logic. Reuse each view's normal structure with the safe fields above; never send
the neutral item through article helpers. Existing available-resource branches,
Contents header-only handling and storage need no changes. The override is now
called where older Sources versions bypassed it. Old overrides must be updated
before deployment if they assume every item is a resolvable article.
