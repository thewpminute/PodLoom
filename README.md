# PodLoom - Podcast Player for Transistor.fm

A WordPress plugin that allows you to connect to your Transistor.fm account and embed podcast episodes using Gutenberg blocks.

## Features

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
- **Default Show Setting**: Set a default show for quick episode embedding
- **Smart Caching**: Automatic caching of API responses to improve performance and reduce API calls
- **Cache Control**: Configurable cache duration and manual cache clearing from admin panel

## Installation

1. **Upload the Plugin**
   - Copy the `PodLoom` folder to `/wp-content/plugins/`
   - Or zip the folder and upload via WordPress admin (Plugins → Add New → Upload Plugin)

2. **Activate the Plugin**
   - Go to WordPress admin → Plugins
   - Find "PodLoom - Podcast Player for Transistor.fm" and click "Activate"

3. **Configure API Settings**
   - Go to WordPress admin → Settings → PodLoom Settings
   - Enter your Transistor API key (get it from [Transistor Dashboard](https://dashboard.transistor.fm/account))
   - Select a default show (optional but recommended)
   - Click "Save Settings"

## Getting Your API Key

1. Log in to your [Transistor Dashboard](https://dashboard.transistor.fm/)
2. Go to your Account settings
3. Find the API section
4. Copy your API key
5. Paste it into the WordPress plugin settings

## Usage

### Adding an Episode to a Post or Page

1. **Create or Edit a Post/Page**
   - Open the post/page where you want to add an episode

2. **Add the Podcast Episode Block**
   - Click the "+" button to add a new block
   - Search for "Podcast Episode" or "PodLoom"
   - Click to add the block

3. **Select a Show**
   - In the block sidebar (Inspector Controls), select a show from the dropdown
   - The default show will be pre-selected if you configured one

4. **Choose Display Mode**
   - **Latest Episode**: Automatically displays the most recent episode (updates when new episodes are published)
   - **Specific Episode**: Select and lock a specific episode to display
   - **Playlist**: Displays a playlist of episodes from the show

5. **Choose Player Theme**
   - Select "Light" or "Dark" theme in the sidebar
   - This controls the appearance of the Transistor player

6. **If Using "Specific Episode" Mode**:
   - Use the searchable dropdown to filter episodes by title
   - Scroll through the episode list
   - Episodes are shown with their status: "(Draft)" or "(Scheduled)" labels for unpublished episodes
   - Click "Load More Episodes..." to load additional episodes if needed
   - Select an episode to embed it (including draft or scheduled episodes)

7. **If Using "Latest Episode" Mode**:
   - The player will automatically display and update to show your most recent episode
   - No need to manually select episodes
   - Perfect for homepage or sidebar widgets that always show current content

8. **If Using "Playlist" Mode**:
   - The playlist will automatically display episodes from the selected show
   - Episode count is controlled in your Transistor dashboard settings
   - Visitors can browse and play episodes directly from the playlist player

9. **Preview and Publish**
   - The player will appear in the editor
   - Publish or update your post/page

### Display Mode: When to Use What

**Use "Latest Episode" Mode When:**
- Embedding on your homepage or sidebar
- You want to always showcase your newest content
- Creating a "Listen Now" section that auto-updates
- Building a podcast landing page
- You publish regularly and want visitors to hear the current episode

**Use "Specific Episode" Mode When:**
- Writing a blog post about a particular episode
- Creating evergreen content that references specific topics
- Building a podcast archive or episode directory
- You want to ensure a specific episode is always displayed
- Creating episode show notes or transcripts

**Use "Playlist" Mode When:**
- You want visitors to browse multiple episodes without leaving the page
- Creating a comprehensive podcast showcase or archive
- Building a dedicated podcast page with easy episode navigation
- You want to display your back catalog for new listeners
- Creating a binge-listening experience for your audience

### Changing Episodes and Modes

To change an already selected episode in Specific Episode mode:
1. Open the searchable episode dropdown in the block sidebar
2. Search and select a different episode

To switch between modes:
1. Select "Display Mode" in the block sidebar
2. Choose between "Specific Episode", "Latest Episode", or "Playlist"

Note: The episode count in Playlist mode is controlled from your Transistor dashboard settings, not from within the WordPress plugin.

### Working with Draft and Scheduled Episodes

The plugin allows you to select draft and scheduled episodes in addition to published episodes:

**Use Cases:**
- Prepare blog posts for episodes that are scheduled to publish later
- Preview how a draft episode will look before publishing
- Create accompanying content ahead of your episode release

**Important Notes:**
- Draft and scheduled episodes appear in the episode dropdown with "(Draft)" or "(Scheduled)" labels
- If Transistor supports embedding unpublished episodes, they will display in your post
- Latest Episode mode only shows published episodes
- Playlist mode only shows published episodes
- Episode status changes are reflected after the cache expires (default: 6 hours) or when you manually clear the cache

### Using Multiple Episodes

To add multiple episodes to a single post:
1. Add multiple "Podcast Episode" blocks
2. Configure each block independently
3. Each block embeds one episode
4. Mix and match display modes as needed

## Admin Settings Page

The **PodLoom Settings** page (Settings → PodLoom Settings) shows:

- **API Key Field**: Enter and save your Transistor API key
- **Default Show Selector**: Choose which podcast should be pre-selected in blocks
- **Enable Caching**: Toggle caching on/off to control API call frequency
- **Cache Duration**: Set how long cached data remains valid (30 minutes to 24 hours)
- **Clear Cache Button**: Manually clear all cached API responses
- **Connection Status**: Visual confirmation that your API key is working
- **Shows List**: Table displaying all your available podcasts with their details

### Caching

The plugin automatically caches API responses to improve performance and reduce API calls to Transistor:

- **Default**: Enabled with 6-hour cache duration
- **Configurable**: Choose from 30 minutes to 24 hours
- **Smart Updates**: Cache is automatically cleared when you save settings
- **Manual Control**: Use the "Clear Cache" button to force fresh data from Transistor
- **Disable Option**: Turn off caching completely if you need real-time data

Benefits of caching:
- Faster page load times in the block editor
- Reduced API usage (avoids rate limiting)
- Better performance when multiple users edit posts simultaneously
- Appropriate for podcast content which updates infrequently

## Troubleshooting

### "Please configure your Transistor API key in the settings"
- Go to Transistor API settings and enter your API key
- Make sure you're using the correct key from your Transistor account

### "Error connecting to Transistor API"
- Verify your API key is correct
- Check that your WordPress site can make external API calls
- Contact Transistor support if the key isn't working

### "No episodes found"
- Make sure your selected show has published episodes
- Check that episodes are set to "published" status in Transistor
- Try searching for a specific episode title

### Episodes not loading in block
- Check your browser console for JavaScript errors
- Ensure your WordPress installation has the Block Editor enabled
- Verify the plugin is properly activated

### Not seeing newly published episodes
- The plugin caches episodes to improve performance
- Go to Settings → PodLoom Settings and click "Clear Cache"
- Or wait for the cache duration to expire (default: 6 hours)
- You can disable caching if you need real-time data

### Want to see changes immediately
- Uncheck "Enable Caching" in Settings → PodLoom Settings
- Note: This will increase API calls and may be slower

## Technical Details

### Requirements

- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher
- **Block Editor**: Gutenberg (default in WordPress 5.0+)
- **Transistor Account**: Active account with API access

### API Endpoints Used

- `GET /v1/shows` - Retrieve podcast shows
- `GET /v1/episodes` - Retrieve episodes (with search and pagination)

### Files Structure

```
PodLoom/
├── podloom-podcast-player.php       # Main plugin file
├── admin/
│   └── admin-functions.php          # Admin page rendering
├── includes/
│   └── api.php                      # Transistor API wrapper
└── blocks/
    └── episode-block/
        ├── block.json               # Block configuration
        └── index.js                 # Block editor & frontend code
```

## Support

For issues or feature requests:
- Visit: https://thewpminute.com/podloom/
- Check the Transistor API documentation: https://developers.transistor.fm/
- Contact your WordPress administrator for technical issues

## License

GPL v2 or later

## Credits

**PodLoom** is developed by [WP Minute](https://thewpminute.com/)

Built for WordPress using the Transistor.fm API.

Transistor.fm is a trademark of Transistor, Inc. This plugin is not officially affiliated with or endorsed by Transistor, Inc.
