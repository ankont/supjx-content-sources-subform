# Per-Editor Presentation Context (Sources 4.2.0)

Presentation destination, operation purpose and preview mode are separate:

- presentation: site or email, explicitly declared by the consumer.
- purpose: preview at the authenticated preview endpoint; output/send remain
  owned by the corresponding final renderer/host. Clients cannot grant a purpose.
- mode: none (JSON), placeholder, list_manual, list_full, render (live).

No URL, component name, editor content, DOM selector or plugin discovery infers
the destination. Missing configuration means site, preserving existing behavior.
The context is editor-only metadata: Sources version-2 JSON is unchanged.

## Host Configuration Before Editor Initialization

Use the exact editor ID supplied to Joomla when constructing the editor field:

```php
$document->addScriptOptions('plg_editorsxtd_sourcesbutton.preview', [
    'editors' => [
        'jform_articletext' => ['presentation' => 'site'],
        'jform_newsletter_body' => ['presentation' => 'email'],
    ],
]);
```

These are illustrative IDs, not component-specific conventions. The host must
declare its own actual ID. Sources does not inspect the Newsletter host or change
it. Two editors on the same page may independently use different destinations.

## Runtime API

After the Sources module loads:

```js
window.SuperSoftSourcesPreview.setPresentation('jform_newsletter_body', 'email');
const destination = window.SuperSoftSourcesPreview.getPresentation('jform_newsletter_body');
```

The same functions are exported as setEditorPresentation/getEditorPresentation
from media/plg_editors-xtd_sourcesbutton/js/sourcesbutton.js. The setter also
accepts an explicit raw TinyMCE/JCE instance or Joomla editor decorator with an
ID. Unknown destinations throw; callers do not silently fall back to site.
Runtime overrides win over initial script options. Changing destination aborts
pending previews and refreshes the current mode without altering article data.
Declaring an ID before its editor exists applies when the editor is bound later.
Editor removal unsubscribes its refresh listener.

## Embedded Field / Builder Preview

The Sources button Builder uses the owning editor's context when its result is
inserted/updated. The embedded Sources-field Builder's Preview dialog supports:

```js
import { mountSourcesField } from '/media/plg_editors-xtd_sourcesbutton/js/field.js';
const field = mountSourcesField(explicitFieldHost, { editorId: 'jform_newsletter_body' });
// Or an independently configured field preview:
field.setPresentation('email');
// Reattach it to a declared editor context (removes the direct override):
field.configurePreviewContext({ editorId: 'jform_newsletter_body' });
```

Use the correct site/subdirectory media base in module imports. An already
mounted field is returned and reconfigured. No selector is used by Sources to
guess the context. The getter field.presentation reports the current destination.
Field destruction unsubscribes and cancels pending requests.

## Server Boundary and Final Pipeline

AJAX requests carry configuration, mode, presentation and CSRF token. The endpoint
retains its existing login/site-or-administrator/com_content authoring permission
checks. A client-provided email context is NOT authorization and does not bypass
them. Hosts whose users have no content-authoring rights need an explicit future
server authorization integration; do not fake identity or infer host permissions.

Site render remains InlineRenderer(..., purpose=preview). Email live render uses
EmailPreview only to construct protocol-v1 limits/document and temporarily enter
an anonymous identity, then calls the SAME EmailRenderer::prepare as the final
newsletter provider. No host event is dispatched and no mail is sent. The
newsletter/sourcesemail provider must be enabled, matching final availability.
The exact original identity is restored in finally, including rendering failure.
Identity-bound Joomla FieldsHelper caches are cleared on entry/exit so guest
models cannot contaminate subsequent author-side field reads. No routing/input,
stored content, persistent ACL or application configuration is changed.

The preview starts a single-Container document with the shared 2 MB byte limit.
Since 4.3.0, contents_levels controls full-body expansion for site/email and
deeper articles/cycles retain linked headers. No cumulative email item/token cap
is applied by Sources. Public article/category/ancestor/language/publication
checks and supported Usage remain unchanged. All five modes
use the final email block/item layouts and the same site-template overrides:

- html/layouts/sources/email/block.php
- html/layouts/sources/email/item.php

Contents email preview fully follows final supported email recursion, rather than
the site's collapsed nested-preview policy. Site Contents still starts nested
Containers as manual-list widgets. Those inherit the explicit parent context.
Summary modes remain the existing configuration-list UI under public policy in
email context; they do not pretend to be final email layouts. JSON/placeholder
modes remain local and issue no rendering request.

## HTML Preservation and Diagnostics

Live email output is published inside a readonly iframe sandboxed with only
allow-same-origin, without scripts. Parent-managed sizing tracks body/image size.
The iframe retains presentation tables, inline styles and image width/height
attributes, isolated from site CSS and WYSIWYG schema rewriting. There is no
global valid-elements/sanitization bypass. No iframe/email HTML is saved:
PreProcess/GetContent still serialize only original Sources tokens.

This is a Sources-block preview, not an emulator for the newsletter's full outer
template or Outlook/Gmail. The email document has minimal default typography;
email overrides should carry their own required inline styling. Server-rendered
HTML is not passed through a second JS image-presentation/crop stage.

Requests are keyed by token+mode+presentation. Mode/context replacement aborts
and invalidates earlier work; disconnected/context-mismatched responses are
ignored even if a transport disregards abort. Nested/field dialogs propagate the
same destination; iframe observers/listeners are cleaned up on replacement.
Email responses must explicitly confirm presentation=email; older/mixed backend
responses without that confirmation are rejected rather than shown as email.

Inspect Network: request presentation=email with mode=render must return
presentation=email, purpose=preview, renderer=EmailRenderer and styles=[]. Site
returns renderer=InlineRenderer. The html payload itself should contain the
expected email table/styles/image dimensions. This separates renderer selection
from formatting loss. Returned diagnostic labels contain no server filesystem
paths, subscriber information or private exception details.

## Verification

- Unit tests cover separate site/email instances, initial declaration, switching,
  stale responses, nested propagation, Builder dialogs and unchanged source JSON.
- Actual TinyMCE/JCE tests run two editor instances in site/admin at desktop/mobile
  widths; five distinct email image/layout signatures, inline styles/table role,
  isolated CSS and token-only GetContent are verified.
- Isolated real Joomla AjaxEvent compares each email preview byte-for-byte with
  the final provider, checks public-only output, no site assets and identity
  restoration on success/error; anonymous and unknown-context requests fail.
- No Catichisi template or Newsletter host source is modified. Existing enabled
  provider installations are preserved by the integration test; otherwise only
  a transient provider fixture is installed and cleaned up.
