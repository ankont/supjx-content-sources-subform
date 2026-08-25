# Sources Package Plan

_Last updated: 2026-05-02_

## Goal

Make `pkg_sources` a complete Joomla authoring package for Sources blocks:

- `Content - Sources` owns token replacement and frontend rendering.
- `Button - Sources` owns the editor experience: inserting tokens, decorating existing tokens, and showing editor-only previews.
- Saved article content should remain token-based and compatible with `{sources}`, `{sources:N}`, and `{sources:rest}`.

## Current State

- The project has been reorganized as `pkg_sources`.
- The package installs two plugins:
  - `plg_content_sources`
  - `plg_editors_xtd_sourcesbutton`
- Current package version: `1.1.11`.
- Latest build artifact:

```text
build/output/pkg_sources_v1.1.11.zip
```

- Versions are bumped together in:
  - `package/pkg_sources.xml`
  - `package/plugins/content/sources/sources.xml`
  - `package/plugins/editors-xtd/sourcesbutton/sourcesbutton.xml`

## Completed

### Package Structure

- Converted the extension from a standalone content plugin layout to a package layout.
- Package manifest now installs the content plugin and the editors-xtd button plugin together.
- Package language files were added for `pkg_sources`.
- Build process creates `pkg_sources_vX.Y.Z.zip` under `build/output`.

### Content Plugin

- `Content - Sources` still owns frontend rendering.
- It replaces article tokens:
  - `{sources}`
  - `{sources:1}` / `{sources:N}`
  - `{sources:rest}`
- It can append rendered output when configured to do so and no token is present.
- It supports existing editor wrappers by reading the token from `data-sources-token` or wrapped token markup.
- Admin preview parsing was hardened in `1.1.11` so it can read more possible subform field names and value structures.
- **v1.1.11**: Added `onAjaxSources` frontend AJAX endpoint for render previews (see below).

### Button Plugin

- Added `Button - Sources` as an editors-xtd plugin.
- The button appears in TinyMCE and JCE.
- The button action is registered through Joomla's editor button API.
- The button now opens a token insertion dialog instead of silently inserting a default token.
- The dialog supports:
  - all blocks: `{sources}`
  - one block: `{sources:N}`
  - remaining blocks: `{sources:rest}`
- Existing tokens in the editor can be decorated as visual editor elements.
- The simple token badge remains editable in raw-token mode so authors can adjust token options by hand.

### Editor Preview Modes

The editor display settings now live in `Button - Sources`, because this plugin controls the live editor UI.

Implemented modes:

- `none`: leave/edit raw tokens.
- `placeholder`: show a compact placeholder title such as the Sources block label.
- `list_manual`: show manual selections plus automatic criteria summary.
- `list_full`: show resolved titles where possible.
- `render`: show rendered HTML preview from the server.

The placeholder title is shown as context for preview modes, so the author can still see what block is expected even before server preview finishes.

### Server-Side Preview

- Added an AJAX preview endpoint to `Button - Sources` via `onAjaxSourcesbutton`.
- The endpoint reads the saved article and its Sources subform field through Joomla field APIs.
- The preview requires a saved article ID. If the article is unsaved or the field data is unavailable, it shows the save notice.
- AJAX responses are now direct JSON responses, avoiding the previous `com_ajax` response wrapping issue.
- JavaScript cache busting was added with a media file version query string.
- Debug data is exposed in the browser console through `window.SuperSoftSourcesButtonDebug` and related last-response globals.

### Render HTML Preview (v1.1.11 — fixed)

The render preview went through several iterations:

1. **Problem**: Direct template include from admin context failed because `JPATH_THEMES` pointed to admin templates.
2. **Fix #1**: Resolved site template path via `JPATH_SITE` + DB query for default template. Template was found but rendered empty grid — Joomla's `subform_rows` had JSON string rawvalues (not decoded arrays), and `LayoutHelper::render('library.card')` couldn't find site template layouts from admin context.
3. **Final fix**: Replaced direct template include with an **HTTP request to the frontend**:
   - `Content - Sources` exposes `onAjaxSources` endpoint on the frontend via `com_ajax`.
   - `Button - Sources` calls `GET /index.php?option=com_ajax&plugin=sources&group=content&format=raw&article_id=X&indices=0,1&mode=all` from the admin.
   - The template runs in its natural site context — all layouts, models, CSS, and helpers work correctly.
   - On failure, diagnostic info (HTTP status code, response body) is included in the console debug message.

### Manual Selection Parsing

In `1.1.11`, preview parsing was broadened for manual selections:

- More possible field aliases are supported, including `block-manual-articles`, `selected-sources`, `selected-articles`, `articles`, `items`, and related hyphen/underscore variants.
- Values can be read from scalars, arrays, objects, `rawvalue`, `value`, `id`, nested structures, comma-separated strings, JSON strings, and labels ending in `[id]`.
- The AJAX debug message now includes `manual=...;keys=...` so field-name mismatches can be diagnosed quickly from the console.

### JCE Icon

- The JCE external button now shows an icon.
- The exact icon can still be refined later.

## Current Verification Status

- v1.1.11 installed and tested on new.catichisi.gr.
- All preview modes verified working: placeholder, list_manual, list_full, render.
- Render HTML preview correctly shows article cards with images from the frontend template.
- PHP lint was not run locally because `php` is not available in this machine's PATH.

---

## v1.2.0 — Polish Roadmap

### 1. Per-article preview mode switching

> Gear icon ή double-click πάνω στο sources block στον editor.

- Προσθήκη UI στοιχείου (⚙ icon πάνω-δεξιά ή context menu) πάνω στο rendered preview block.
- Αλλαγή preview mode on-the-fly: `placeholder` → `list_manual` → `list_full` → `render`.
- Η ρύθμιση plugin ορίζει το **default** mode. Ο editor μπορεί να το αλλάξει per-article/per-session.
- Η επιλογή αποθηκεύεται σε localStorage ή session (δεν αλλάζει το saved content).

**Αρχεία**: `sourcesbutton.js`, `sourcesbutton.css`

### 2. Full-width preview

> Το preview να παίρνει το πλάτος του editor, όπως θα φαίνεται στο πραγματικό άρθρο.

- Τα list/render preview blocks σήμερα είναι σε compact container. Πρέπει να γίνουν full-width μέσα στο editor area.
- Το **placeholder** badge μπορεί να μείνει compact/inline (δεν χρειάζεται full-width).

**Αρχεία**: `sourcesbutton.css`, πιθανώς `sourcesbutton.js` (wrapper markup)

### 3. Badge display → Placeholder text

> Η ρύθμιση Badge display να ελέγχει τι εμφανίζεται στο Placeholder mode.

- Σήμερα ελέγχει μόνο το compact badge text. Να αξιοποιηθεί πλήρως στο placeholder mode.
- Αλλαγή label/description στα language files για σαφήνεια.

**Αρχεία**: `sourcesbutton.xml`, `sourcesbutton.js`, language INI files

### 4. Badge label → Placeholder label

> Αντί "Suggested Sources Block", να εμφανίζει ό,τι ορίζει η ρύθμιση Badge label.

- Σήμερα default: "Sources". Αλλαγή σε κενό πεδίο με hint "Sources".
- Η τιμή ελέγχει το κείμενο στο placeholder mode.
- Ξεκαθάρισμα ότι δεν αλλάζει το token name (`{sources}` μένει σταθερό).

**Αρχεία**: `sourcesbutton.xml`, `sourcesbutton.js`, language INI files

### 5. Καλύτερα labels για preview modes

> Τα ονόματα `list_manual` / `list_full` δεν είναι κατανοητά στον editor.

Προτεινόμενα labels:

| Τιμή | Τρέχον label | Νέο label (EN) | Νέο label (EL) |
|------|-------------|----------------|----------------|
| `none` | Token badge only | Just {sources} token | Μόνο το {sources} token |
| `placeholder` | Placeholder | Placeholder badge | Ενδεικτική ετικέτα |
| `list_manual` | Manual + auto summary | Settings summary | Σύνοψη ρυθμίσεων |
| `list_full` | Full resolved titles | Resolved articles | Τελικά άρθρα |
| `render` | Full HTML preview | Live preview | Ζωντανό preview |

**Αρχεία**: language INI files (content + sourcesbutton), `sourcesbutton.xml`

### 6. Tags/Categories ονόματα + cleaner layout

> Στο list_manual mode, tags/categories εμφανίζονται ως IDs. Πρέπει να δείχνουν ονόματα.

- Fetch tag/category titles from DB στο `buildListPreview()` (SourcesButton.php).
- Αφαίρεση ή απλοποίηση sub-labels ("MANUAL SELECTIONS" / "AUTO CRITERIA") — π.χ.:
  - Χωρίς headers, απλά: `• Τίτλος 1  • Τίτλος 2  ─  Tags: Ζωγραφική · Κατηγορίες: ...`
  - Ή πιο subtle divider αντί bold sub-headings.

**Αρχεία**: `SourcesButton.php` (`buildListPreview`, `fetchTagTitles`, `fetchCategoryTitles`)

### 7–8. Heading tag/class config

> Ο τίτλος κάθε block (π.χ. "Σχετικά άρθρα") εμφανίζεται σε hardcoded `<h3>` στο template.

- Προσθήκη ρυθμίσεων στο **Content plugin** config:
  - **Heading tag**: dropdown (`h2` / `h3` / `h4` / `h5` / `div` / `none`)
  - **Heading CSS class**: text field (κενό = default template class)
- Αυτά περνάνε στο template ως μεταβλητές (`$sources_heading_tag`, `$sources_heading_class`).
- Αν tag = `none`, ο τίτλος δεν εμφανίζεται (ο editor βάζει δικό του πάνω από `{sources}`).
- Το template (`sources.php`) θα πρέπει να ενημερωθεί να τιμά αυτές τις μεταβλητές.

**Αρχεία**: `sources.xml`, `Sources.php`, language INI files, `sources.php` (template)

---

## Decisions

- Save raw tokens where possible; avoid persisting full visual preview HTML as article content.
- Require the article to be saved before server-side preview if reading unsaved subform data is brittle.
- Keep `Content - Sources` as the source of truth for token name, subform field name, and frontend rendering settings.
- Avoid deep JCE-specific work unless a generic Joomla editor-button implementation cannot cover the behavior.
- Token name `{sources}` is fixed — not configurable.
- Render HTML preview uses HTTP request to frontend (not direct template include from admin).

## Later Improvements

- Make the insertion dialog list actual saved Sources blocks with their titles.
- Allow choosing a block by title instead of manually entering a number.
- Show whether each block has manual selections and/or automatic criteria inside the insertion dialog.
- Consider a shared helper if parsing/rendering logic between the two plugins grows further.
- Add automated PHP lint/build checks once PHP is available in the local environment.