# PodLoom - Claude Project Guidelines

## Project Overview

PodLoom is a WordPress plugin for embedding podcast episodes with full Podcasting 2.0 support. It connects to Transistor.fm via API or any RSS feed. The plugin is distributed free on WordPress.org.

**Key Constraints:**
- Must work for broad public use (not single-organization use cases)
- GPL v2 licensed, no premium upsells in core functionality
- Lightweight (~25KB gzipped) - performance is a core differentiator
- WordPress 5.8+ and PHP 7.4+ compatibility required

---

## Critical Features - Require Approval Before Changes

**STOP and ask for approval before modifying any of these:**

### 1. RSS Parser (`includes/class-podloom-rss.php`)
- Core feed parsing logic
- SSRF protection mechanisms
- Feed validation and sanitization

### 2. Podcast Player UI (`assets/css/rss-player.css`, `assets/css/podcast20-styles.css`)
- Player layout and structure
- Responsive behavior
- Core visual design

### 3. Podcasting 2.0 Features (`includes/class-podloom-podcast20-parser.php`, `assets/js/podcast20-player.js`)
- Chapter parsing and display
- Transcript loading and rendering
- Person tags (hosts/guests)
- Funding button integration
- Tabbed interface behavior

### 4. Gutenberg Block (`blocks/episode-block/`)
- Block registration and attributes
- Editor preview rendering
- Block controls and inspector

### 5. Settings Pages (`admin/tabs/`)
- Tab structure and navigation
- Settings registration
- Admin UI layout

### 6. Caching System (`includes/cache.php`)
- Cache invalidation logic
- Multi-layer caching strategy
- Cache key generation

---

## Code Standards

### PHP Standards (WordPress Coding Standards)
```php
// Naming: lowercase with underscores, prefixed with podloom_
function podloom_get_episode_data() {}

// Classes: capitalized words, prefixed
class Podloom_RSS_Parser {}

// Hooks: prefix all hooks
add_action('wp_enqueue_scripts', 'podloom_enqueue_assets');
add_filter('podloom_episode_data', 'podloom_filter_data');

// Direct file access prevention (every PHP file)
if (!defined('ABSPATH')) {
    exit;
}
```

### JavaScript Standards
```javascript
// Use vanilla JS - no jQuery dependency for frontend
// Namespace functions under podloom object when needed
// Use const/let, not var
// Document complex logic with comments
```

### CSS Standards
```css
/* Prefix all classes with podloom- */
.podloom-player {}
.podloom-episode-title {}

/* Use CSS custom properties for theming */
/* Mobile-first responsive design */
/* Avoid !important except when overriding theme conflicts */
```

---

## Security Requirements (WordPress.org Compliance)

### Always Implement
1. **Input Sanitization** - Sanitize ALL user input
   ```php
   sanitize_text_field()
   sanitize_url()
   absint()
   wp_kses_post() // for HTML content
   ```

2. **Output Escaping** - Escape ALL output
   ```php
   esc_html()
   esc_attr()
   esc_url()
   wp_kses() // for allowed HTML
   ```

3. **Nonce Verification** - All AJAX and form submissions
   ```php
   wp_verify_nonce($nonce, 'podloom_action')
   wp_create_nonce('podloom_action')
   ```

4. **Capability Checks** - Admin functions
   ```php
   if (!current_user_can('manage_options')) {
       wp_die('Unauthorized');
   }
   ```

5. **Prepared Statements** - Any database queries
   ```php
   $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id)
   ```

### Never Do
- Store API keys in plain text in database (use options API properly)
- Execute unvalidated external URLs
- Allow arbitrary file uploads
- Use `eval()` or similar dynamic code execution
- Skip nonce verification for "convenience"

### Documented Exception: Transcript Proxy Endpoint
The `podloom_fetch_transcript` AJAX handler intentionally skips nonce verification because:
- It's a read-only proxy for fetching transcript files (no data modification)
- It must work for logged-out visitors viewing podcasts
- Anonymous users have no session to protect via CSRF
- Protected by: rate limiting (15/min), SSRF protection, URL validation, size limits

---

## UI/UX Consistency Guidelines

### Design Principles
1. **Match WordPress Admin UI** - Admin pages should feel native to WordPress
2. **Postbox Pattern** - Use WordPress postbox styling for settings groups
3. **Consistent Spacing** - Use WordPress default spacing (20px margins/padding)
4. **Button Hierarchy** - Primary (blue), Secondary (gray), Destructive (red)
5. **Feedback** - Always show success/error messages for actions

### Color Palette (Admin)
- Primary actions: `#2271b1` (WordPress blue)
- Success: `#00a32a`
- Warning: `#dba617`
- Error/Destructive: `#d63638`
- Borders: `#c3c4c7`
- Background: `#f0f0f1`

### Player UI (Frontend)
- Must work with any theme
- Respect user's typography settings
- Minimal default styling option available
- Accessible (keyboard navigation, screen readers)
- Mobile-responsive without horizontal scroll

---

## Color Palette System (IMPORTANT)

The player uses a **theme-aware color system** that auto-calculates colors based on the user's background color setting. All new UI elements MUST use this system.

### How It Works
- `includes/color-utils.php` contains `podloom_calculate_theme_colors($bg_color)`
- Calculates luminance to determine if background is dark or light
- Returns a full palette of colors with proper contrast

### Available Color Tokens
When adding new UI elements, use these calculated colors:

| Token | Purpose |
|-------|---------|
| `text_primary` | Main text content |
| `text_secondary` | Supporting text, metadata |
| `text_muted` | Subtle text, timestamps |
| `card_bg` | Card/panel backgrounds |
| `card_bg_hover` | Card hover state |
| `card_border` | Card borders |
| `button_bg` | Secondary button background |
| `button_bg_hover` | Button hover state |
| `button_border` | Button borders |
| `button_text` | Button text |
| `accent` | Primary accent (links, highlights) |
| `accent_hover` | Accent hover state |
| `accent_text` | Text on accent backgrounds |
| `tab_*` | Tab navigation colors |
| `content_bg` | Content area backgrounds |
| `warning_*` | Warning/error states |
| `avatar_bg/text` | Placeholder avatars |

### Requirements for New UI Elements
1. **Never hardcode colors** - Always use calculated palette values
2. **Test both modes** - Verify readability on dark AND light backgrounds
3. **Maintain contrast** - WCAG AA minimum (4.5:1 for text, 3:1 for UI)
4. **Add new tokens if needed** - If existing tokens don't fit, add to `podloom_calculate_theme_colors()` for both dark and light branches

### Example: Adding a New Element
```php
$colors = podloom_calculate_theme_colors($background_color);

// Use semantic tokens
$html = '<div style="background: ' . esc_attr($colors['card_bg']) . ';
                     border-color: ' . esc_attr($colors['card_border']) . ';
                     color: ' . esc_attr($colors['text_primary']) . ';">
    <span style="color: ' . esc_attr($colors['text_muted']) . ';">Metadata</span>
</div>';
```

### Testing Color Palettes
Before finalizing any new UI:
- [ ] Test with white background (#ffffff)
- [ ] Test with light gray (#f5f5f5)
- [ ] Test with dark background (#1a1a1a)
- [ ] Test with black background (#000000)
- [ ] Test with brand color backgrounds (various hues)
- [ ] Verify text is readable in all scenarios

---

## File Organization

```
podloom-podcast-player/
├── podloom-podcast-player.php   # Main plugin file, constants, includes
├── uninstall.php                # Clean uninstall
├── admin/
│   ├── admin-functions.php      # Admin page registration, rendering
│   ├── css/admin-styles.css     # Admin-only styles
│   ├── js/                      # Admin JavaScript (per-feature)
│   └── tabs/                    # Settings tab templates (one per tab)
├── assets/
│   ├── css/                     # Frontend styles
│   └── js/                      # Frontend scripts
├── blocks/
│   └── episode-block/           # Gutenberg block files
├── includes/
│   ├── api.php                  # Transistor API wrapper
│   ├── cache.php                # Caching utilities
│   ├── class-podloom-*.php      # Feature-specific classes
│   ├── utilities.php            # Shared helper functions
│   └── *-ajax-handlers.php      # AJAX endpoint handlers
└── languages/                   # i18n translation files
```

### When Creating New Files
- PHP classes: `includes/class-podloom-{feature}.php`
- Admin JS: `admin/js/{feature}-manager.js`
- Frontend JS: `assets/js/{feature}.js`
- CSS: Follow existing pattern (admin vs frontend separation)

---

## WordPress.org Plugin Requirements

### Must Include
- GPL v2+ license header in main file
- Proper `Text Domain` for translations
- `uninstall.php` for clean removal
- Semantic versioning in main file header

### Must Avoid
- Tracking/analytics without opt-in consent
- Sending site/user data to external servers without disclosure
- "Phone home" functionality without user awareness
- Upsells or "premium" feature locks
- Links to external sites without `rel="noopener"`
- Minified code without source (provide unminified versions)

### Internationalization
```php
// All user-facing strings must be translatable
__('Text', 'podloom-podcast-player')
_e('Text', 'podloom-podcast-player')
esc_html__('Text', 'podloom-podcast-player')
```

---

## Testing Checklist Before Major Changes

### Functional Testing
- [ ] Player plays audio correctly
- [ ] Chapters navigate to correct timestamps
- [ ] Transcripts load and display
- [ ] Settings save and persist
- [ ] Cache clears properly
- [ ] Block renders in editor and frontend identically

### Compatibility Testing
- [ ] Works with default themes (Twenty Twenty-Four, etc.)
- [ ] No JavaScript console errors
- [ ] No PHP errors/warnings (WP_DEBUG enabled)
- [ ] Mobile responsive (320px - 1920px)
- [ ] Player UI readable on light backgrounds
- [ ] Player UI readable on dark backgrounds

### Security Review
- [ ] All inputs sanitized
- [ ] All outputs escaped
- [ ] Nonces verified on all AJAX/forms
- [ ] Capability checks on admin functions

---

## Performance Guidelines

### JavaScript
- Vanilla JS only on frontend (no jQuery dependency)
- Defer non-critical scripts
- Load transcripts on-demand, not upfront
- Minimize DOM manipulation

### CSS
- Single frontend stylesheet when possible
- Avoid render-blocking styles
- Use CSS containment where appropriate

### PHP
- Use transient caching for external API calls
- Avoid queries in loops
- Lazy-load heavy features

### Target Metrics
- Total payload: <30KB gzipped
- Time to interactive: <1 second
- No layout shift on player load

---

## Common Tasks Reference

### Adding a New Setting
1. Register option in `admin/admin-functions.php`
2. Add UI in appropriate `admin/tabs/{tab}.php`
3. Add sanitization callback
4. Update relevant functionality to use new option

### Adding AJAX Endpoint
1. Add handler in `includes/rss-ajax-handlers.php`
2. Register action with proper nonce verification
3. Add capability check
4. Return proper JSON response with wp_send_json_*

### Modifying Player Appearance
1. Check if change affects core player UI (requires approval)
2. Maintain minimal styling mode compatibility
3. Test with multiple themes
4. Ensure mobile responsiveness

---

## Documentation Requirements

### Code Comments
- Document "why" not "what" for complex logic
- PHPDoc blocks for functions with parameters/returns
- Inline comments for non-obvious behavior

### User Documentation
- Update readme.txt for feature changes (WordPress.org format)
- Include in changelog section
- Update admin help text if applicable

---

## Version Control

### Commit Messages
- Prefix: `feat:`, `fix:`, `refactor:`, `docs:`, `style:`, `perf:`
- Reference issue numbers when applicable
- Keep commits focused and atomic

### Branches
- `main` - stable, release-ready code
- Feature branches for new development

---

## Quick Reference: Do's and Don'ts

### DO
- Ask before modifying critical features
- Sanitize input, escape output
- Test on mobile devices
- Maintain backwards compatibility
- Keep payload size minimal
- Follow existing code patterns
- Use `podloom_calculate_theme_colors()` for all player UI colors
- Test new UI on both light and dark backgrounds

### DON'T
- Add jQuery dependencies to frontend
- Store sensitive data without encryption
- Skip security checks for convenience
- Add features beyond what's requested
- Break existing functionality without discussion
- Ignore WordPress coding standards
- Hardcode colors in player UI (use the color palette system)
