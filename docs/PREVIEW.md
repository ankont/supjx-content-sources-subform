# Editor Preview Boundaries

Live preview uses the normal InlineRenderer and sources.item override with
purpose=preview. It does not switch Joomla identity, remove edit links from HTML,
or bypass access checks. Catichisi currently computes edit links from the current
user without consulting sources_purpose. Hiding editor actions belongs in that
shared template renderer using the existing purpose contract, not CSS filtering
or a second Sources renderer.

Inline preview bodies grow with their contents; scrolling remains the enclosing
editor's responsibility. The separate field Preview dialog retains its normal
viewport scrolling. Both previews supply Joomla's visually-hidden utility for
screen-reader-only labels, including the icon tile's More information label.

The editor iframe does not contain the site's page container ancestors. Their
max-width rules therefore cannot apply automatically. A template may include:

```css
:root { --sources-preview-max-width: 1160px; }
```

Use the site's actual shared content-width value, not this illustrative number.
Publish this rule in the stylesheet already loaded into editor previews. Sources
centers the preview surface and clamps it to the available width. Alternatively,
apply the actual site content container inside the sources.item override. Sources
does not hardcode Catichisi's dimensions or alter frontend output widths.

Normal Contents output supports nested Sources subject to the existing
access/cycle/depth guards, but not every article lifecycle is reproduced by AJAX.
The inspected Catichisi SourceContent triggers onContentPrepare only; its detail
layout does not render the ordinary article after-display field events. Also,
the Sources field template deliberately requires site view=article, so it will
not emit HTML during an AJAX preview request (nor administrator content events).
This explains a heading-only embed when an article's body is empty and its
content lives in Sources custom fields. It is not a blanket recursion ban.

Contents preview intentionally does not expand nested Sources. It retains the
normal article layout/body, suppresses only Sources field HTML and nested Sources
content-plugin output in a scoped boundary. Inline Containers become manual-list
preview blocks at their original positions in a detached introtext/fulltext copy,
before the normal template/content preparation. Only Sources field Containers
use the final in-article slot. Other plugins run normally.
These blocks use the same preview UI and AJAX endpoint as the editor, defaulting
to list_manual regardless of browser preferences. The user can explicitly switch
each to live preview; its children again start as manual lists. Nested controls
never write the global preference or the enclosing article configuration.
They intentionally omit Builder editing: their data belongs to another article.
Both editor and field-preview dialogs use the shared preview-ui.js module.
The nested blocks are based on valid nonempty inline configurations or raw Sources
fields returned by Joomla's normal field access/category/language pipeline.
Raw field reads reuse detached request snapshots; no prepared field HTML is read.
Unknown/malformed configurations are not treated as valid nested Sources.
All scope counters restore on exceptions; output rendering outside preview is
unchanged. The existing sources.item data contract is unchanged. Template
overrides must not add another nested Sources UI or recursive expansion.
No fake request view or substitute identity is used.

## In-Article Placement

For Contents preview only, sources.item receives the optional callable
render_nested_sources_preview. Forward it through the template's embedded
article layouts and echo its return value INSIDE the article frame, after the
body and before closing the article element. The callback returns safe,
server-generated Sources FIELD preview blocks and returns an empty string after its
first invocation, avoiding duplicate placement. It does not run arbitrary data.
The packaged default layout already consumes it. Old overrides that do not call
the slot retain the previous after-article fallback until updated. Actual output
and non-Contents layouts keep their existing data contract.
Do not append inline Containers through this callback: their preview markers
already occupy their original body positions. No template change is needed for
this positional correction beyond preserving the article's prepared body.

Field Preview now uses the same toolbar/header as inline editor previews.
Headers use the single block title, or merged container title, falling back to
the translated Sources label. A second line describes layout/type/selection;
multiple blocks show display mode, block count and manually selected articles,
never a guessed dynamic result count. Legacy badge text is no longer the header.
Readonly JSON controls opt out of the editor's atomic-widget key/mouse handling,
while preserving native selection/copy and preventing edits.

JSON mode uses a selectable readonly textarea, never a second configuration
editor. Changes still go through the Builder or source/code view. Placeholder
mode is block-level with a centered label; neither affects stored article HTML.
The JSON surface retains its existing frame but has no minimum empty area,
resize handle or inner scrollbar. Native field-sizing grows/shrinks with wrapped
text; older browsers use an observed-height fallback. It remains a native readonly
control to keep reliable mouse/keyboard selection in JCE and TinyMCE.
