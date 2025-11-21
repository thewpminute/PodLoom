=== PodLoom - Podcast Player for Transistor.fm & RSS Feeds ===
Contributors: wpminute, mattmm
Tags: podcast, podcasting 2.0, chapters, transcripts, audio
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.5.1
License: GNU General Public License v2.0 or later

# PodLoom - Podcast Player for Transistor.fm & RSS Feeds

A lightweight, high-performance WordPress plugin for embedding podcast episodes with full **Podcasting 2.0** support. Connect to Transistor.fm or any RSS feed and display rich podcast content including chapters, transcripts, credits, and funding buttons.

## Why PodLoom?

### Lightning Fast Performance
- **~25 KB gzipped** total payload (6-15x lighter than alternatives)
- Zero external dependencies (no React, Vue, or jQuery required)
- Sub-second load times on mobile
- Better Core Web Vitals and SEO scores

### Full Podcasting 2.0 Support
PodLoom automatically detects and displays all Podcasting 2.0 namespace tags:
- **Chapters** with timestamps, images, and links
- **Transcripts** (SRT, VTT, HTML, TXT formats)
- **Person tags** for hosts, guests, and contributors
- **Funding buttons** for listener support
- Tabbed interface that adapts to your content

### Complete Customization
- Typography controls (fonts, sizes, colors, weights)
- Display options (show/hide any element)
- Brand-matching color schemes
- Minimal styling mode for custom CSS

## Features

### Podcasting 2.0 Support (NEW!)

PodLoom brings the future of podcasting to your WordPress site:

**Chapters**
- Interactive chapter list with timestamps
- Click to jump to any chapter
- Chapter artwork display
- External links for references

**Transcripts**
- Multiple format support (SRT, VTT, HTML, TXT, JSON)
- On-demand loading (saves bandwidth)
- Clean, readable typography
- Toggle visibility

**Person Tags (Credits)**
- Host and guest profiles
- Profile photos with fallback avatars
- Role labels (Host, Guest, Producer, etc.)
- Links to websites and social profiles

**Funding**
- Support/donate buttons
- Custom button text
- Direct links to Patreon, Buy Me a Coffee, etc.
- Non-intrusive placement

**Tabbed Interface**
- Automatic tab generation based on available content
- Description, Credits, Chapters, Transcripts tabs
- Clean, accessible design
- Responsive on all devices

### Transistor.fm Integration
- **Easy API Integration**: Connect your Transistor.fm account with a simple API key
- **Gutenberg Block**: Intuitive block editor interface for selecting and embedding episodes
- **Multiple Shows Support**: Work with multiple podcasts from your Transistor account
- **Episode Search**: Searchable dropdown to quickly filter and find episodes (including draft and scheduled episodes)
- **Pagination**: Load more episodes with built-in pagination
- **Theme Options**: Choose between light and dark player themes
- **Three Display Modes**:
  - **Latest Episode Mode**: Always display the most recent episode from a show (auto-updates)
  - **Specific Episode Mode**: Select and lock a specific episode to display
  - **Playlist Mode**: Display a browsable playlist of episodes from a show

### RSS Feed Support
- **Universal RSS Support**: Add podcasts from any RSS feed (Transistor, Buzzsprout, Captivate, Podbean, etc.)
- **Podcasting 2.0 Auto-Detection**: Automatically parses and displays P2.0 namespace tags
- **Multiple Feed Management**: Add and manage multiple RSS feeds
- **Feed Validation**: Automatic validation with status indicators for each feed
- **Episode Browser**: Browse and select episodes from RSS feeds with pagination
- **Customizable Player**: Control which elements display (artwork, title, date, duration, description)
- **Typography Controls**: Customize fonts, sizes, colors, and weights for all text elements
- **Background Color**: Set custom background colors for RSS episode blocks
- **Minimal Styling Mode**: Option to use only semantic HTML for complete custom CSS control
- **Description Limits**: Set character limits for episode descriptions
- **Live Preview**: Real-time preview of typography changes in the admin panel
- **Player Height Control**: Set maximum height with scrollable content areas

### Performance & Caching
- **Lightweight**: ~83 KB uncompressed, ~25 KB gzipped (total payload)
- **Smart Caching**: Multi-layer caching system for optimal performance
  - Episode data cached (configurable: 30 min to 24 hours)
  - Rendered HTML cached for instant editor preview
  - Typography settings cached until changed
- **Atomic Cache Updates**: Race-condition-free cache invalidation
- **Smart Invalidation**: Changing settings clears only what needs updating
- **On-Demand Loading**: Transcripts loaded only when requested

### Security
- **Input Sanitization**: All user inputs properly sanitized
- **Output Escaping**: All outputs escaped with esc_html(), esc_url(), esc_attr()
- **Nonce Verification**: AJAX requests protected with nonces
- **Capability Checks**: Admin functions restricted to authorized users
- **Rate Limiting**: Built-in rate limiting for RSS feed operations
- **Transcript Size Limits**: Protection against oversized files (configurable)
- **URL Validation**: Prevents javascript: and data: URL injection

### General Features
- **Welcome Tab**: Helpful onboarding with video tutorial and feature overview
- **Default Show Setting**: Set a default show for quick episode embedding
- **Cache Control**: Configurable cache duration and manual cache clearing
- **Danger Zone**: Complete plugin reset option to delete all stored settings and cache data

## Installation

1. **Upload the Plugin**
   - Copy the `podloom-podcast-player` folder to `/wp-content/plugins/`
   - Or zip the folder and upload via WordPress admin (Plugins → Add New → Upload Plugin)

2. **Activate the Plugin**
   - Go to WordPress admin → Plugins
   - Find "PodLoom - Podcast Player" and click "Activate"

3. **Configure Settings**
   - Go to WordPress admin → Settings → PodLoom Settings
   - For Transistor.fm: Enter your API key
   - For RSS feeds: Enable RSS feeds and add your feed URLs

## Getting Your Transistor API Key

1. Log in to your [Transistor Dashboard](https://dashboard.transistor.fm/)
2. Go to your Account settings
3. Find the API section
4. Copy your API key
5. Paste it into the WordPress plugin settings

## Usage

### Adding an Episode to a Post or Page

1. **Add the PodLoom Episode Player Block**
   - Click the "+" button to add a new block
   - Search for "Podcast Episode" or "PodLoom"
   - Click to add the block

2. **Choose Your Source**
   - **Transistor**: Select a show from your connected account
   - **RSS Feed**: Select from your configured RSS feeds

3. **Select Display Mode**
   - **Latest Episode**: Automatically displays the most recent episode
   - **Specific Episode**: Select and lock a specific episode
   - **Playlist** (Transistor only): Displays a browsable episode playlist

4. **Customize Appearance** (RSS feeds)
   - Configure typography in Settings → PodLoom → Typography
   - Toggle display elements (artwork, title, date, duration, description)
   - Set background colors and player height

5. **Publish**
   - The player will display with all available Podcasting 2.0 features

### Podcasting 2.0 Features

If your RSS feed includes Podcasting 2.0 tags, PodLoom automatically displays:

- **Chapters tab**: When `<podcast:chapters>` is present
- **Transcripts tab**: When `<podcast:transcript>` is present
- **Credits tab**: When `<podcast:person>` tags are present
- **Funding button**: When `<podcast:funding>` is present

No configuration required—if it's in your feed, PodLoom shows it.

### Hosts Supporting Podcasting 2.0

| Host | Chapters | Transcripts | Person | Funding |
|------|----------|-------------|--------|---------|
| Transistor | ✅ | ✅ | ✅ | ✅ |
| Buzzsprout | ✅ | ✅ | ✅ | ✅ |
| Captivate | ✅ | ✅ | ✅ | ✅ |
| RSS.com | ✅ | ✅ | ✅ | ✅ |
| Castos | ✅ | ✅ | ✅ | ✅ |
| Podbean | ✅ | ✅ | ⚠️ | ✅ |

## Admin Settings

### Welcome Tab
- Plugin overview and feature highlights
- Getting started guide
- Video tutorial

### Transistor API Tab
- API key configuration
- Connection status
- Shows list

### RSS Feeds Tab
- Enable/disable RSS feeds
- Feed management (add, edit, refresh, delete)
- Feed status indicators
- Player display settings
- Podcasting 2.0 display options:
  - Show/hide funding buttons
  - Show/hide transcripts tab
  - Show/hide hosts and guests
  - Show/hide chapters
- Typography settings with live preview
- Player height control

### General Settings Tab
- Default show selector
- Cache duration (applies to all caching)
- Enable/disable caching
- Clear cache button
- Danger Zone (plugin reset)

## Performance Comparison

| Player | Payload (gzipped) | External Dependencies |
|--------|-------------------|----------------------|
| **PodLoom** | **~25 KB** | **None** |
| Podbean | 200-300 KB | React, Analytics |
| PowerPress | 150+ KB | jQuery |
| Smart Podcast Player | 400+ KB | Vue.js |

## Technical Specifications

### Requirements
- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher
- **Block Editor**: Gutenberg (default in WordPress 5.0+)

### Asset Sizes
- `rss-player.css`: 1.2 KB
- `podcast20-styles.css`: 9.8 KB
- `podcast20-chapters.js`: 26 KB
- `episode-block/index.js`: 46 KB
- **Total**: ~83 KB uncompressed, ~25 KB gzipped

### Supported Transcript Formats
- SRT (SubRip)
- VTT (Web Video Text Tracks)
- HTML
- Plain text (.txt)
- JSON (Podcasting 2.0 format)

### Files Structure

```
podloom-podcast-player/
├── podloom-podcast-player.php       # Main plugin file
├── uninstall.php                    # Uninstall cleanup script
├── admin/
│   ├── admin-functions.php          # Admin page rendering
│   ├── css/admin-styles.css         # Admin styling
│   ├── js/
│   │   ├── settings-page.js         # General settings
│   │   ├── rss-manager.js           # RSS feed management
│   │   └── typography-manager.js    # Typography live preview
│   └── tabs/                        # Settings tab templates
├── assets/
│   ├── css/
│   │   ├── rss-player.css           # RSS player styles
│   │   └── podcast20-styles.css     # P2.0 feature styles
│   └── js/
│       └── podcast20-chapters.js    # P2.0 interactivity
├── blocks/
│   └── episode-block/
│       ├── block.json               # Block configuration
│       └── index.js                 # Gutenberg block
├── includes/
│   ├── api.php                      # Transistor API wrapper
│   ├── cache.php                    # Caching utilities
│   ├── class-podloom-rss.php        # RSS feed handling
│   ├── class-podloom-podcast20-parser.php  # P2.0 parsing
│   ├── rss-ajax-handlers.php        # AJAX endpoints
│   └── utilities.php                # Helper functions
└── languages/                       # Translation files
```

## Troubleshooting

### Podcasting 2.0 features not showing
- Verify your podcast host supports Podcasting 2.0 tags
- Check that the features are enabled in your host's dashboard
- Refresh the RSS feed in PodLoom settings
- Clear the cache

### Transcripts not loading
- Ensure the transcript URL is publicly accessible
- Check browser console for CORS errors
- Verify transcript format is supported (SRT, VTT, HTML, TXT)

### Episodes not updating
- Clear the cache in Settings → PodLoom → General
- Check the cache duration setting
- Refresh the RSS feed manually

### Player styling issues
- Try enabling "Minimal Styling Mode" and adding custom CSS
- Check for theme CSS conflicts
- Verify typography settings are saved

## Support

For issues or feature requests:
- Visit: https://thewpminute.com/podloom/
- GitHub: Report issues and contribute
- Check the Transistor API documentation: https://developers.transistor.fm/

## License

GPL v2 or later

## Changelog

### Version 2.5.1
*Major Update - Full Podcasting 2.0 Support*

**New Features:**
- **Podcasting 2.0 Support**: Full support for podcast namespace tags
  - Chapters with timestamps, images, and links
  - Transcripts (SRT, VTT, HTML, TXT, JSON formats)
  - Person tags for hosts, guests, and contributors
  - Funding/support buttons
- **Tabbed Interface**: Automatic tabs for Description, Credits, Chapters, Transcripts
- **Smart Caching**: Multi-layer caching with atomic updates
  - Rendered HTML caching for fast editor preview
  - Cache version system prevents race conditions
  - Smart invalidation on settings changes
- **Security Hardening**:
  - JSON input sanitization
  - Transcript size limits (2MB default, configurable)
  - Enhanced output escaping
- **Editor/Frontend Parity**: Block editor now matches frontend display exactly
- **Player Height Control**: Configurable max height with scrollable content

**Improvements:**
- 6-15x smaller payload than competing plugins
- Consolidated cache settings (single duration for all caching)
- Automatic P2.0 feature detection from RSS feeds
- On-demand transcript loading (saves bandwidth)
- Better mobile performance
- Improved Core Web Vitals scores

**Bug Fixes:**
- Fixed editor not showing Podcasting 2.0 content
- Fixed race condition in cache version updates
- Fixed inline styles overriding CSS in editor
- Removed duplicate cache duration settings

### Version 2.1.1
- Bug fixes for transcript loading
- Improved error logging
- Security policy updates

### Version 2.0.0
*Major Update - RSS Feed Support & Enhanced Features*

**New Features:**
- RSS Feed Support with multiple feed management
- Typography Controls for RSS players
- Background Colors for episode blocks
- Minimal Styling Mode
- Description Limits
- Live Preview for typography
- Welcome Tab with video tutorial

**Improvements:**
- Enhanced security
- Rate limiting
- Improved caching
- Better error handling

### Version 1.1.0
- Added caching functionality
- Added Danger Zone reset option
- Cache duration controls

### Version 1.0.0
- Initial release
- Transistor.fm API integration
- Gutenberg block
- Three display modes

## Credits

**PodLoom** is developed by [WP Minute](https://thewpminute.com/)

Built with ❤️ for podcasters who care about performance.

Supports the [Podcasting 2.0](https://podcastindex.org/) open standard.

Transistor.fm is a trademark of Transistor, Inc. This plugin is not officially affiliated with or endorsed by Transistor, Inc.
