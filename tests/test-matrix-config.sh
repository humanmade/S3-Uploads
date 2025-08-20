#!/bin/bash

# Simple test script to validate matrix configuration without Docker dependencies
# This tests the matrix configuration and scripts without requiring network access

set -e

echo "Testing matrix configuration..."
echo "=============================="

# Test 1: Validate matrix script exists and is executable
echo "✓ Checking matrix test script..."
if [[ -x "./tests/run-matrix-tests.sh" ]]; then
    echo "  Matrix test script is executable"
else
    echo "  ✗ Matrix test script is not executable"
    exit 1
fi

# Test 2: Validate tag validation script
echo "✓ Checking tag validation script..."
if [[ -x "./tests/validate-tags.sh" ]]; then
    echo "  Tag validation script is executable"
else
    echo "  ✗ Tag validation script is not executable"  
    exit 1
fi

# Test 3: Check matrix script help
echo "✓ Testing matrix script help..."
if ./tests/run-matrix-tests.sh --help > /dev/null; then
    echo "  Matrix script help works"
else
    echo "  ✗ Matrix script help failed"
    exit 1
fi

# Test 4: Validate composer.json structure
echo "✓ Checking composer.json..."
if command -v jq > /dev/null; then
    # Check PHPUnit version range
    phpunit_version=$(jq -r '.["require-dev"]["phpunit/phpunit"]' composer.json)
    if [[ "$phpunit_version" == "^7.5 || ^8.0 || ^9.0" ]]; then
        echo "  PHPUnit version range is correct: $phpunit_version"
    else
        echo "  ✗ PHPUnit version range is incorrect: $phpunit_version"
        exit 1
    fi
    
    # Check test scripts
    test_script=$(jq -r '.scripts.test' composer.json)
    if [[ "$test_script" == "./tests/run-tests.sh" ]]; then
        echo "  Test script is correct"
    else
        echo "  ✗ Test script is incorrect: $test_script"
        exit 1
    fi
    
    matrix_script=$(jq -r '.scripts["test:matrix"]' composer.json)
    if [[ "$matrix_script" == "./tests/run-matrix-tests.sh" ]]; then
        echo "  Matrix test script is correct"
    else
        echo "  ✗ Matrix test script is incorrect: $matrix_script"
        exit 1
    fi
else
    echo "  jq not available, skipping JSON validation"
fi

# Test 5: Check CI workflow
echo "✓ Checking CI workflow..."
if [[ -f ".github/workflows/ci.yml" ]]; then
    if grep -q "test-matrix" .github/workflows/ci.yml; then
        echo "  CI workflow has matrix testing job"
    else
        echo "  ✗ CI workflow missing matrix testing job"
        exit 1
    fi
    
    if grep -q "strategy:" .github/workflows/ci.yml; then
        echo "  CI workflow has test matrix strategy"
    else
        echo "  ✗ CI workflow missing test matrix strategy"
        exit 1
    fi
else
    echo "  ✗ CI workflow file not found"
    exit 1
fi

echo ""
echo "✓ All matrix configuration tests passed!"
echo ""
echo "Matrix features available:"
echo "- composer test:matrix       # Run full matrix locally"  
echo "- composer test:quick        # Run quick matrix subset"
echo "- composer test:validate-tags # Validate Docker tags"
echo "- CI matrix testing on PRs   # 8 key combinations"
echo "- Full matrix on schedule    # Weekly comprehensive testing"