# PodLoom Filters Reference

This document provides a comprehensive reference for all WordPress filters available in the PodLoom Podcast Player plugin. Filters allow developers to customize and extend the plugin's functionality without modifying core files.

---

## What Are WordPress Filters?

Filters are one of the two types of [Hooks](https://developer.wordpress.org/plugins/hooks/) in WordPress (the other being Actions). They provide a way for developers to modify data during the execution of WordPress or a plugin, without editing the original source code.

**Key concepts:**

- **Filters modify data** - They receive data, optionally modify it, and return it
- **Non-destructive** - Your customizations live in your theme or a separate plugin, so updates won't overwrite them
- **Chainable** - Multiple filters can be applied to the same data, each one passing its result to the next

### Official WordPress Documentation

- [Plugin Handbook: Hooks](https://developer.wordpress.org/plugins/hooks/) - Introduction to hooks
- [Plugin Handbook: Filters](https://developer.wordpress.org/plugins/hooks/filters/) - Detailed filter documentation
- [add_filter() Reference](https://developer.wordpress.org/reference/functions/add_filter/) - Function reference
- [apply_filters() Reference](https://developer.wordpress.org/reference/functions/apply_filters/) - How filters are triggered

---

## How to Use Filters

### Basic Syntax

```php
add_filter('filter_name', 'your_callback_function', $priority, $accepted_args);
```

| Parameter | Description | Default |
|-----------|-------------|---------|
| `filter_name` | The name of the filter to hook into | Required |
| `callback` | The function to call when the filter runs | Required |
| `priority` | Order in which the function is executed (lower = earlier) | 10 |
| `accepted_args` | Number of arguments your function accepts | 1 |

### Where to Add Filter Code

You can add filter code in several places:

1. **Your theme's `functions.php`** - Simple, but lost if you change themes
2. **A child theme's `functions.php`** - Survives parent theme updates
3. **A custom plugin** - Best for site-specific functionality (recommended)
4. **A code snippets plugin** - Easy for non-developers

### Example: Creating a Custom Plugin for Filters

Create a file called `my-podloom-customizations.php` in `/wp-content/plugins/`:

```php
<?php
/**
 * Plugin Name: My PodLoom Customizations
 * Description: Custom filters for PodLoom Podcast Player
 * Version: 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add your filters here
add_filter('podloom_episode_data', function($episode, $feed_id, $attributes) {
    // Your customization code
    return $episode;
}, 10, 3);
```

Then activate it in WordPress Admin → Plugins.

### Anonymous Functions vs Named Functions

**Anonymous function (closure):**
```php
add_filter('podloom_episode_data', function($episode) {
    $episode['title'] = strtoupper($episode['title']);
    return $episode;
});
```

**Named function:**
```php
function my_modify_episode_title($episode) {
    $episode['title'] = strtoupper($episode['title']);
    return $episode;
}
add_filter('podloom_episode_data', 'my_modify_episode_title');
```

Named functions are easier to debug and can be removed with `remove_filter()`.

### Removing a Filter

```php
// Remove a named function filter
remove_filter('podloom_episode_data', 'my_modify_episode_title', 10);

// Note: Anonymous functions cannot be removed
```

---

## Table of Contents

- [Player Filters](#player-filters)
  - [podloom_episode_data](#podloom_episode_data)
  - [podloom_player_html](#podloom_player_html)
- [Cache Filters](#cache-filters)
  - [podloom_cache_duration](#podloom_cache_duration)
- [Transcript Proxy Filters](#transcript-proxy-filters)
  - [podloom_transcript_rate_limit](#podloom_transcript_rate_limit)
  - [podloom_transcript_max_size](#podloom_transcript_max_size)
  - [podloom_transcript_validate_url](#podloom_transcript_validate_url)
  - [podloom_transcript_request_args](#podloom_transcript_request_args)
  - [podloom_transcript_strict_content_type](#podloom_transcript_strict_content_type)
  - [podloom_transcript_allowed_content_types](#podloom_transcript_allowed_content_types)

---

## Player Filters

### podloom_episode_data

Filter episode data before the player is rendered. This allows you to modify any aspect of the episode (title, description, audio URL, artwork, etc.) before it's displayed.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$episode` | array | Episode data array containing title, description, audio_url, image, date, duration, podcast20, etc. |
| `$feed_id` | string | The RSS feed ID |
| `$attributes` | array | Block attributes |

**Example: Modify episode title**

```php
add_filter('podloom_episode_data', function($episode, $feed_id, $attributes) {
    // Add episode number prefix
    if (!empty($episode['title'])) {
        $episode['title'] = 'Episode: ' . $episode['title'];
    }
    return $episode;
}, 10, 3);
```

**Example: Add tracking parameter to audio URL**

```php
add_filter('podloom_episode_data', function($episode, $feed_id, $attributes) {
    if (!empty($episode['audio_url'])) {
        $episode['audio_url'] = add_query_arg('source', 'website', $episode['audio_url']);
    }
    return $episode;
}, 10, 3);
```

**Example: Filter episodes by feed**

```php
add_filter('podloom_episode_data', function($episode, $feed_id, $attributes) {
    // Only modify episodes from a specific feed
    if ($feed_id === 'abc123') {
        $episode['description'] = wp_trim_words($episode['description'], 50);
    }
    return $episode;
}, 10, 3);
```

---

### podloom_player_html

Filter the complete player HTML output before it's rendered on the page. Useful for adding wrapper elements, custom data attributes, or post-processing the HTML.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$output` | string | The complete player HTML |
| `$episode` | array | Episode data array |
| `$feed_id` | string | The RSS feed ID |
| `$attributes` | array | Block attributes |

**Example: Add wrapper div**

```php
add_filter('podloom_player_html', function($output, $episode, $feed_id, $attributes) {
    return '<div class="my-podcast-wrapper">' . $output . '</div>';
}, 10, 4);
```

**Example: Add data attributes for JavaScript**

```php
add_filter('podloom_player_html', function($output, $episode, $feed_id, $attributes) {
    // Add episode ID as data attribute
    $output = str_replace(
        'class="wp-block-podloom-episode-player',
        'data-episode-id="' . esc_attr($episode['guid'] ?? '') . '" class="wp-block-podloom-episode-player',
        $output
    );
    return $output;
}, 10, 4);
```

**Example: Add schema.org markup**

```php
add_filter('podloom_player_html', function($output, $episode, $feed_id, $attributes) {
    $schema = sprintf(
        '<script type="application/ld+json">{"@context":"https://schema.org","@type":"PodcastEpisode","name":"%s"}</script>',
        esc_js($episode['title'] ?? '')
    );
    return $schema . $output;
}, 10, 4);
```

---

## Cache Filters

### podloom_cache_duration

Filter the cache duration for RSS feeds. Allows per-feed cache duration overrides, which is useful for feeds that update at different frequencies.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$cache_duration` | int | Cache duration in seconds (default: 21600 = 6 hours) |
| `$feed_id` | string | The RSS feed ID |
| `$feed` | array | Feed configuration array (contains url, name, etc.) |

**Example: Set shorter cache for news podcast**

```php
add_filter('podloom_cache_duration', function($duration, $feed_id, $feed) {
    // Check if this is the daily news feed
    if (strpos($feed['url'], 'daily-news') !== false) {
        return HOUR_IN_SECONDS; // 1 hour cache
    }
    return $duration;
}, 10, 3);
```

**Example: Longer cache for archived shows**

```php
add_filter('podloom_cache_duration', function($duration, $feed_id, $feed) {
    // Archived shows rarely update
    if ($feed['name'] === 'Classic Episodes') {
        return WEEK_IN_SECONDS; // 1 week cache
    }
    return $duration;
}, 10, 3);
```

**Example: Different cache by feed ID**

```php
add_filter('podloom_cache_duration', function($duration, $feed_id, $feed) {
    $cache_times = [
        'feed_abc123' => HOUR_IN_SECONDS,      // 1 hour
        'feed_def456' => DAY_IN_SECONDS,       // 24 hours
        'feed_ghi789' => WEEK_IN_SECONDS,      // 1 week
    ];

    return $cache_times[$feed_id] ?? $duration;
}, 10, 3);
```

---

## Transcript Proxy Filters

These filters control the behavior of the transcript proxy endpoint, which fetches transcript files from external servers to bypass CORS restrictions.

### podloom_transcript_rate_limit

Control the rate limit for transcript requests per IP address.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$rate_limit` | int | Requests allowed per minute per IP (default: 15) |

**Example: Increase rate limit**

```php
add_filter('podloom_transcript_rate_limit', function($limit) {
    return 30; // Allow 30 requests per minute
});
```

**Example: Disable rate limiting**

```php
add_filter('podloom_transcript_rate_limit', '__return_zero');
```

---

### podloom_transcript_max_size

Control the maximum allowed transcript file size.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$max_size` | int | Maximum size in bytes (default: 2097152 = 2MB) |

**Example: Allow larger transcripts**

```php
add_filter('podloom_transcript_max_size', function($size) {
    return 5 * 1024 * 1024; // 5MB
});
```

---

### podloom_transcript_validate_url

Add custom URL validation for transcript requests. Return an error string to block the request, or null to allow it.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$error` | string\|null | Error message (null = no error) |
| `$url` | string | The transcript URL being requested |

**Example: Block specific domains**

```php
add_filter('podloom_transcript_validate_url', function($error, $url) {
    $blocked_domains = ['spam-site.com', 'malicious.net'];

    foreach ($blocked_domains as $domain) {
        if (strpos($url, $domain) !== false) {
            return 'This domain is not allowed';
        }
    }

    return $error;
}, 10, 2);
```

**Example: Only allow specific hosts**

```php
add_filter('podloom_transcript_validate_url', function($error, $url) {
    $allowed_hosts = ['transistor.fm', 'buzzsprout.com', 'libsyn.com'];
    $host = parse_url($url, PHP_URL_HOST);

    $is_allowed = false;
    foreach ($allowed_hosts as $allowed) {
        if (strpos($host, $allowed) !== false) {
            $is_allowed = true;
            break;
        }
    }

    if (!$is_allowed) {
        return 'Transcript host not in allowlist';
    }

    return $error;
}, 10, 2);
```

---

### podloom_transcript_request_args

Modify the arguments passed to `wp_remote_get()` when fetching transcripts.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$args` | array | Request arguments (timeout, sslverify, headers, etc.) |
| `$url` | string | The transcript URL being fetched |

**Example: Increase timeout for slow servers**

```php
add_filter('podloom_transcript_request_args', function($args, $url) {
    $args['timeout'] = 30; // 30 seconds
    return $args;
}, 10, 2);
```

**Example: Add custom headers**

```php
add_filter('podloom_transcript_request_args', function($args, $url) {
    $args['headers'] = [
        'Accept' => 'text/plain, text/vtt, application/json',
        'X-Custom-Header' => 'my-value'
    ];
    return $args;
}, 10, 2);
```

---

### podloom_transcript_strict_content_type

Enable strict content-type validation for transcript responses.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$strict` | bool | Whether to validate content-type (default: false) |

**Example: Enable strict validation**

```php
add_filter('podloom_transcript_strict_content_type', '__return_true');
```

---

### podloom_transcript_allowed_content_types

Define which content types are allowed when strict content-type validation is enabled.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$types` | array | Array of allowed MIME types |

**Default allowed types:**
- `text/plain`
- `text/html`
- `application/json`
- `text/vtt`
- `application/x-subrip`
- `text/srt`
- `application/srt`

**Example: Add custom content type**

```php
add_filter('podloom_transcript_allowed_content_types', function($types) {
    $types[] = 'application/xml';
    $types[] = 'text/xml';
    return $types;
});
```

---

## Best Practices

### 1. Always return the filtered value

```php
// Good
add_filter('podloom_episode_data', function($episode) {
    $episode['title'] = 'Modified: ' . $episode['title'];
    return $episode; // Always return!
});

// Bad - will break the player
add_filter('podloom_episode_data', function($episode) {
    $episode['title'] = 'Modified: ' . $episode['title'];
    // Forgot to return!
});
```

### 2. Use the correct number of arguments

```php
// Specify accepted arguments count
add_filter('podloom_episode_data', 'my_function', 10, 3); // priority 10, 3 args
add_filter('podloom_player_html', 'my_function', 10, 4);  // priority 10, 4 args
```

### 3. Check for expected data before modifying

```php
add_filter('podloom_episode_data', function($episode) {
    // Check if key exists before modifying
    if (!empty($episode['title'])) {
        $episode['title'] = sanitize_text_field($episode['title']);
    }
    return $episode;
});
```

### 4. Use appropriate priority

```php
// Run early (before other filters)
add_filter('podloom_episode_data', 'my_early_filter', 5);

// Run late (after other filters)
add_filter('podloom_episode_data', 'my_late_filter', 99);
```

---

## Need Help?

If you need a filter that doesn't exist, please [open a feature request](https://github.com/your-repo/podloom/issues) on GitHub.
