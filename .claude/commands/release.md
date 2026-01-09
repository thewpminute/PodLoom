# Release Manager

Create a new release for the PodLoom plugin. This command handles the complete release workflow.

## Arguments

The command accepts an optional version argument: `/release 2.16.0`

If no version is provided, ask the user what version to release.

## Pre-flight Checks

Before starting, verify:
1. We're on a feature branch (not main) OR there are uncommitted changes to release
2. All changes are committed on the current branch
3. The working tree is clean (no uncommitted changes)

If on main with no changes, ask the user what they want to release.

## Release Workflow

### Step 1: Version Bump
Update the version number in these files:
- `package.json` - the "version" field
- `podloom-podcast-player.php` - both the header comment `Version:` and the `PODLOOM_PLUGIN_VERSION` constant
- `readme.txt` - the `Stable tag:` field

### Step 2: Changelog
Ask the user for changelog entries, or review recent commits to suggest entries.

Update `readme.txt`:
- Add new version section under `== Changelog ==` (after the header, before previous versions)
- Add new version section under `== Upgrade Notice ==` (after the header, before previous versions)

Format for changelog entries:
```
= X.Y.Z =
* **Feature Name**: Description of the change
```

Format for upgrade notice:
```
= X.Y.Z =
Brief one-line summary of the release.
```

### Step 3: Commit Version Bump
```bash
git add package.json podloom-podcast-player.php readme.txt
git commit -m "chore: Bump version to X.Y.Z"
```

### Step 4: Merge to Main
If on a feature branch:
```bash
git checkout main
git merge <feature-branch>
```

### Step 5: Push to Origin
```bash
git push origin main
```

### Step 6: Create Release Tag
```bash
git tag -a vX.Y.Z -m "vX.Y.Z - Brief description"
git push origin vX.Y.Z
```

### Step 7: GitHub Release
Wait a few seconds for GitHub Actions to create the release, then update it with changelog notes:
```bash
gh release edit vX.Y.Z --notes "<changelog content>"
```

If no release exists yet, create one:
```bash
gh release create vX.Y.Z --title "vX.Y.Z" --notes "<changelog content>"
```

## Post-Release

After completing the release:
1. Confirm the release URL
2. Optionally delete the feature branch if requested

## Error Handling

- If any step fails, stop and report the error
- Do not force push or use destructive git commands
- If the tag already exists, ask user how to proceed
