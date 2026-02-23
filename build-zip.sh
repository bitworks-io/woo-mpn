#!/bin/bash
# Build plugin zip for WordPress installation
# Usage: ./build-zip.sh
# Alternative: php build-zip.php (uses PHP ZipArchive, works when shell/rsync fail)

set -e
cd "$(dirname "$0")"

if command -v php &>/dev/null; then
  php build-zip.php && exit 0
fi

VERSION=$(grep "Version:" woo-mpn.php | head -1 | sed 's/.*: *//')
ZIP_NAME="woo-mpn-${VERSION}.zip"

echo "Building $ZIP_NAME (version $VERSION)..."

# Clean previous build
rm -rf build
mkdir -p build/woo-mpn

# Copy plugin files (exclude dev files)
rsync -a \
  --exclude='.git' \
  --exclude='.cursor' \
  --exclude='*.zip' \
  --exclude='build' \
  --exclude='.DS_Store' \
  --exclude='.gitignore' \
  --exclude='build-zip.sh' \
  --exclude='build-zip.php' \
  --exclude='zip-info.txt' \
  . build/woo-mpn/

# Create zip (remove existing first to ensure fresh overwrite)
cd build
rm -f "../$ZIP_NAME"
zip -r "../$ZIP_NAME" woo-mpn
cd ..

# Cleanup
rm -rf build

echo "Created: $ZIP_NAME"
ls -la "$ZIP_NAME"
unzip -l "$ZIP_NAME" | head -20
