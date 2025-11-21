# Build Instructions

## Creating a Release Build

To create a clean zip file ready for WordPress plugin distribution:

```bash
./build.sh
```

This will:
1. ✅ Extract version from `podloom-podcast-player.php`
2. ✅ Create a clean copy excluding development files
3. ✅ Generate a zip file in `build/releases/`
4. ✅ Name it `podloom-podcast-player-{version}.zip`

## What's Excluded

The build script automatically excludes:
- `.git/` - Git repository
- `.gitignore` - Git configuration
- `.claude/` - Claude Code configuration
- `.DS_Store` - macOS system files
- `docs/` - Development documentation
- `README.md` - Development readme
- `build.sh` - This build script
- `build/` - Build directory
- `BUILD.md` - This file
- `debug-*.php` - Debug files
- `node_modules/` - NPM dependencies (if any)
- `.vscode/`, `.idea/` - Editor configurations

## What's Included

Everything needed for the WordPress plugin:
- `podloom-podcast-player.php` - Main plugin file
- `uninstall.php` - Uninstall cleanup
- `admin/` - Admin interface
- `assets/` - CSS and JavaScript
- `blocks/` - Gutenberg block
- `includes/` - PHP classes and functions
- `languages/` - Translation files (if any)

## Output

```
build/
└── releases/
    └── podloom-podcast-player-{version}.zip
```

The generated zip file is ready to upload to:
- WordPress.org plugin repository
- Your website's plugin installer
- Distribution to clients

## File Size

Typical build size: **~80-100 KB** (compressed)

## Requirements

- Bash shell (macOS, Linux, WSL on Windows)
- `rsync` (pre-installed on macOS/Linux)
- `zip` (pre-installed on macOS/Linux)

## Notes

- The build directory is gitignored and won't be committed
- Each build creates a new zip with the current version number
- Previous builds are automatically replaced
