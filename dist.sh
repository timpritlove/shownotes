#!/usr/bin/env bash
# Creates a distributable zip of a WordPress plugin with all Composer dependencies included.
# The plugin name and version are read from the plugin's own header.
#
# Usage: ./dist.sh
# Output: tmp/<PluginFolder>-<version>.zip  (unpacks to a folder named after the plugin directory)

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"

# The plugin folder name becomes the install directory name (e.g. OSFX)
PLUGIN_FOLDER="$(basename "$PLUGIN_DIR")"

# Find the main plugin file: the PHP file in the root that contains a "Plugin Name:" header
MAIN_FILE=$(grep -rl 'Plugin Name:' "$PLUGIN_DIR"/*.php 2>/dev/null | head -1)
if [[ -z "$MAIN_FILE" ]]; then
  echo "Error: no plugin main file found (missing 'Plugin Name:' header)" >&2
  exit 1
fi

# Read name and version from the plugin header
PLUGIN_NAME=$(php -r "
  preg_match('/Plugin Name:\s+(.+)/i', file_get_contents('$MAIN_FILE'), \$m);
  echo trim(\$m[1] ?? '');
")
VERSION=$(php -r "
  preg_match('/Version:\s+(.+)/i', file_get_contents('$MAIN_FILE'), \$m);
  echo trim(\$m[1] ?? 'dev');
")

STAGE_DIR="$PLUGIN_DIR/tmp/$PLUGIN_FOLDER"
ZIP_FILE="$PLUGIN_DIR/tmp/${PLUGIN_FOLDER}-${VERSION}.zip"

echo "Building ${PLUGIN_NAME} ${VERSION}..."

# Ensure Composer dependencies are installed
cd "$PLUGIN_DIR"
if [[ -f composer.json ]]; then
  composer install --no-dev --no-interaction --quiet
fi

# Stage the plugin files
mkdir -p "$PLUGIN_DIR/tmp"
rm -rf "$STAGE_DIR"
rsync -a \
  --exclude='.git' \
  --exclude='.gitignore' \
  --exclude='dist.sh' \
  --exclude='composer.phar' \
  --exclude='tmp/' \
  --exclude='.DS_Store' \
  --exclude='vendor/*/test*' \
  --exclude='vendor/*/doc*' \
  --exclude='vendor/*/CHANGELOG*' \
  --exclude='vendor/*/README*' \
  --exclude='vendor/*/phpunit*' \
  "$PLUGIN_DIR/" "$STAGE_DIR/"

# Zip it up
rm -f "$ZIP_FILE"
cd "$PLUGIN_DIR/tmp"
zip -r --quiet "$ZIP_FILE" "$PLUGIN_FOLDER"
rm -rf "$STAGE_DIR"

echo "Done: $ZIP_FILE ($(du -sh "$ZIP_FILE" | cut -f1))"
