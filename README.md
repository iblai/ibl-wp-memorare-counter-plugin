# IBLmemorare Counter

**Version:** 1.0-beta  
**Author:** ibl.ai  
**Description:** Post visit counter for WordPress. Fully compatible with WordPress Multisite.

> ⚠️ **PRERELEASE NOTICE**: This plugin is currently in testing phase. While it's functional, it's still being refined and may have minor issues. Use with caution in production environments.

## Features

- Counts visits for each post.
- **Full WordPress Multisite compatibility** - Works seamlessly across multiple sites.
- REST API endpoint with nonce verification for secure counting.
- **NEW: Most Read Posts API** - `/wp-json/iblmemorare/v1/posts/ibl-mostread` endpoint to get posts ordered by views.
- Bot detection to prevent fake views.
- Rate limiting by IP to prevent multiple counts in a short period.
- Cookie-based prevention of repeated counts from the same user.
- Admin column to display views (visible only to users with edit permissions).
- Shortcode `[iblmemorare_counter]` to display views in the frontend.
- Automatic append of views at the end of post content.
- Fully translatable with `.pot` file.
- **Multisite-specific features:**
  - Site-specific namespacing for API routes, cookies, and transients
  - Automatic activation/deactivation for new/deleted sites
  - Blog-specific salt generation for enhanced security

## ⚠️ Prerelease Status

**Current Status**: Beta Release (v1.0-beta)

- ✅ **Core functionality working**: Visit counting, admin columns, REST API
- ✅ **Multisite compatible**: Tested with WordPress Multisite
- ⚠️ **Still in development**: May have minor bugs or issues
- ⚠️ **Use with caution**: Not recommended for critical production sites yet
- 🔄 **Active testing**: Feedback and bug reports welcome

## Building the Plugin ZIP

To create the plugin ZIP file for WordPress installation:

### Using the Build Script

1. **Navigate to the plugin directory**:
   ```bash
   cd /path/to/ibl-wp-memorare-counter-plugin
   ```

2. **Run the build script**:
   ```bash
   ./scripts/build-zip.sh
   ```

3. **The script will create**: `ibl-wp-memorare-counter-v0.4.zip`

### Manual ZIP Creation

If you prefer to create the ZIP manually:

1. **Select these files**:
   - `ibl_wp_memorare_counter_plugin.php`
   - `README.md`
   - `languages/` (entire folder)

2. **Create ZIP** with these files (exclude `scripts/` folder)

## Installation

1. Download the plugin ZIP file (created above).
2. Go to your WordPress admin panel → Plugins → Add New → Upload Plugin.
3. Upload `ibl-wp-memorare-counter-v0.4.zip` and activate the plugin.
4. (Optional) Place `[iblmemorare_counter]` shortcode anywhere in your post content to display views.

## Usage

- **Admin Column:** Visit the 'Posts' page to see the 'Views' column.
- **Shortcode:** `[iblmemorare_counter]` anywhere in post content.
- **Automatic Append:** Views are automatically displayed at the end of each post.
- **Most Read Posts API:** Use the REST API endpoint to get posts ordered by views.

### API Endpoint Usage

**Get most read posts:**
```
GET /wp-json/iblmemorare/v1/posts/ibl-mostread
```

**Parameters:**
- `limit` (optional): Number of posts to return (default: 10, max: 100)
- `days` (optional): Number of days to look back (default: 30, max: 365)
- `post_type` (optional): Post type to query (default: 'post')
- `category` (optional): Category ID to filter posts (default: all categories)

**Examples:**
```
/wp-json/iblmemorare/v1/posts/ibl-mostread?limit=5&days=7
/wp-json/iblmemorare/v1/posts/ibl-mostread?limit=10&category=5
/wp-json/iblmemorare/v1/posts/ibl-mostread?limit=5&days=7&category=3
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "title": "Post Title",
      "excerpt": "Post excerpt...",
      "date": "2024-01-01",
      "url": "https://site.com/post",
      "views": 150,
      "thumbnail": "https://site.com/wp-content/uploads/image.jpg"
    }
  ],
  "total": 5,
  "limit": 5,
  "days": 7,
  "category": 3
}
```

## Languages / Translation

- The plugin is ready for translation.
- Base `.pot` file is located at `languages/iblmemorare-counter.pot`.

## Security Features

- Nonce verification for REST API requests.
- Bot detection based on user agent strings.
- Rate limiting per IP and post.
- Cookie mechanism to prevent repeated counts.
- Escaping all output for admin and frontend display.


## About ibl.ai

[ibl.ai](https://ibl.ai) builds AI-driven, revenue-generating systems for the educational sector, serving learners from over 400 universities.

## License

GPLv2 or later
