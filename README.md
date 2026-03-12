# Content - Sources (Joomla Content Plugin)

`plg_content_sources` is a Joomla content plugin that renders Sources blocks from a Subform custom field into article content tokens.

It is designed for article workflows where a custom field stores one or more source blocks and the article body decides where they should appear with tokens such as `{sources}`, `{sources:1}` and `{sources:rest}`.

## Repository Layout

- `plugin/` contains the installable Joomla plugin source.
- `build/build.ps1` creates the installable ZIP from the contents of `plugin/`.
- `build/output/` contains generated ZIP artifacts.
- `build/stage/` is a temporary staging area used during packaging.
- `build.bat` is a Windows shortcut for the PowerShell build script.

## What The Plugin Does

- Reads a Subform custom field from the current article.
- Replaces article tokens with rendered Sources output.
- Supports:
  - `{sources}` for all blocks
  - `{sources:1}` for a specific block
  - `{sources:rest}` for blocks not rendered yet
- Can append all blocks automatically if no token is found.
- Uses a template PHP file for frontend rendering.
- Supports administrator preview modes:
  - leave tokens unchanged
  - placeholder output
  - manual selections plus auto summary
  - full resolved title list
  - full HTML render

## Configuration Notes

Key plugin options:

- **Subform field name**: defaults to `blocks`.
- **Token name**: defaults to `sources`.
- **Append if token missing**: appends rendered output when no matching token exists in the article body.
- **Templates base path**: empty value uses the active template override path for article rendering.
- **Templates entry file**: defaults to `sources.php`.
- **Admin preview mode** and **Admin preview max items** control editor-side preview behavior.

By default, frontend rendering looks for:

```text
templates/<active_template>/html/com_content/article/sources.php
```

If needed, you can override both the base path and the entry file from plugin parameters.

## Installation

### Option A - Install as ZIP

1. Build or download the installable ZIP.
2. In Joomla Administrator go to **System -> Install -> Extensions**.
3. Upload the ZIP.
4. Enable **Content - Sources** if needed and configure the plugin parameters.

### Option B - Manual Install For Development

Copy the contents of `plugin/` into:

```text
plugins/content/sources/
```

Then install or discover the plugin through Joomla as needed.

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
build/output/plg_content_sources-vX.Y.Z.zip
```

The version is read from `plugin/sources.xml`.

The ZIP is created from the contents of `plugin/` at the archive root, so the result stays directly Joomla-installable without an extra source folder inside the archive.

## GitHub Releases

This repository is set up to publish the installable ZIP automatically through GitHub Actions when a version tag is pushed.

Release flow:

1. Update the version in `plugin/sources.xml`.
2. Commit and push your changes.
3. Create and push a tag that matches the manifest version, prefixed with `v`:

```powershell
git tag v1.0.0
git push origin v1.0.0
```

4. GitHub Actions will:
   - validate that the tag matches the manifest version
   - run `build/build.ps1`
   - create or update a GitHub Release
   - upload the generated ZIP from `build/output/`

Important:

- The tag must match the manifest version exactly. Example: tag `v1.0.0` must match manifest version `1.0.0`.
- Generated ZIP artifacts are intentionally kept out of git history.

