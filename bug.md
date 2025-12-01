# PodLoom Bug Tracker

## 2024-11-30: Default show not applying in Gutenberg block

**Status**: Open

**Issue**: When setting a default show (Transistor or RSS) in Settings > PodLoom > General, the Gutenberg block doesn't pre-select it when inserting a new block.

**What we know**:
- The `podloom_default_show` option is being saved correctly in the database
- `wp_localize_script` passes `defaultShow` to the `podloom-episode-block-editor` script
- The block's `useEffect` should apply the default when `isLoaded` is true and no show is selected
- Added `editorScript` to block.json to ensure proper script/data linking

**Suspected causes to investigate**:
- `window.podloomData.defaultShow` may not be reaching the block (check browser console for the object)
- Timing issue with store loading vs useEffect execution
- Script loading order or WordPress block editor script context

**Files involved**:
- `includes/core/class-podloom-blocks.php` (lines 80-90) - `wp_localize_script`
- `blocks/episode-block/index.js` (lines 275-299) - useEffect for default
- `src/episode-block/block.json` - editorScript reference

**Debug steps attempted**:
- Added console logging (no output seen)
- Added `editorScript` field to block.json
- Removed redundant `editor_script` from PHP registration
