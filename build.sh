#!/bin/bash
# Build GoodConnect plugin zip for WordPress.org / GitHub release
set -e

VERSION=$(grep "Stable tag:" good-connect/readme.txt | awk '{print $3}')
OUTFILE="good-connect-${VERSION}.zip"

echo "Building GoodConnect v${VERSION}..."

# Clean any previous build
rm -f good-connect-*.zip

zip -r "$OUTFILE" good-connect/ \
    -x "*.git*" \
    -x "*.DS_Store" \
    -x "*.bak" \
    -x "*.bak-*" \
    -x "good-connect/assets/goodhost-icon.png" \
    -x "good-connect/assets/fonts/*"

echo ""
echo "Built: $OUTFILE ($(du -sh "$OUTFILE" | cut -f1))"
echo ""
echo "To create a GitHub release:"
echo "  gh release create v${VERSION} ${OUTFILE} --title \"v${VERSION}\" --notes \"See readme.txt for changelog\""
