#!/bin/bash

# Validate that all expected plugin-tester tags exist
# This script checks the Docker Hub API to ensure all combinations are available

set -e

# Expected tags based on our testing matrix
declare -a EXPECTED_TAGS=(
    # PHP 7.4 with all WordPress versions
    "wp-5.4" "wp-5.5" "wp-5.6" "wp-5.7" "wp-5.8" "wp-5.9"
    "wp-6.0-php7.4" "wp-6.1-php7.4" "wp-6.2-php7.4" "wp-6.3-php7.4"
    "wp-6.4-php7.4" "wp-6.5-php7.4" "wp-6.6-php7.4" "wp-6.7-php7.4" "wp-6.8-php7.4"
    
    # PHP 8.0-8.3 with WordPress 6.0+
    "wp-6.0-php8.0" "wp-6.1-php8.0" "wp-6.2-php8.0" "wp-6.3-php8.0"
    "wp-6.4-php8.0" "wp-6.5-php8.0" "wp-6.6-php8.0" "wp-6.7-php8.0" "wp-6.8-php8.0"
    
    "wp-6.0-php8.1" "wp-6.1-php8.1" "wp-6.2-php8.1" "wp-6.3-php8.1"
    "wp-6.4-php8.1" "wp-6.5-php8.1" "wp-6.6-php8.1" "wp-6.7-php8.1" "wp-6.8-php8.1"
    
    "wp-6.0-php8.2" "wp-6.1-php8.2" "wp-6.2-php8.2" "wp-6.3-php8.2"
    "wp-6.4-php8.2" "wp-6.5-php8.2" "wp-6.6-php8.2" "wp-6.7-php8.2" "wp-6.8-php8.2"
    
    "wp-6.0-php8.3" "wp-6.1-php8.3" "wp-6.2-php8.3" "wp-6.3-php8.3"
    "wp-6.4-php8.3" "wp-6.5-php8.3" "wp-6.6-php8.3" "wp-6.7-php8.3" "wp-6.8-php8.3"
)

echo "Validating plugin-tester Docker tags..."
echo "========================================"

# Fetch all available tags
ALL_TAGS=$(curl -s "https://registry.hub.docker.com/v2/repositories/humanmade/plugin-tester/tags/?page_size=100" | jq -r '.results[].name' 2>/dev/null | sort)

MISSING_TAGS=()
AVAILABLE_COUNT=0
TOTAL_COUNT=${#EXPECTED_TAGS[@]}

for tag in "${EXPECTED_TAGS[@]}"; do
    if echo "$ALL_TAGS" | grep -q "^${tag}$"; then
        echo "✓ $tag"
        AVAILABLE_COUNT=$((AVAILABLE_COUNT + 1))
    else
        echo "✗ $tag (MISSING)"
        MISSING_TAGS+=("$tag")
    fi
done

echo ""
echo "Summary:"
echo "--------"
echo "Total expected tags: $TOTAL_COUNT"
echo "Available tags: $AVAILABLE_COUNT"
echo "Missing tags: ${#MISSING_TAGS[@]}"

if [ ${#MISSING_TAGS[@]} -gt 0 ]; then
    echo ""
    echo "Missing tags:"
    for tag in "${MISSING_TAGS[@]}"; do
        echo "  - $tag"
    done
    echo ""
    echo "Note: Missing tags will cause matrix tests to fail for those combinations."
    exit 1
else
    echo ""
    echo "✓ All expected tags are available!"
    exit 0
fi