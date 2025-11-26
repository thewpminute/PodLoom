# PodLoom Styling Guide

Complete guide to customizing the appearance of your podcast players, including RSS episodes and Podcasting 2.0 elements.

---

## Table of Contents

- [Quick Start: Using Color Palettes](#quick-start-using-color-palettes)
- [Understanding PodLoom's Color System](#understanding-podlooms-color-system)
- [Styling Options](#styling-options)
- [How Dynamic Coloring Works](#how-dynamic-coloring-works)
- [Minimal Styling Mode (Advanced)](#minimal-styling-mode-advanced)
- [CSS Class Reference](#css-class-reference)
- [Examples & Tips](#examples--tips)

---

## Quick Start: Using Color Palettes

The fastest way to style your podcast players is using the built-in color palettes:

1. Go to **Settings → PodLoom Settings → RSS tab**
2. Scroll to **Quick Color Palettes**
3. Select a pre-designed palette:
   - **Classic Dark** - Dark mode appearance (#1a1a1a background)
   - **Light Minimal** - Clean white background (#ffffff)
   - **Midnight Blue** - Deep blue tones (#0f172a)
   - **Forest Green** - Natural green palette (#064e3b)
   - **Warm Amber** - Warm brown/amber tones (#78350f)
   - **Sunset Vibes** - Orange/red warmth (#4c1d1d)
   - **Deep Ocean** - Blue ocean tones (#0c4a6e)
   - **Berry Smoothie** - Purple/magenta accents (#4a044e)
   - **Slate Gray** - Neutral gray palette (#334155)

4. The palette instantly updates:
   - Background color
   - Typography colors (title, date, duration, description)
   - Tab and button colors (automatically coordinated!)

**That's it!** PodLoom automatically creates a coordinated color scheme for all Podcasting 2.0 elements.

---

## Understanding PodLoom's Color System

PodLoom uses an **intelligent color system** that does the hard work for you.

### What You Control Directly

In the RSS Settings tab, you have direct control over:

✅ **Background Color** - The main container background
✅ **Title Color** - Episode title text
✅ **Date Color** - Publication date text
✅ **Duration Color** - Episode duration text
✅ **Description Color** - Episode description text
✅ **Typography Settings** - Font family, size, weight, line height

### What Gets Generated Automatically

When you set a background color, PodLoom **automatically generates** coordinating colors for:

🎨 **Tab Navigation**
- Inactive tab text color
- Hover state colors
- Active tab color and border
- Tab hover background

🎨 **Podcasting 2.0 Elements**
- Chapter timestamp buttons
- Transcript timestamp buttons
- People/Credits box backgrounds
- Chapter list backgrounds and borders

### The Magic: Smart Color Calculation

PodLoom analyzes your background color's brightness (luminance) and automatically decides:

- **Light backgrounds** → Generate darker colors for text and accents
- **Dark backgrounds** → Generate lighter colors for text and accents

This ensures your tabs and buttons are **always readable** regardless of your color choices.

---

## Styling Options

### RSS Player Display Settings

**Location:** Settings → PodLoom Settings → RSS tab

#### Element Visibility

Toggle which elements appear in your player:
- Episode Artwork
- Episode Title
- Publication Date
- Episode Duration
- Episode Description
- Skip Buttons (rewind 10s / forward 30s)

#### Podcasting 2.0 Element Visibility

Control which P2.0 features are displayed:
- Funding Links
- Transcripts
- Podcast Hosts (from channel)
- Episode Guests (from episode)
- Chapters

#### Description Settings

- **Character Limit**: Set to `0` for unlimited, or specify a character count to truncate long descriptions
- **Player Height**: Maximum height in pixels (default: 600px). Content exceeding this height becomes scrollable.

---

## How Dynamic Coloring Works

### Example: You Choose a Light Background

**You set:** Background color to `#f9f9f9` (light gray)

**PodLoom automatically calculates:**

| Element | Generated Color | How It's Calculated |
|---------|----------------|---------------------|
| Tab Text | Dark gray (`#666666`) | Darkened 40% from background |
| Tab Hover | Darker gray | Darkened 60% from background |
| Active Tab | Very dark (`#1d1d1d`) | Darkened 70% from background |
| Tab Border | Medium gray | Darkened 15% from background |
| Hover Background | Slightly darker | Darkened 5% from background |
| Chapter Timestamps | Dark accent | Darkened 60% from background |
| Chapter Backgrounds | Subtle tint | Darkened 3% from background |

### Example: You Choose a Dark Background

**You set:** Background color to `#2a2a2a` (dark gray)

**PodLoom automatically calculates:**

| Element | Generated Color | How It's Calculated |
|---------|----------------|---------------------|
| Tab Text | Light gray (`#949494`) | Lightened 50% from background |
| Tab Hover | Lighter gray | Lightened 70% from background |
| Active Tab | Very light (`#d4d4d4`) | Lightened 80% from background |
| Tab Border | Medium gray | Lightened 25% from background |
| Hover Background | Slightly lighter | Lightened 15% from background |
| Chapter Timestamps | Light accent | Lightened 60% from background |
| Chapter Backgrounds | Subtle tint | Lightened 10% from background |

### Why This Matters

You only need to choose **one background color**, and PodLoom creates an entire coordinated color scheme. This ensures:
- Proper contrast for accessibility
- Visual harmony across all elements
- Consistent look on both light and dark backgrounds

---

## Minimal Styling Mode (Advanced)

For developers and advanced users who want complete CSS control.

### What Is Minimal Styling Mode?

When enabled, PodLoom outputs **only semantic HTML** with CSS classes, without any plugin-generated styles. This lets you write your own CSS from scratch.

### Enabling Minimal Styling Mode

1. Go to **Settings → PodLoom Settings → RSS tab**
2. Check **"Enable Minimal Styling Mode"**
3. Save settings

**What happens:**
- ✅ All typography settings are disabled
- ✅ Color palette selector is hidden
- ✅ PodLoom only outputs HTML structure with classes
- ✅ Player max-height setting still applies (for scrollable content)
- ❌ No color or typography styles are applied

### Writing Your Own CSS

Add your custom CSS to your theme's stylesheet or a custom CSS plugin.

#### Available CSS Classes

See the [CSS Class Reference](#css-class-reference) section below for all available classes.

#### Basic Example

```css
/* Episode Container */
.wp-block-podloom-episode-player.rss-episode-player {
    background: #ffffff;
    border: 2px solid #333;
    padding: 30px;
}

/* Episode Title */
.rss-episode-title {
    font-size: 28px;
    color: #222;
    font-weight: bold;
}

/* Tab Navigation */
.podcast20-tab-button {
    color: #666;
    padding: 10px 20px;
}

.podcast20-tab-button.active {
    color: #000;
    border-bottom: 3px solid #0066cc;
}

/* Chapter Timestamps */
.chapter-timestamp {
    background: #0066cc;
    color: white;
    padding: 5px 12px;
    border-radius: 4px;
    cursor: pointer;
}
```

---

## CSS Class Reference

### Main Container

| Class | Description |
|-------|-------------|
| `.wp-block-podloom-episode-player` | Main player wrapper |
| `.rss-episode-player` | RSS episode specific wrapper |

### Episode Information

| Class | Description |
|-------|-------------|
| `.rss-episode-wrapper` | Episode content wrapper (artwork + content) |
| `.rss-episode-artwork` | Artwork container |
| `.rss-episode-content` | Main content area (title, meta, audio) |
| `.rss-episode-header` | Header section (title + funding button) |
| `.rss-episode-title` | Episode title |
| `.rss-episode-meta` | Meta information container (date, duration) |
| `.rss-episode-date` | Publication date |
| `.rss-episode-duration` | Episode duration |
| `.rss-episode-audio` | Audio player element |
| `.rss-episode-description` | Description text |

### Skip Buttons

| Class | Description |
|-------|-------------|
| `.podloom-skip-buttons` | Container for skip buttons |
| `.podloom-skip-btn` | Individual skip button |
| `.podloom-skip-btn[data-skip="-10"]` | Rewind 10 seconds button |
| `.podloom-skip-btn[data-skip="30"]` | Forward 30 seconds button |

### Podcasting 2.0 Tabs

| Class | Description |
|-------|-------------|
| `.podcast20-tabs` | Main tabs container |
| `.podcast20-tab-nav` | Tab navigation bar |
| `.podcast20-tab-button` | Individual tab button |
| `.podcast20-tab-button.active` | Active/selected tab |
| `.podcast20-tab-panel` | Tab content panel |
| `.podcast20-tab-panel.active` | Active/visible panel |

### Funding

| Class | Description |
|-------|-------------|
| `.podcast20-funding-button` | Support/funding button |

### Transcripts

| Class | Description |
|-------|-------------|
| `.podcast20-transcripts` | Transcript section container |
| `.transcript-formats` | Format selector buttons container |
| `.transcript-format-button` | Individual format button (TXT, HTML, etc.) |
| `.transcript-format-button.active` | Selected format |
| `.transcript-viewer` | Transcript content viewer |
| `.transcript-content` | Transcript text content |
| `.transcript-timestamp` | Clickable timestamp in transcript |
| `.transcript-close` | Close transcript button |

### People / Credits

| Class | Description |
|-------|-------------|
| `.podcast20-people` | People/credits section container |
| `.podcast20-people-list` | List of people |
| `.podcast20-person` | Individual person item |
| `.podcast20-person-img` | Person's image (if available) |
| `.podcast20-person-avatar` | Fallback avatar (initials) |
| `.podcast20-person-info` | Person's name and role container |
| `.podcast20-person-role` | Person's role (Host, Guest, etc.) |
| `.podcast20-person-name` | Person's name |

### Chapters

| Class | Description |
|-------|-------------|
| `.podcast20-chapters` | Chapters section container |
| `.podcast20-chapters-list` | List of chapters |
| `.chapters-heading` | Chapters section heading |
| `.chapter-item` | Individual chapter item |
| `.chapter-item.active` | Currently playing chapter |
| `.chapter-img` | Chapter image (if available) |
| `.chapter-img-placeholder` | Fallback chapter image |
| `.chapter-info` | Chapter title and timestamp container |
| `.chapter-timestamp` | Clickable chapter timestamp |
| `.chapter-title` | Chapter title text |

---

## Examples & Tips

### Example 1: Matching Your Theme

**Goal:** Make the player match your WordPress theme's colors

1. Find your theme's primary background color
2. Go to **Settings → PodLoom Settings → RSS tab**
3. Scroll to **Typography Settings → Background Color**
4. Enter your theme's color
5. Set Title, Date, Duration, and Description colors to match your theme's text colors
6. PodLoom automatically coordinates the tabs and P2.0 elements!

### Example 2: Creating a Dark Mode Player

**Option A - Use Built-in Palette:**
1. Select **"Classic Dark"** or **"Midnight Blue"** from Quick Color Palettes

**Option B - Custom Dark Colors:**
1. Set Background Color to a dark color (e.g., `#1a1a1a`)
2. Set Title Color to light (e.g., `#ffffff`)
3. Set Date/Duration/Description to lighter grays (e.g., `#a0a0a0`)
4. PodLoom generates light-colored tabs and buttons automatically

### Example 3: Brand Colors

**Scenario:** Your brand uses purple (`#6a1b9a`)

1. Set Background Color to a light tint: `#f3e5f5`
2. Set Title Color to your brand purple: `#6a1b9a`
3. PodLoom automatically uses purple tones for tabs and accents

### Tip: Testing Your Colors

Use the **Live Preview** in the Typography Settings section to see your changes before saving!

### Tip: Accessibility

When choosing colors:
- Ensure sufficient contrast between text and background
- Test with tools like [WebAIM Contrast Checker](https://webaim.org/resources/contrastchecker/)
- PodLoom's automatic color generation helps maintain contrast, but verify your manual typography colors

### Tip: Mobile Responsive

PodLoom automatically adjusts for mobile devices:
- Tabs stack vertically on narrow screens
- Funding button moves to top
- Touch-friendly button sizes
- All controlled via the plugin's built-in CSS

---

## Troubleshooting

### My Tabs Are Hard to Read

**Solution:** Your background color and generated tab colors may have low contrast.

Try:
1. Use a lighter or darker background color
2. Use one of the built-in palettes as a starting point
3. Check that you're not overriding PodLoom's CSS in your theme

### My Custom CSS Isn't Working

**Check these:**
1. Is Minimal Styling Mode enabled? (Required for full custom CSS control)
2. Are you using the correct CSS classes? (See reference above)
3. Is your theme or another plugin overriding the styles?
4. Use browser DevTools to inspect the element and check CSS specificity

### Colors Look Different on Mobile

This is normal! PodLoom adjusts some layout aspects for mobile, but colors should remain consistent. If they differ significantly:
1. Check for theme-specific mobile CSS overrides
2. Verify your colors have sufficient contrast
3. Test in multiple browsers

### Palettes Don't Apply

After selecting a palette:
1. Scroll down and click **"Save RSS Settings"** button
2. Refresh your front-end page (hard refresh with Ctrl+F5 or Cmd+Shift+R)
3. Clear any caching plugins

---

## Need Help?

- **Plugin Support:** [Submit an issue](https://github.com/your-repo/issues)
- **Documentation:** Check the `/docs` folder in the plugin directory
- **CSS Questions:** Use browser DevTools to inspect elements and identify classes

---

## Summary

**For most users:** Use the Quick Color Palettes or set a Background Color and let PodLoom handle the rest.

**For advanced users:** Enable Minimal Styling Mode and write your own CSS using the class reference.

**Key Takeaway:** PodLoom's smart color system means you only need to choose a few colors, and the plugin creates a professional, coordinated look automatically!
