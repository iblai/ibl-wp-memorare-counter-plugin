# IBLmemorare Counter

**Version:** 1.2  
**Author:** Your Name  
**Description:** Post visit counter with strict security measures for WordPress.

## Features

- Counts visits for each post.
- REST API endpoint with nonce verification for secure counting.
- Bot detection to prevent fake views.
- Rate limiting by IP to prevent multiple counts in a short period.
- Cookie-based prevention of repeated counts from the same user.
- Admin column to display views (visible only to users with edit permissions).
- Shortcode `[iblmemorare_counter]` to display views in the frontend.
- Automatic append of views at the end of post content.
- Fully translatable with `.pot` file.

## Installation

1. Download the plugin ZIP file.
2. Go to your WordPress admin panel → Plugins → Add New → Upload Plugin.
3. Upload `iblmemorare-counter.zip` and activate the plugin.
4. (Optional) Place `[iblmemorare_counter]` shortcode anywhere in your post content to display views.

## Usage

- **Admin Column:** Visit the 'Posts' page to see the 'Views' column.
- **Shortcode:** `[iblmemorare_counter]` anywhere in post content.
- **Automatic Append:** Views are automatically displayed at the end of each post.

## Languages / Translation

- The plugin is ready for translation.
- Base `.pot` file is located at `languages/iblmemorare-counter.pot`.

## Security Features

- Nonce verification for REST API requests.
- Bot detection based on user agent strings.
- Rate limiting per IP and post.
- Cookie mechanism to prevent repeated counts.
- Escaping all output for admin and frontend display.

## Changelog

### 1.2
- Updated all text strings to English.
- Enhanced security measures.

### 1.1
- Initial secure version with REST API tracking, cookie and rate-limit protections.

### 1.0
- Basic post view counter with admin column and shortcode.

## License

GPLv2 or later
