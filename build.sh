#!/bin/bash

# PodLoom Podcast Player - Build Script
# Creates a clean zip file for WordPress plugin distribution

set -e  # Exit on error

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  PodLoom Podcast Player - Build${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Get plugin info from main file
PLUGIN_FILE="podloom-podcast-player.php"
PLUGIN_NAME="podloom-podcast-player"
VERSION=$(grep "Version:" $PLUGIN_FILE | head -1 | awk '{print $3}')

if [ -z "$VERSION" ]; then
    echo -e "${RED}Error: Could not detect plugin version${NC}"
    exit 1
fi

echo -e "${GREEN}Plugin:${NC} $PLUGIN_NAME"
echo -e "${GREEN}Version:${NC} $VERSION"
echo ""

# Create build directory
BUILD_DIR="build"
TEMP_DIR="$BUILD_DIR/temp"
OUTPUT_DIR="$BUILD_DIR/releases"
ZIP_NAME="$PLUGIN_NAME-$VERSION.zip"

echo -e "${BLUE}→${NC} Creating build directories..."
rm -rf "$BUILD_DIR"
mkdir -p "$OUTPUT_DIR"
mkdir -p "$TEMP_DIR/$PLUGIN_NAME"

# Copy files to temp directory, excluding development files
echo -e "${BLUE}→${NC} Copying plugin files..."
rsync -av \
    --exclude='.git' \
    --exclude='.gitignore' \
    --exclude='.claude' \
    --exclude='.DS_Store' \
    --exclude='docs/' \
    --exclude='README.md' \
    --exclude='BUILD.md' \
    --exclude='build.sh' \
    --exclude='build/' \
    --exclude='node_modules/' \
    --exclude='.vscode/' \
    --exclude='.idea/' \
    --exclude='*.log' \
    --exclude='*.zip' \
    --exclude='debug-*.php' \
    . "$TEMP_DIR/$PLUGIN_NAME/"

# Count files
FILE_COUNT=$(find "$TEMP_DIR/$PLUGIN_NAME" -type f | wc -l | xargs)
echo -e "${GREEN}✓${NC} Copied $FILE_COUNT files"
echo ""

# Create zip file
echo -e "${BLUE}→${NC} Creating zip archive..."
cd "$TEMP_DIR"
zip -q -r "../../$OUTPUT_DIR/$ZIP_NAME" "$PLUGIN_NAME"
cd ../..

# Calculate zip size
ZIP_SIZE=$(du -h "$OUTPUT_DIR/$ZIP_NAME" | cut -f1)

# Cleanup temp directory
echo -e "${BLUE}→${NC} Cleaning up..."
rm -rf "$TEMP_DIR"

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Build Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "${GREEN}Output:${NC} $OUTPUT_DIR/$ZIP_NAME"
echo -e "${GREEN}Size:${NC} $ZIP_SIZE"
echo ""
echo -e "${YELLOW}Ready to upload to WordPress!${NC}"
echo ""
