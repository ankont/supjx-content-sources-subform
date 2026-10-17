# Selection View Follow-Up

## Article Usage Integration (3.2.0)

- Add an Articles capability for displayed name; empty values use the resolved
  article title. This changes the selected item's presentation, not the article.
- Add a presentation mode for thumbnail, icon or both, reusing the existing
  visual.thumbnailOverride and visual.iconOverride capabilities.
- Define how SmartCrop uses the effective article thumbnail, writes registered
  extension usage/metadata, survives replacement and restores on reopen.
- Publish the capability keys, validation and rendering contract for callers.
  Editable usage alone is not sufficient: callers must enable the profile,
  preserve usage on collection/picker changes and persist it with each reference.
- Implemented: all Sources collections and pickers use editable Usage profiles.
  Name and thumbnail are primary; icon and visual presentation are secondary,
  initially collapsed through the native SmartBrowser mechanism.
- Numeric IDs remain valid. Only references carrying non-null Usage become
  canonical SmartBrowser selection entries in the same ordered array. No
  secondary map or separately synchronized storage is introduced.
- Reserved ext.* JSON is retained, including future SmartCrop metadata, without
  interpreting extension-owned payloads. Crop editing of an effective article
  thumbnail remains the SmartCrop integration owner's responsibility.
- Existing template layouts receive sources_usage and sources_visual. Displayed
  names and resolved images also populate cloned native article fields. Custom
  icons/presentation need those layouts to consume the explicit visual contract;
  the package's standalone fallback already does. No template files are edited.

Sources uses the public Collection View without cloning its renderer.

## Preferences

The existing public viewPreferenceKey already supports isolation. Sources sets
supjx.sources.selection (separate from Fields/default collections).
CollectionState stores grid/details at that key; CollectionView stores listOpen
and infoState at its multiple-layout suffix. No SmartBrowser change is required
for Sources-level isolation. Stable per-block preferences across content edits
would need a durable identity strategy; Sources does not add IDs to its JSON
just to remember UI state.

## URI Editing

ResourceInfoPanel.vue currently renders the selection URI as a disabled input.
Expose explicit read-only styling, or a public allowReferenceEdit/onReferenceChange
contract. Editing must resolve/validate the new adapter-qualified reference and
replace that collection item at the same position. Invalid input must preserve
the existing selection and report an error. Respect allowed adapters, resource
types, permissions, duplicates and unavailable resources. Do not treat the URI
as an independent editable string or change item metadata in Sources.

For Sources this is an Articles reference (article:43), not the Intro Image file
path. The existing per-resource Replace action is currently the safe workflow.

## Preview Position

CollectionView.vue always passes selectionPosition to ResourceInfoPanel.
Hide the title counter when the list is visible in the side layout. For vertical
gallery navigation keep accessible previous/next buttons; neighboring positions
(for example 1 and 3 when viewing item 2) could be supplemental labels, not
replacement accessible names. Handle boundaries, missing references, changes to
selection and reordering. Implement centrally, not through Sources CSS overrides.
