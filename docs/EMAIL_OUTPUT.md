# Sources Email Output (Package 4.1.0 / Provider 1.0.0)

## Installation and Contract

Install the Sources package, then enable **Newsletter - SuperSoftJx - Sources
Email** with **Public** access. This optional newsletter/sourcesemail plugin
subscribes only to onNewsletterPrepareEmail, exact context
com_newsletterbridge.newsletter, mutable Joomla Registry document version 1.
It has no dependency on Newsletter Bridge PHP classes and does nothing without
the event. Native article output does not need SmartBrowser or SmartCrop.
No-token content does not boot the Sources plugin, run queries or load assets.
Existing Sources plugins share package version 4.1.0; this new provider has its
own version, initially 1.0.0.

The host owns author/job ACL, the anonymous dispatch identity, placeholders, URL
normalisation, snapshots and sending. The provider verifies publicOnly=true and
the anonymous identity, uses a public article/category/ancestor policy, and never
loads subscriber data, redispatches the event or invokes a mail transport. Only
html, usage.items, usage.tokens and appended errors change in the document.
Failures keep the original document HTML and append sources.email.failed without
private exception details. No public article matches intentionally produces empty
Sources output. Malformed, encoded, misplaced, legacy and future syntax fails
closed rather than leaking raw tokens into email.

## Shared Data, Different Presentation

InlineRenderer::resolveBlocks is the same data-only selection/merge/order path
used by site output. Email uses its explicit public-only constructor policy,
bounded query sizes and a separate per-document item/token/depth budget. There
is no second parser, schema or article selector. Native article filters include
state, article/category/ancestor access, publication windows and site language
policy. Public Sources fields additionally check field/group access and state.
Request snapshots are keyed by identity/policy, query and query limit; the email
renderer never invokes SmartBrowser prepareOutput or site sources.item layouts.

All five display modes use presentation tables and inline styles: Cards stacks
image/title/summary; Details puts the image alongside text; List is compact;
Icons uses small image/title tiles; Contents includes supported article content.
Grid modes progressively stack via fluid inline-block widths. Outlook may stack
or size these differently; identical rendering across clients is NOT promised.
There are no site classes/assets, scripts, interactive controls or icon fonts.

Contents supports native introtext/fulltext, basic HTML formatting, safe image/link
URLs, the native article image, inline Sources in place and accessible modern
Sources fields at the end. No generic content events or arbitrary field rendering
are invoked. Catichisi's field/resource-based article composition is template
functionality and is NOT copied. Other resource/custom-field content, embeds,
downloads, interactive viewers and template-only metadata remain available through
the article title's site link. Authors must verify email content before sending.

Displayed-name Usage is supported. Native Joomla image metadata is supported.
Icon-only presentation omits the image; decorative font-icon overrides are not
used in email. Media thumbnail references and extension/crop Usage currently fail
explicitly as unsupported; they are not guessed, silently discarded or interpreted
through SmartCrop. No optional media/resource provider is shipped in 1.0.0.

## Native Joomla Overrides

Provider fallbacks:

- plugins/newsletter/sourcesemail/layouts/sources/email/block.php
- plugins/newsletter/sourcesemail/layouts/sources/email/item.php

Site overrides (normal Joomla parent-template lookup applies on the site):

- templates/YOUR_TEMPLATE/html/layouts/sources/email/block.php
- templates/YOUR_TEMPLATE/html/layouts/sources/email/item.php

Component-specific html/layouts/com_content/sources/email/... is also supported.
For administrator rendering, the default site template's override paths are used.
Without overrides, provider layouts work independently of the site's appearance.

block receives title, layout and items. Each item contains id, title, url, image,
summary, content and its already rendered html. item receives item and layout;
the item model at that point has no html property. Values remain unescaped data
except content, which is sanitised/safely rendered HTML. Escape title/summary/URLs
with EmailHtml::escape. Overrides are trusted installed PHP; keep their HTML
email-safe. They control presentation, not public resolution, budgets or sending.

## Limits and Tests

Since 4.3.0, the provider respects the smaller host byte limit, capped at
2,000,000 bytes. Legacy maxItems/maxTokens/maxDepth document entries are accepted
but no longer impose cumulative email limits. Counters still record all work.
The shared Content plugin contents_levels setting (default 3) controls full-body
expansion for site/email. Deeper Contents and cycles render header-only through
the existing item layout; no body or Sources fields are expanded there.
Shared selector/schema bounds and configured selection limits remain unchanged.
Byte validation also applies to generated layout HTML. No provider cache spans jobs.
See [Contents header contract](CONTENTS_HEADERS.md).

- npm test: email fixtures plus existing Sources regressions.
- tests/email-joomla.php: opt-in isolated local Joomla integration, used with a
  temporary candidate package under /tmp/sources-email-... in the authorised
  catichisi-local-web-1 container. It refuses an existing provider registration,
  installs it transiently through the real loader and cleans up in finally.
- It creates randomly prefixed empty tables, only synthetic articles/newsletters/
  subscribers, and a memory-only mailer. No existing content/subscriber is changed.
- Real bridge preview/test/start/process/resume exercise the same output, author
  identity restoration and snapshots unaffected by later fixture article edits.
- These checks are NOT proof of Outlook/Gmail delivery or visual compatibility.

Verified locally on 2026-10-26: 42 automated test cases, eight TinyMCE/JCE
site/admin browser scenarios, ten email layout/viewport browser fixtures, and
30 real Joomla loader/public-policy/field/override/bridge lifecycle assertions.
The lifecycle test intercepted three messages in memory only and verified
cleanup of its provider registration/files and every synthetic table.

No commit, push or release is implied by building the installable ZIP.
