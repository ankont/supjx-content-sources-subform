# Sources Email Output (Package 6.0.0 / Provider 1.4.1)

## Installation and Contract

Install the Sources package, then enable **Newsletter - SuperSoftJx - Sources
Email** with **Public** access. This optional newsletter/sourcesemail plugin
subscribes only to `onNewsletterPrepareEmail`, with a mutable Joomla Registry
document, version 1 or 2. Any host context is accepted (including
`com_newsletterbridge.newsletter` and `com_smartnewsletter.dispatch`); context is
diagnostic information, not an authorization mechanism.
It has no dependency on Newsletter Bridge PHP classes and does nothing without
the event. Native article output does not need SmartBrowser or SmartCrop.
No-token content does not boot the Sources plugin, run queries or load assets.
Content/Button/Fields share package version 6.0.0; the newsletter provider has
its independent version 1.4.1 (introduced at 1.0.0).

The host owns author/job ACL, recipient identification, email addresses,
personalization, files, URL normalization, queueing, snapshots/history and sending.
Sources owns parsing, resolution and rendering of Sources tokens. It scopes and
restores the Joomla identity itself; operator privileges never determine output.
It never loads subscriber data, redispatches the event or invokes mail transport.
Successful v1 changes only html and usage counters, preserving the Bridge's exact
response whitelist. Failures keep original HTML/counters and append error codes.
V2 additionally reports capabilities/effective policy; warnings report a private
request downgraded to public. No accessible article matches intentionally produces empty
Sources output. Malformed, encoded, misplaced, legacy and future syntax fails
closed rather than leaking raw tokens into email.

## Shared Data, Different Presentation

InlineRenderer::resolveBlocks is the same data-only selection/merge/order path
used by site output. Email uses an explicit reader policy, bounded query sizes,
the shared Contents depth policy and a per-document byte budget. There
is no second parser, schema or article selector. Native article filters include
state, article/category/ancestor access, publication windows and site language
policy. Sources fields additionally check field/group access and state for the
effective reader, including private readers. Dynamic tags also check publication,
language and reader access.
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
email-safe. They control presentation, not resolution, budgets or sending.
Introduction/group overrides receive `public_only` for the effective policy.
The existing item/block, header-only, unavailable and introduction contracts are
unchanged; see [introductions](INTRODUCTIONS.md),
[unavailable presentation](UNAVAILABLE_PRESENTATION.md) and
[Contents headers](CONTENTS_HEADERS.md).

## Host-Neutral Request Contract

Required Registry fields (strict types):

| Field | Value |
| --- | --- |
| version | integer 1 (public compatibility) or 2 (capabilities/private) |
| purpose | string preview, test or send |
| publicOnly | boolean; true always scopes rendering to guest id 0 |
| html | string containing the email HTML/tokens |
| rootUrl | absolute http(s) site root with trailing slash |
| limits.maxBytes | positive integer; Sources caps it at 2,000,000 |

Optional: `usage.items`/`usage.tokens` nonnegative integers (default 0),
`errors`/`warnings` arrays of string codes (default empty).
`unresolved` and other host-owned values are untouched.
`maxItems`, `maxTokens` and `maxDepth` are neither required nor used; Sources
retains its own schema/query bounds, configured limits, `contents_levels`, cycle
guards and header-only behavior. No storage/schema migration is required.
Unknown versions or a non-Registry document are ignored, not silently interpreted.
Malformed supported documents append `sources.email.invalid_document`.

### Public v1 (Existing Bridge)

```php
use Joomla\Registry\Registry;
use Joomla\Event\Event;

$document = new Registry([
    'version' => 1, 'purpose' => 'preview', 'publicOnly' => true,
    'rootUrl' => 'https://example.org/', 'html' => $html,
    'limits' => ['maxBytes' => 2000000],
    'usage' => ['items' => 0, 'tokens' => 0], 'errors' => [],
]);
$dispatcher->dispatch('onNewsletterPrepareEmail', new Event(
    'onNewsletterPrepareEmail', [
        'context' => 'com_newsletterbridge.newsletter', 'document' => $document,
    ]
));
// Do not send if errors is nonempty. Read prepared HTML from $document.
```

The same public document from any other context produces the same HTML. The host
does not need to change its identity. V1 adds no capability metadata on success:
the existing Bridge validates an exact top-level response whitelist.

### Private v2 and Capability Discovery

Enable **Allow private email rendering** in **Newsletter - SuperSoftJx - Sources Email**
(`email_private_enabled`, default 0). The former Content plugin setting is ignored;
explicitly enable the switch in Sources Email after upgrading if needed.
A private request must use v2, boolean
`publicOnly: false` and a positive integer `userId`. Sources loads that Joomla
account through UserFactoryInterface and requires an existing, non-guest,
unblocked, activated account with readable authorized view levels. Only `userId`
is accepted as the reader identity: no caller-supplied groups, access levels or
User object. Public requests ignore userId and always use guest permissions.

```php
$document = new Registry([
    'version' => 2, 'purpose' => 'send', 'publicOnly' => false,
    'userId' => $verifiedJoomlaRecipientId, // integer, verified by the trusted host
    'rootUrl' => 'https://example.org/', 'html' => $html,
    'limits' => ['maxBytes' => 2000000], 'errors' => [], 'warnings' => [],
]);
$dispatcher->dispatch('onNewsletterPrepareEmail', new Event(
    'onNewsletterPrepareEmail', [
        'context' => 'com_smartnewsletter.dispatch', 'document' => $document,
    ]
));
$provider = $document->get('providers.sourcesemail');
// Check errors and warnings before storing/sending the resulting html.
```

V2 returns `providers.sourcesemail`:

```json
{
  "capabilities": {
    "versions": [1, 2], "privateRendering": true,
    "privateEnabled": true, "policyAvailable": true
  },
  "effectivePublicOnly": false,
  "effectiveUserId": 123
}
```

Probe with a valid **v2 public request and empty html** to discover capabilities
without resolving Sources, loading content or booting the content plugin. Or call
the newsletter plugin's public `getCapabilities()` method. Missing v2 capability
metadata means no declared private support; do not assume plugin version alone
authorizes private output. `privateRendering` declares support; `privateEnabled`
declares the central opt-in; `policyAvailable` declares readable configuration.
Effective fields are null on the no-token probe or failed preparation. After
successful public fallback they are true/0; requested publicOnly/userId remain
unchanged. The context and client input are never authorization credentials.

### Warnings, Failure and Isolation

Private failure appends one of these **warnings**, then retries the original
HTML with guest permissions. It does not fail the document when fallback succeeds.

| Warning code suffix (prefix sources.email.) | Cause |
| --- | --- |
| private_contract_required | Private requested with v1; use v2 |
| private_disabled | Central private-email switch is off |
| user_missing | No explicit userId |
| user_invalid | Wrong type/nonpositive ID, missing account or guest |
| user_blocked | Account is blocked |
| user_inactive | Account has a pending activation value |
| private_access_unavailable | Configuration, user factory or access cannot be verified |
| private_render_failed | Private preparation failed; partial output discarded |

If public preparation also fails, `errors` receives `sources.email.failed` and
original HTML/counters remain intact. Exception details and private data are not
returned. `invalid_document` is a structural error, not a downgrade. The host
must decide whether a warning/public fallback is acceptable or to stop the job;
it must never send unresolved original tokens after an error.

Identity changes are scoped in try/finally, including layout exceptions. Each
reader gets a new EmailRenderer; request snapshots include effective reader id,
view levels/groups, policy and language, and return detached data, not shared
mutable article objects or cached HTML. Joomla FieldsHelper caches are cleared
at identity boundaries to prevent cross-reader field disclosure. There is no
cross-job email cache. Installed PHP overrides remain trusted code and must not
change identity or bypass the reader policy. Links grant no additional access:
following private links still requires normal Joomla login. Hosts own recipient
verification and the risks of forwarding a private email.

### SmartNewsletter Caller Changes

Keep `com_smartnewsletter.dispatch` and maxBytes-only limits. Public operation may
continue with v1. For private output, discover v2/private support, require the
enabled policy, send v2/false plus the verified integer Joomla recipient userId,
then inspect warnings/errors and effective policy. Do not send recipient email,
groups or a User object as the identity contract. No host identity switch is
needed. Any future recipient snapshot/history cache must be partitioned by
recipient/policy (and invalidated when appropriate); Sources does not own it.

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
- tests/php-email-hosts.php: multiple contexts, maxBytes-only requests, two readers,
  gate/account warnings, failure rollback, exact identity restoration and cache
  isolation without resetting caches between readers.
- tests/email-joomla.php: opt-in isolated local Joomla integration, used with a
  temporary candidate package under /tmp/sources-email-... in the authorised
  catichisi-local-web-1 container. It preserves an enabled existing registration,
  or installs it transiently through the real loader and cleans up in finally.
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

Verified on 2026-11-23 with the candidate loaded from /tmp (no installed plugin
replacement): 60 real Joomla loader/event/ACL/Bridge assertions, including two
active recipient accounts with different access, private field ACL, category and
ancestor ACL, public v1 response compatibility, alternate context/maxBytes-only,
blocked/missing user fallback and reader/public cache isolation. Three messages
were intercepted in memory only; all synthetic tables were removed. These are
integration checks, not frontend TTFB profiling or real email delivery tests.
