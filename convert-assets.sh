#!/bin/bash

# Enhanced Autoload Manager - Convert SVG assets to PNG
# Requires ImageMagick or librsvg

echo "Converting SVG assets to PNG format..."

# Check if ImageMagick is installed
if ! command -v convert &> /dev/null && ! command -v rsvg-convert &> /dev/null; then
    echo "Error: Neither ImageMagick nor librsvg found."
    echo "Install with: brew install imagemagick librsvg"
    exit 1
fi

# Create assets directory
mkdir -p assets

# Function to convert SVG to PNG
convert_svg() {
    local input=$1
    local output=$2
    local width=$3
    local height=$4
    
    if command -v rsvg-convert &> /dev/null; then
        echo "Converting $input to $output (${width}x${height})..."
        rsvg-convert -w $width -h $height "$input" -o "$output"
    elif command -v convert &> /dev/null; then
        echo "Converting $input to $output (${width}x${height})..."
        convert -background transparent -resize ${width}x${height} "$input" "$output"
    fi
}

# Convert icon
if [ -f "assets-source/icon.svg" ]; then
    convert_svg "assets-source/icon.svg" "assets/icon-128x128.png" 128 128
    convert_svg "assets-source/icon.svg" "assets/icon-256x256.png" 256 256
else
    echo "Warning: icon.svg not found"
fi

# Convert banners
if [ -f "assets-source/banner-772x250.svg" ]; then
    convert_svg "assets-source/banner-772x250.svg" "assets/banner-772x250.png" 772 250
else
    echo "Warning: banner-772x250.svg not found"
fi

if [ -f "assets-source/banner-1544x500.svg" ]; then
    convert_svg "assets-source/banner-1544x500.svg" "assets/banner-1544x500.png" 1544 500
else
    echo "Warning: banner-1544x500.svg not found"
fi

echo "Conversion complete! Check the assets/ directory."
echo ""
echo "Assets created:"
ls -la assets/*.png 2>/dev/null || echo "No PNG files created"