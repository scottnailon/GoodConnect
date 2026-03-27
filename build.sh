#!/bin/bash
# Build GoodConnect plugin zip for WordPress.org submission
VERSION=$(grep "Stable tag:" good-connect/readme.txt | awk '{print $3}')
OUTFILE="good-connect-${VERSION}.zip"

zip -r "$OUTFILE" good-connect/ \
    --exclude "*.git*" \
    --exclude "*.DS_Store" \
    --exclude "good-connect/assets/goodhost-icon.png" \
    --exclude "good-connect/assets/fonts/*" \
    --exclude "build.sh"

echo "Built: $OUTFILE ($(du -sh "$OUTFILE" | cut -f1))"
