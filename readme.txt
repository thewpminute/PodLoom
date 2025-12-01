=== PodLoom - Podcast Player for Transistor.fm & RSS Feeds ===
Contributors: wpminute, mattmm
Tags: podcast, podcasting 2.0, chapters, transcripts, audio
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.14.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, high-performance WordPress plugin for embedding podcast episodes with full Podcasting 2.0 support.

== Description ==

PodLoom is a lightweight, high-performance WordPress plugin for embedding podcast episodes with full **Podcasting 2.0** support. Connect to Transistor.fm or any RSS feed and display rich podcast content including chapters, transcripts, credits, and funding buttons. Works seamlessly with both the **Gutenberg block editor** and **Elementor**.

= Why PodLoom? =

**Lightning Fast Performance**

* **~25 KB gzipped** total payload (6-15x lighter than alternatives)
* Zero external dependencies (no React, Vue, or jQuery required)
* Sub-second load times on mobile
* Better Core Web Vitals and SEO scores

**Accessible by Design**

* **WCAG 2.1 AA compliant** player controls and navigation
* Full keyboard navigation for all interactive elements
* Screen reader support with live announcements
* Visible focus indicators throughout

**Full Podcasting 2.0 Support**

PodLoom automatically detects and displays all Podcasting 2.0 namespace tags:

* **Chapters** with timestamps, images, and links
* **Transcripts** (SRT, VTT, HTML, TXT formats)
* **Person tags** for hosts, guests, and contributors
* **Funding buttons** for listener support
* Tabbed interface that adapts to your content

**Complete Customization**

* Typography controls (fonts, sizes, colors, weights)
* Display options (show/hide any element)
* Brand-matching color schemes
* Minimal styling mode for custom CSS

= Features =

**Podcasting 2.0 Support**

* Interactive chapter list with timestamps and click-to-seek
* Multiple transcript format support (SRT, VTT, HTML, TXT, JSON)
* Host and guest profiles with photos and role labels
* Support/donate buttons with custom text
* Automatic tab generation based on available content

**Transistor.fm Integration**

* Easy API integration with your Transistor.fm account
* Intuitive Gutenberg block for embedding episodes
* Multiple shows support
* Episode search with pagination
* Light and dark player themes
* Three display modes: Latest Episode, Specific Episode, Playlist

**RSS Feed Support**

* Works with any standard podcast RSS feed
* Automatic Podcasting 2.0 tag detection
* Multiple feed management with validation
* Customizable player with typography controls
* Background color and minimal styling options
* **Playlist mode** with browsable episode list and auto-play next

**Subscribe Buttons**

* Display subscribe/follow buttons for all major podcast platforms
* Supports 15+ platforms: Spotify, Apple Podcasts, Amazon Music, Pocket Casts, Overcast, and more
* **Auto-sync from Transistor.fm** - Subscribe links imported automatically when you add your API key
* **Three color modes**: Brand colors, monochrome, or custom color
* **Flexible layouts**: Horizontal, vertical, or grid arrangements
* **Full customization**: Icon size slider (16-64px), spacing control, optional labels with font settings
* Available as Gutenberg block and Elementor widget
* Manual link entry for RSS feeds and non-Transistor podcasts

**Elementor Integration**

* Native Elementor widget with drag-and-drop support
* Searchable episode selector with type-to-filter
* Live preview in the Elementor editor
* All display modes: Latest Episode, Specific Episode, Playlist
* Full Podcasting 2.0 rendering for RSS sources
* Respects global PodLoom styling settings

**Performance & Caching**

* Smart multi-layer caching system
* Atomic cache updates (race-condition-free)
* On-demand transcript loading
* Configurable cache duration

== Installation ==

1. Upload the `podloom-podcast-player` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → PodLoom Settings to configure

= For Transistor.fm Users =

1. Log in to your [Transistor Dashboard](https://dashboard.transistor.fm/)
2. Go to Account settings → API section
3. Copy your API key
4. Paste it into the plugin settings

= For RSS Feed Users =

1. Go to Settings → PodLoom Settings → RSS Feeds tab
2. Enable RSS feeds
3. Add your podcast RSS feed URL
4. Select episodes from the block editor

= For Elementor Users =

1. Ensure Elementor 3.0.0 or higher is installed and activated
2. Configure your podcast sources in Settings → PodLoom (API key or RSS feeds)
3. Open any page with Elementor
4. Search for "PodLoom" in the widget panel
5. Drag the PodLoom Episode widget onto your page
6. Select your podcast source and episode

== Frequently Asked Questions ==

= What is Podcasting 2.0? =

Podcasting 2.0 is an open standard that extends RSS feeds with new features like chapters, transcripts, person credits, and funding links. If your podcast host supports these features, PodLoom will automatically display them.

= Why aren't Podcasting 2.0 features showing? =

1. Verify your podcast host supports Podcasting 2.0 tags
2. Check that the features are enabled in your host's dashboard
3. Refresh the RSS feed in PodLoom settings
4. Clear the cache

= Why aren't transcripts loading? =

1. Ensure the transcript URL is publicly accessible
2. Check browser console for CORS errors
3. Verify transcript format is supported (SRT, VTT, HTML, TXT)

= How do I update episodes? =

1. Clear the cache in Settings → PodLoom → General
2. Check the cache duration setting
3. Refresh the RSS feed manually

= Can I customize the player styling? =

Yes! You can use the built-in typography controls, or enable "Minimal Styling Mode" and add your own custom CSS.

== Screenshots ==

1. Episode player with Podcasting 2.0 tabs
2. Chapter list with timestamps and images
3. Transcript viewer
4. Admin settings - RSS Feeds tab
5. Admin settings - Typography controls
6. Block editor episode selection
7. Elementor widget with searchable episode dropdown
8. RSS Playlist mode with browsable episode list

== Changelog ==

= 2.14.0 =
* **Mobile Tab Navigation**: Horizontal scrolling tabs on mobile instead of vertical stacking
* **Touch-Friendly Design**: 44px minimum touch targets meeting Apple accessibility guidelines
* **Active Tab Indicator**: Underline indicator using `currentColor` for theme compatibility
* **Smooth Scrolling**: Momentum scrolling on iOS with hidden scrollbar for native feel
* **Documentation**: Updated styling guide with mobile tab navigation details

= 2.13.0 =
* **Accessibility Improvements**: Comprehensive WCAG 2.1 AA compliance enhancements across admin and frontend
* **Keyboard Navigation**: Full keyboard support for chapters, playlist episodes, transcript timestamps, and tabs
* **Screen Reader Support**: Live announcements for episode changes, chapter navigation, and playback state
* **ARIA Enhancements**: Proper roles, states, and labels for all interactive elements
* **Focus Indicators**: Visible focus styles for all buttons, tabs, chapters, and playlist items
* **Admin Accessibility**: Improved form labels, accordion accessibility, table headers, and AJAX feedback
* **Dynamic State Updates**: Play/pause button labels update based on playback state
* **Tab Panel Improvements**: Arrow key navigation, proper aria-hidden on inactive panels
* **Transcript Loading States**: aria-busy and aria-expanded states during transcript loading
* **Iframe Accessibility**: Descriptive titles added to Transistor.fm embedded players

= 2.11.1 =
* **Subscribe Buttons Block**: New Gutenberg block to display podcast subscribe/follow buttons
* **Subscribe Buttons Widget**: Elementor widget for subscribe buttons with full customization
* **15+ Platform Support**: Spotify, Apple Podcasts, Amazon Music, Pocket Casts, Overcast, Castbox, Castro, Deezer, iHeartRadio, Pandora, Player FM, Podcast Addict, TuneIn, YouTube Music, YouTube, and RSS
* **Auto-Sync from Transistor**: Subscribe links automatically imported when adding your API key
* **Icon Customization**: Adjustable icon size (16-64px) with smooth slider control
* **Color Modes**: Brand colors (platform colors), monochrome, or custom hex color
* **Layout Options**: Horizontal, vertical, or grid arrangements
* **Optional Labels**: Show platform names with customizable font size and family
* **Icon Spacing**: Control gap between icons (4-48px)
* **Background Sync**: Subscribe link sync runs in background for faster settings saves
* **Bug Fixes**: Fixed RSS feed validation status not updating after adding feeds
* **Security**: Fixed SSRF vulnerability in feed validation, added input validation to color utilities
* **Code Quality**: Fixed undefined constant, improved error handling and response consistency

= 2.9.0 =
* **RSS Playlist Mode**: Display multiple episodes in a browsable playlist with an Episodes tab
* **Episode Order Control**: Choose episodic (newest first) or serial (oldest first) ordering for playlists
* **Click-to-Play Episodes**: Select any episode from the list to immediately start playback
* **Auto-Play Next**: Automatically plays the next episode when the current one finishes
* **Now Playing Indicator**: Animated visual indicator shows which episode is currently playing
* **Dynamic P2.0 Updates**: Chapters, Transcripts, and Credits tabs update when switching episodes
* **Configurable Episode Count**: Set maximum episodes (5-100) displayed in the playlist
* **Elementor Playlist Support**: RSS Playlist mode now available in Elementor widget
* **Asset Minification**: JavaScript and CSS now minified for faster page loads (~66% smaller)
* **Security Enhancement**: Added sanitization for playlist episode data

= 2.8.0 =
* **Elementor Integration**: New native Elementor widget for embedding podcast episodes
* **Searchable Episode Selector**: Type-to-filter dropdown makes finding episodes fast and easy
* **Live Editor Preview**: See your player render in real-time within Elementor
* **Full Feature Parity**: All display modes (Latest, Specific, Playlist) available in Elementor
* **Podcasting 2.0 in Elementor**: RSS sources display chapters, transcripts, and person tags
* **Unified Source Selector**: Choose between Transistor shows and RSS feeds from one dropdown

= 2.7.1 =
* **Player Border Styling**: Customize border color, width, style, and radius
* **Funding Button Customization**: Style font, colors, and border radius for funding buttons
* **Image Caching**: Cache podcast cover art locally in the media library for faster loading
* **Conditional HTTP Requests**: Efficient RSS updates using ETag/Last-Modified headers
* **Improved UI**: Minimal Styling Mode moved to Advanced Typography section with CSS class reference
* **RSS Feeds in Default Show**: Select RSS feeds as your default show in General Settings

= 2.5.3 =
* Security hardening and WordPress coding standards improvements
* Performance optimizations for cache clearing
* Fixed deprecated function usage

= 2.5.1 =
* **Full Podcasting 2.0 Support**: Chapters, transcripts, person tags, and funding buttons
* **Tabbed Interface**: Automatic tabs for Description, Credits, Chapters, Transcripts
* **Smart Caching**: Multi-layer caching with atomic updates
* **Security Hardening**: JSON input sanitization, transcript size limits
* **Editor/Frontend Parity**: Block editor now matches frontend display exactly
* **Player Height Control**: Configurable max height with scrollable content

= 2.1.1 =
* Bug fixes for transcript loading
* Improved error logging
* Security policy updates

= 2.0.0 =
* RSS Feed Support with multiple feed management
* Typography Controls for RSS players
* Background Colors for episode blocks
* Minimal Styling Mode
* Description Limits
* Live Preview for typography
* Welcome Tab with video tutorial

= 1.1.0 =
* Added caching functionality
* Added Danger Zone reset option
* Cache duration controls

= 1.0.0 =
* Initial release
* Transistor.fm API integration
* Gutenberg block with three display modes

== Upgrade Notice ==

= 2.14.0 =
Mobile tab navigation now uses horizontal scrolling for better usability. Includes touch-friendly 44px targets and theme-aware active indicators.

= 2.13.0 =
Major accessibility update! Full keyboard navigation, screen reader support, and WCAG 2.1 AA compliance improvements. Recommended for all users.

= 2.11.1 =
New Subscribe Buttons feature! Display platform icons (Spotify, Apple Podcasts, etc.) with auto-sync from Transistor.fm. Includes security fixes and bug fixes for RSS feed validation.

= 2.9.0 =
New RSS Playlist mode displays multiple episodes in a browsable list with auto-play next, now-playing indicator, and dynamic Podcasting 2.0 tab updates.

= 2.8.0 =
New Elementor widget for drag-and-drop podcast embedding. Includes searchable episode selector and full Podcasting 2.0 support.

= 2.7.1 =
New styling options for player borders and funding buttons, plus image caching and efficient RSS updates.

= 2.5.3 =
Security and performance improvements. Recommended update for all users.

= 2.5.1 =
Major update with full Podcasting 2.0 support. If your podcast host supports chapters, transcripts, or person tags, they will now display automatically.

== Privacy Policy ==

PodLoom does not collect any personal data from your website visitors. The plugin:

* Connects to Transistor.fm API only when configured with an API key
* Fetches RSS feeds from URLs you configure
* Proxies transcript requests to bypass CORS restrictions (no data stored)
* Caches podcast data locally in your WordPress database

No analytics, tracking, or external connections are made beyond the podcast sources you configure.

== Disclaimer ==

PodLoom is not affiliated with, endorsed by, or sponsored by WordPress, the WordPress Foundation, WordPress.com, Automattic, Elementor, or Transistor.fm. All trademarks and registered trademarks are the property of their respective owners.
