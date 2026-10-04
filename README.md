# SuperSoftJx - Sources

`pkg_sources` installs the Sources content plugin and an editor button plugin for Joomla authoring workflows.

The content plugin renders Sources blocks from a Subform custom field into article content tokens. The editor button plugin helps authors insert and visually identify Sources tokens inside the Joomla editor.

## Repository Layout

- `package/pkg_sources.xml` is the Joomla package manifest.
- `package/plugins/content/sources/` contains `Content - Sources`.
- `package/plugins/editors-xtd/sourcesbutton/` contains `Button - Sources`.
- `build/build.ps1` creates the installable package ZIP.
- `build/output/` contains generated ZIP artifacts.
- `build/stage/` is a temporary staging area used during packaging.

## What It Does

`Content - Sources`:

- Reads a Subform custom field from the current article.
- Replaces article tokens with rendered Sources output.
- Supports `{sources}`, `{sources:1}` and `{sources:rest}`.
- Can append all blocks automatically if no token is found.
- Uses a template PHP file for frontend rendering.
- Supports administrator preview modes.

`Button - Sources`:

- Adds a Sources editor button.
- Inserts `{sources}` into the active editor.
- Styles Sources tokens as editor badges where TinyMCE/JCE expose an editable iframe.
- Keeps the saved token compatible with the content plugin.

## Configuration Notes

Key content plugin options:

- **Subform field name**: defaults to `blocks`.
- **Token name**: defaults to `sources`.
- **Append if token missing**: appends rendered output when no matching token exists in the article body.
- **Templates base path**: empty value uses the active template override path for article rendering.
- **Templates entry file**: defaults to `sources.php`.
- **Admin preview mode** and **Admin preview max items** control Joomla admin preview behavior, not the raw editor field.

By default, frontend rendering looks for:

```text
templates/<active_template>/html/com_content/article/sources.php
```

## Installation

1. Build or download the installable package ZIP.
2. In Joomla Administrator go to **System -> Install -> Extensions**.
3. Upload the ZIP.
4. Enable **Content - Sources** and **Button - Sources** if needed.
5. Configure the content plugin parameters.

## Build

Run either:

```bat
build.bat
```

or:

```powershell
powershell -ExecutionPolicy Bypass -File .\build\build.ps1
```

The generated package is written to:

```text
build/output/pkg_sources_vX.Y.Z.zip
```

The package version is read from `package/pkg_sources.xml`.

## GitHub Releases

This repository is set up to publish the installable package ZIP automatically through GitHub Actions when a version tag is pushed.

Release flow:

1. Update the version in `package/pkg_sources.xml`.
2. Keep plugin manifest versions aligned as needed.
3. Commit and push your changes.
4. Create and push a tag that matches the package manifest version, prefixed with `v`:

```powershell
git tag v1.0.0
git push origin v1.0.0
```

Generated ZIP artifacts are intentionally kept out of git history.

## Sources Builder (1.3.0)

The article Source Blocks tab defaults to a Sources Builder in both site and administrator article forms. Each block has a collapsible card, sequential block number, move controls, Duplicate/Delete menu, and its own SmartBrowser Articles Collection View. The existing Joomla fields remain the only submitted source of truth: no field names, stored formats, or Sources tokens change.

The bottom-right Classic form toggle exposes the original native form. Returning to the Builder reads its current values. Missing SmartBrowser support or an integration failure leaves the native form accessible.

The Builder requires SmartBrowser 1.7.0+ with the public Collection View and picker APIs. Collections resolve/render selected resources and provide ordering/removal. Add Sources appends the picker's returned Articles in order, deduplicating existing selections; the current Articles picker API does not support preselecting an existing multi-selection.

Verification: `npm.cmd test` exercises the real Joomla subform lifecycle with mocked Collection/picker APIs. `node tests/browser-verify.mjs` exercises the real sibling SmartBrowser Collection renderer in desktop/mobile Chrome with mocked API responses and picker results. Live Joomla installation, Save/Apply, and reload should additionally be checked in both article editors before release.
