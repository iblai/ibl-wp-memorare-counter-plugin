#!/bin/bash

# IBL WP Memorare Counter Plugin - ZIP Builder
# Creates a ZIP file ready for WordPress installation

set -e

PLUGIN_NAME="ibl-wp-memorare-counter"
PLUGIN_VERSION="0.4"
ZIP_NAME="${PLUGIN_NAME}-v${PLUGIN_VERSION}.zip"

# Get script directory and project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_ROOT"

# Remove old ZIP if exists
[ -f "$ZIP_NAME" ] && rm "$ZIP_NAME"

# Create ZIP with plugin files
zip -r "$ZIP_NAME" \
    ibl_wp_memorare_counter_plugin.php \
    README.md \
    languages/ \
    -x "*.DS_Store" "*.git*" "*.svn*" "scripts/*"

echo "✅ Plugin ZIP created: $ZIP_NAME"
echo "📦 Ready for WordPress upload!"
