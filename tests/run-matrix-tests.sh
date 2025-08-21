#!/bin/bash

# Matrix test script for S3-Uploads plugin
# Tests against multiple PHP and WordPress versions

set -e

# Define the matrix of versions to test
# Based on plugin requirements: PHP >= 7.4, WordPress >= 5.3
declare -a PHP_VERSIONS=("7.4" "8.0" "8.1" "8.2" "8.3")
declare -a WP_VERSIONS=()

# PHP 7.4 can test against all WordPress versions from 5.4+
WP_VERSIONS_74=("5.4" "5.5" "5.6" "5.7" "5.8" "5.9" "6.0" "6.1" "6.2" "6.3" "6.4" "6.5" "6.6" "6.7" "6.8")

# PHP 8.0+ only have tags for WordPress 6.0+
WP_VERSIONS_8X=("6.0" "6.1" "6.2" "6.3" "6.4" "6.5" "6.6" "6.7" "6.8")

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Counters
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Array to track failures
declare -a FAILED_COMBINATIONS

# Function to run tests for a specific combination
run_test_combination() {
    local php_version=$1
    local wp_version=$2
    local tag="wp-${wp_version}-php${php_version}"
    
    echo -e "\n${YELLOW}Testing PHP ${php_version} with WordPress ${wp_version} (${tag})${NC}"
    echo "----------------------------------------"
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    # Set the tag for the test script
    export PLUGIN_TESTER_TAG=$tag
    
    # Run the tests
    if ./tests/run-tests.sh --coverage-clover=coverage-${tag}.xml; then
        echo -e "${GREEN}✓ PHP ${php_version} + WP ${wp_version} PASSED${NC}"
        PASSED_TESTS=$((PASSED_TESTS + 1))
    else
        echo -e "${RED}✗ PHP ${php_version} + WP ${wp_version} FAILED${NC}"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        FAILED_COMBINATIONS+=("${php_version}+${wp_version}")
    fi
}

# Parse command line arguments
QUICK_MODE=false
SINGLE_VERSION=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --quick)
            QUICK_MODE=true
            shift
            ;;
        --php=*)
            SINGLE_PHP_VERSION="${1#*=}"
            shift
            ;;
        --wp=*)
            SINGLE_WP_VERSION="${1#*=}"
            shift
            ;;
        --tag=*)
            SINGLE_TAG="${1#*=}"
            shift
            ;;
        --help)
            echo "Usage: $0 [options]"
            echo "Options:"
            echo "  --quick           Run a quick subset of tests (latest versions only)"
            echo "  --php=VERSION     Test only specific PHP version (7.4, 8.0, 8.1, 8.2, 8.3)"
            echo "  --wp=VERSION      Test only specific WordPress version"
            echo "  --tag=TAG         Test only specific plugin-tester tag (e.g., wp-6.8-php8.3)"
            echo "  --help            Show this help message"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

echo "S3-Uploads Matrix Testing"
echo "========================="

# Single tag mode
if [ ! -z "$SINGLE_TAG" ]; then
    echo "Testing single tag: $SINGLE_TAG"
    export PLUGIN_TESTER_TAG=$SINGLE_TAG
    ./tests/run-tests.sh
    exit $?
fi

# Quick mode - test latest versions only
if [ "$QUICK_MODE" = true ]; then
    echo "Quick mode: Testing latest versions only"
    run_test_combination "8.3" "6.8"
    run_test_combination "8.2" "6.8" 
    run_test_combination "7.4" "6.8"
else
    echo "Full matrix mode: Testing all supported combinations"
    
    # Test PHP 7.4 with all WordPress versions
    if [ -z "$SINGLE_PHP_VERSION" ] || [ "$SINGLE_PHP_VERSION" = "7.4" ]; then
        for wp_version in "${WP_VERSIONS_74[@]}"; do
            if [ -z "$SINGLE_WP_VERSION" ] || [ "$SINGLE_WP_VERSION" = "$wp_version" ]; then
                run_test_combination "7.4" "$wp_version"
            fi
        done
    fi
    
    # Test PHP 8.x with WordPress 6.0+
    for php_version in "8.0" "8.1" "8.2" "8.3"; do
        if [ -z "$SINGLE_PHP_VERSION" ] || [ "$SINGLE_PHP_VERSION" = "$php_version" ]; then
            for wp_version in "${WP_VERSIONS_8X[@]}"; do
                if [ -z "$SINGLE_WP_VERSION" ] || [ "$SINGLE_WP_VERSION" = "$wp_version" ]; then
                    run_test_combination "$php_version" "$wp_version"
                fi
            done
        fi
    done
fi

# Print summary
echo -e "\n${YELLOW}Test Summary${NC}"
echo "============="
echo "Total combinations tested: $TOTAL_TESTS"
echo -e "Passed: ${GREEN}$PASSED_TESTS${NC}"
echo -e "Failed: ${RED}$FAILED_TESTS${NC}"

if [ ${#FAILED_COMBINATIONS[@]} -gt 0 ]; then
    echo -e "\n${RED}Failed combinations:${NC}"
    for combo in "${FAILED_COMBINATIONS[@]}"; do
        echo "  - PHP $combo"
    done
    echo ""
    exit 1
else
    echo -e "\n${GREEN}All tests passed!${NC}"
    exit 0
fi