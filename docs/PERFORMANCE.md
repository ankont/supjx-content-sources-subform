# Sources Rendering Boundaries and Local Profiling

## Evidence and Root Cause

In the synchronized local Joomla 6.2.0 Catichisi runtime, a 30-card proposal
reproduced 10,387 FieldsHelper::getFields calls, 945 SelectionFieldSupport::prepare
calls, 588 resourceItem invocations and 18 Sources renders. The previously
captured caller trace attributes 15 of those renders to ItemData::indexFields
requesting all fields with prepareValue=true.

A Sources custom field was an HTML-producing template, not a cheap data getter.
Preparing fields for card metadata therefore rendered the article's Sources too.
Separately, LegacySources requested full field preparation even on articles with
no legacy output. Eager hidden tag landing content events amplify both paths,
but belong to the template rather than Sources alone.

getFields counts are not SQL query counts. Instrumented inclusive durations
overlap and MUST NOT be summed.

## Minimal Boundary

Sources adds SourceFieldData, in its existing content-plugin namespace. It wraps
only the Sources field's HTML generation, not the Joomla event dispatcher:

```php
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use SuperSoft\Plugin\Content\Sources\Support\SourceFieldData;

// If every needed field is raw data, Joomla already provides the complete API:
$rawFields = FieldsHelper::getFields('com_content.article', $item, false);

// If resource fields need their normal preparation, suppress only Sources HTML:
$fields = SourceFieldData::withoutRendering(
    static fn() => FieldsHelper::getFields('com_content.article', $item, true)
);

foreach ($fields as $field) {
    if ($field->type === 'sources') {
        $configuration = SourceFieldData::configuration($field);
        // Validated version-2 array or null. Reads rawvalue, never parses rendered HTML.
    }
}
```

Boot the enabled content Sources plugin before using its helper if its namespace
has not already been registered, e.g. app->bootPlugin('sources', 'content').
Do not enable a disabled plugin merely to read raw fields. The configuration
helper validates data; it is NOT an authorization boundary or a new resolver.
Use Joomla's normal field access/category/language pipeline first.

withoutRendering affects only Sources custom-field HTML. Other field
Before/Prepare/After events still run. Inline content processing and other content
plugins are not globally skipped. Raw fields keep their original rawvalue; the
Sources field's display value is intentionally empty in this data-consumer scope.
Use configuration(), not value, for its data.

### Specific Catichisi Consumer Change

In ItemData::indexFields replace the existing:

```php
$item->jcfields = FieldsHelper::getFields('com_content.article', $item, true) ?: [];
```

with:

```php
$item->jcfields = SourceFieldData::withoutRendering(
    static fn() => FieldsHelper::getFields('com_content.article', $item, true)
) ?: [];
```

Only use that form in metadata/resource indexing, not in the actual article's
Sources display path. Keep normal field rendering at the true output boundary.
Sources applies this scope automatically around non-Contents sources.item
layouts and its own readonly selection preparation; the explicit consumer
change additionally covers template callers outside those Sources-owned paths.

## Data Preparation Versus HTML

InlineRenderer::resolve remains the existing published/access/language-aware
article selector. prepareOutput is now public; it returns the same complete
readonly-prepared selection map used by rendering, without layouts or presentation:

```php
$config = SourceFieldData::configuration($field);
if ($config !== null) {
    $renderer = new InlineRenderer($contentPluginParams);
    foreach ($config['blocks'] as $block) {
        $articles = $renderer->resolve($block);
        $selectedByReference = $renderer->prepareOutput(
            $articles, [(int) $item->id, 'metadata']
        );
    }
}
```

Use validated blocks and normal Joomla plugin parameters. resolve/prepareOutput
are per-block operations, not a second Container merge/ordering implementation.
If actual Container HTML is wanted, use render as before; it owns merge/order,
headings, limits and layout dispatch. The optional context array partitions
prepared snapshots for host/purpose-specific decoration; render passes host ID
and purpose automatically. Direct consumers should supply their corresponding
stable context. No new endpoint, storage, serialization or data model exists.

## Cache Scope and Safety

- Query snapshots key the actual SQL, including publishing time/language/access
  clauses, result shape and connection identity. Usage is applied only after
  retrieving a detached article snapshot.
- Prepared selections key the complete reference and Usage, public API settings,
  consumer context and principal ID/view levels/groups/language/client. Cache
  hits are per entry; only misses are batched through the existing prepare API.
- Returned unavailable references may be cached as absent for this request only.
- Snapshots are internal serialized values, not shared article/Registry/metadata
  object instances. Each read reconstructs a detached graph. Unserializable or
  oversized results are not cached.
- Bounds: 128 entries, 512 KiB per entry, 4 MiB total. RequestCache::reset clears
  snapshots if a trusted in-request writer changes source data or policy.
  There is no persistent or cross-user cache.
- No rendered HTML, field HTML, layout result or presentation-provider invocation
  is cached. Each genuinely requested visual still runs the normal renderer.

## Legitimate Embeds and Cycles

Sources field rendering tracks active article/field/config identities and retains
the existing maximum of three active fields. Re-entering the same field stops;
distinct embedded articles may still render. All guards release on exceptions.
Non-Contents card metadata runs in the data-only scope. Normal Contents output
explicitly restores field rendering so legitimate embedded Sources are not suppressed.
Contents preview instead uses the manual nested preview UI documented in PREVIEW.md;
live expansion is opt-in per nested block, not automatic recursion.
An intentionally embedding custom consumer can use withRendering(callback).

sources.item retains its public item/layout/selected/purpose/parent_article_id
contract and existing item/self/recursion checks. No extra HTML finalization is
added. Preview uses the same renderer with purpose=preview.

Legacy handling also guards active article IDs against cycles. It first checks
enabled state, inline precedence, token/append policy and template readability.
It reads raw fields once per request/category/article/language/policy snapshot.
Modern nonempty Sources fields take precedence without rendering them. Only a
genuine nonempty legacy subform triggers full field preparation, preserving
subform_rows, merge settings, other field plugins, numbered/rest tokens and
append_if_missing. Full prepared legacy fields/HTML are NOT cached.

## Actual Joomla Frontend Measurement

Paired anonymous HTTP requests to the same synchronized local proposal:

/selections/weekly/2o-mathima-mesis-anoteris-vathmidas.html

| Measurement | Before | Candidate |
| --- | ---: | ---: |
| HTTP status | 200 | 200 |
| HTML TTFB | 34.017812 s | 4.478848 s |
| Total HTTP time | 34.029171 s | 4.488991 s |
| FieldsHelper::getFields | 10,387 | 1,209 |
| SelectionFieldSupport::prepare | 945 | 95 |
| InlineRenderer::render | 18 | 2 |
| InlineRenderer::prepareOutput | 156 | 18 |
| InlineRenderer::resourceItem | 588 | 60 |

Before that pair, an independent baseline measured 35.495499 s with the same
counts. These are individual diagnostic requests, not a statistical latency
benchmark; warm-up and profiling overhead affect timing. Counts establish the
amplification reduction. Do not equate the remaining two renders/60 item calls
with two visible sets of cards: the DOM still has 30 visible Sources cards.

The candidate DOM retained all 30 cards' order, classes, text/titles, links,
image source/alt/styles/server markers and pins. Both HTML responses were
452,057 bytes; whole-page bytes were not claimed identical (dynamic page data).
Legacy-origin getFields calls dropped from the supplied 1,525 prepared calls to
10 raw reads in this candidate trace. No claim is made about SQL totals.

The measured candidate contains the data-only scopes, snapshot caching and
legacy raw preflight. The subsequent explicit legacy cycle guard is covered by
fixtures; a final repeat was refused because another local profiling session
was already active. That session was not modified or restored by this work.

tools/profile-local-sources.ps1 uses the existing local-only method profiler,
backs up the installed Sources files, temporarily copies only five Sources PHP
files, and always hash-checks/restores them. The profiler temporarily instruments
installed methods with exact backups; no Catichisi/SmartBrowser/SmartCrop source
repository or stored data is edited. Outputs go under ignored tests/artifacts.
The completed measurement restored both profiler originals and installed Sources
files. Coordinate with other local profiling sessions before running it.

## Fixture Measurements and Regression Coverage

tests/php-performance.php compares the SAME renderer with its request cache reset
before each iteration versus retained across 100 data-only iterations. It uses
mock loaders/APIs, not real SQL, and emits measured wall time:

| Fixture work, 100 iterations | Cache reset | Request cache |
| --- | ---: | ---: |
| Mock article loads | 100 | 1 |
| Mock selection prepare calls | 100 | 1 |
| Layout/HTML calls | 0 | 0 |
| Example observed wall time | 4.704 ms | 3.940 ms |

Those milliseconds are not Joomla frontend timings and do not model network/DB
latency. Fixtures additionally cover mutable-object isolation, differing Usage,
principal changes, unavailable results, repeated field consumption, normal output,
preview, Contents embedding, A-B-A cycles, scope exception cleanup, modern/legacy
precedence, legacy append/rest and preservation of other field preparation.
Existing generic-output/presentation, ACL/order and editor tests remain in place.

## Still Owned by Catichisi

- Stop eager PHP preparation of hidden tag landing articles; hidden template HTML
  is not lazy execution. Preserve their content events when truly rendered.
- Apply the metadata-only consumer scope above outside Sources-owned card paths.
- Investigate remaining repeated resource-field preparation and the two surviving
  root field render requests; this patch deliberately does not cache their HTML.
- Own the timing and preparation of genuine full article embeds/popups. Sources
  does not bypass other content plugins to hide that cost.

No Catichisi, SmartBrowser or SmartCrop source files were changed. No page cache,
recursion-limit increase, schema/data migration or deployment to live is involved.
