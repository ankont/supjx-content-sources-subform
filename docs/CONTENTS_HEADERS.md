# Contents Header Fallback (4.3.0)

Content - SuperSoftJx - Sources has one `contents_levels` integer setting,
minimum 1, default 3. The first selected article is level 1, not the enclosing
page/newsletter. Both site and email use it; it is not stored in Sources JSON.
Level N+1 renders a linked header instead of the body. Active-path cycles use
the same fallback without suppressing unrelated siblings or later repetitions.
Non-Contents layouts do not recursively expand article bodies.

Email cumulative article/container caps are removed. Existing public-only,
publication, language, schema/selector bounds and byte checks remain intact.
Host protocol counters are still populated. Old document maxItems/maxTokens/
maxDepth values no longer override this shared presentation policy.

IMPORTANT: the current Newsletter Bridge independently rejects usage.items >60
or usage.tokens >8 AFTER the provider event. Removing Sources caps cannot remove
that host-side rejection. Do not clamp counters or mutate the host policy to
bypass it. A separate host change is needed before sending larger newsletters.

## Site Item Contract

`sources.item` still receives item (object), layout, selected, purpose and
parent_article_id. Additive fields:

- `header_only`: boolean, true only for Contents fallback.
- `fallback_reason`: `depth`, `cycle` or null.
- `depth`: 1-based article level.
- `read_more_text`: translated text for a header-only article, otherwise empty.

The item is a detached copy. In header-only mode its introtext/fulltext/text are
empty and prepared Sources field rendering is disabled. The override must not
refetch/prepare the full article by ID or expand its fields. Keep using selected
for Usage and visual presentation. No forced SmartBrowser rendering runs before
or after the override. An override that bypasses this contract by fetching fresh
content cannot be made safe by the Sources guard alone.

## Email Item Contract

`sources.email.item` retains layout=`contents`, not `list`. The item array now
also carries header_only, fallback_reason, depth and read_more_text. The first
three are additionally passed at displayData level. Existing id/title/url/image/
summary/content remain; content is empty for header-only. URLs are absolute,
title already reflects supported displayed-name Usage. Escape titles, URLs and
read_more_text. Do not rerun selection, content preparation or presentation hooks.
The generic email fallback uses a compact image beside the linked heading and
a small translated read-more line, not a UL. Template overrides own their look.

Site editor previews retain their existing interactive nested widgets/manual-list
default. They apply the same policy when rendering Contents within a request;
explicitly opening a nested widget starts a new preview root. Email preview uses
the exact final email renderer and automatic depth/header behavior.

## Prompt for Catichisi

Update only the existing Sources item overrides for the Sources 4.3.0 contract.
Do not change ordinary article rendering or selection/storage.

1. In sources.item, when layout is contents and displayData.header_only is true,
   render only the usual article heading (thumbnail/pin, category/date where
   available), with the effective title linked to the normal article URL.
   Preserve selected Usage/visuals and the shared presentation pipeline once.
2. Beneath the heading show escaped displayData.read_more_text in small subdued
   type. No body, fields, content.prepare events or nested Sources; do not refetch
   a complete article to bypass the detached empty body. Leave full Contents
   behavior unchanged when header_only is false. Keep existing purpose handling.
3. In sources/email/item.php, apply the same header-only branch using
   item.header_only, item.title, item.url, item.image and item.read_more_text.
   Keep layout=contents; use email-safe presentation tables/inline styles and
   explicit image dimensions. Do not invoke site article rendering in email.
4. No changes needed to sources/email/block.php unless necessary for the existing
   heading spacing. No new parser, resolver, schema or recursion policy.
5. Verify three full article levels and fourth-level headers, cycles as headers,
   configured depth=2, siblings, Usage overrides, site/email and preview/output.
   Ordinary articles and the other four Sources layouts must remain unchanged.

## Separate Prompt for Newsletter Bridge

Sources 4.3.0 removes cumulative article/token caps and still publishes accurate
usage counters. In EmailPreparation::render, remove the hard-coded >60/>8
post-event rejection (keep integer/nonnegative validation). Stop advertising
maxItems/maxTokens/maxDepth as enforced provider policy, or explicitly retain
them as legacy ignored metadata. Keep publicOnly, exact policy immutability,
errors/unresolved checks, supported-placeholder checks, the 2 MB limit and
render-once job snapshots. Do not zero/clamp counters. Add tests proving >60
articles/>8 containers pass through the provider and bridge, and malformed,
private or oversized output still fails. Sources owns the shared contents_levels
setting and depth/cycle header fallback; the host must not add a second renderer.
