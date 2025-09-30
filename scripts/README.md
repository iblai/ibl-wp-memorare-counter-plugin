# Scripts Directory

This directory contains the build script for the IBL WP Memorare Counter Plugin.

## Available Script

### `build-zip.sh` - ZIP Builder Script
**Usage:** `./scripts/build-zip.sh`

**Features:**
- Simple and fast
- Direct ZIP creation
- Minimal output
- Perfect for WordPress upload

**Output:** `ibl-wp-memorare-counter-v1.3.zip`

## Usage Examples

```bash
# Build ZIP file
./scripts/build-zip.sh

# Or run from project root
bash scripts/build-zip.sh
```

## ZIP Contents

The generated ZIP file contains:
- `ibl_wp_memorare_counter_plugin.php` - Main plugin file
- `README.md` - Plugin documentation
- `languages/` - Translation files
- `plugin-info.txt` - WordPress plugin header info

## Installation

1. Run the build script
2. Upload the generated ZIP file to WordPress:
   - **Single Site:** Admin → Plugins → Add New → Upload Plugin
   - **Multisite:** Network Admin → Plugins → Add New → Upload Plugin

## Requirements

- Bash shell
- `zip` command (usually pre-installed on macOS/Linux)
- WordPress 5.0+ (PHP 7.4+)
