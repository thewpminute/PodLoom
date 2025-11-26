=== PodLoom - Podcast Player for Transistor.fm & RSS Feeds ===
Contributors: wpminute, mattmm
Tags: podcast, podcasting 2.0, chapters, transcripts, audio
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.5.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, high-performance WordPress plugin for embedding podcast episodes with full Podcasting 2.0 support.

== Description ==

PodLoom is a lightweight, high-performance WordPress plugin for embedding podcast episodes with full **Podcasting 2.0** support. Connect to Transistor.fm or any RSS feed and display rich podcast content including chapters, transcripts, credits, and funding buttons.

= Why PodLoom? =

**Lightning Fast Performance**

* **~25 KB gzipped** total payload (6-15x lighter than alternatives)
* Zero external dependencies (no React, Vue, or jQuery required)
* Sub-second load times on mobile
* Better Core Web Vitals and SEO scores

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

== Changelog ==

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
