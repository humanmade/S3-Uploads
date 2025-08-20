# S3-Uploads Testing Matrix

This document describes the testing matrix for S3-Uploads across different PHP and WordPress versions.

## Supported Combinations

The plugin supports PHP ≥7.4 and WordPress ≥5.3. The testing matrix uses [humanmade/plugin-tester](https://github.com/humanmade/plugin-tester) Docker images.

### PHP 7.4
Compatible with all WordPress versions 5.4-6.8:
- wp-5.4, wp-5.5, wp-5.6, wp-5.7, wp-5.8, wp-5.9
- wp-6.0-php7.4, wp-6.1-php7.4, wp-6.2-php7.4, wp-6.3-php7.4
- wp-6.4-php7.4, wp-6.5-php7.4, wp-6.6-php7.4, wp-6.7-php7.4, wp-6.8-php7.4

### PHP 8.0, 8.1, 8.2, 8.3
Compatible with WordPress versions 6.0-6.8:
- wp-6.0-phpX.X, wp-6.1-phpX.X, wp-6.2-phpX.X, wp-6.3-phpX.X
- wp-6.4-phpX.X, wp-6.5-phpX.X, wp-6.6-phpX.X, wp-6.7-phpX.X, wp-6.8-phpX.X

## CI Testing Strategy

### Regular CI (Pull Requests & Pushes)
- **Matrix testing**: Tests a representative subset of combinations
- **Fast feedback**: ~8 combinations covering latest, minimum, and intermediate versions
- **Per-combination coverage**: Separate coverage reports for each PHP/WP combination

### Full Matrix Testing
- **Scheduled**: Weekly on Sundays at 2 AM UTC
- **Manual trigger**: Available via GitHub Actions workflow_dispatch
- **Comprehensive**: Tests all ~50+ supported combinations
- **Extensive validation**: Ensures compatibility across the full support matrix

## Running Tests Locally

```bash
# Quick test (latest versions)
./tests/run-matrix-tests.sh --quick

# Full matrix
./tests/run-matrix-tests.sh

# Specific version
./tests/run-matrix-tests.sh --php=8.3 --wp=6.8

# Single tag
./tests/run-matrix-tests.sh --tag=wp-6.8-php8.3

# Traditional single test
PLUGIN_TESTER_TAG=wp-6.8-php8.3 ./tests/run-tests.sh
```

## PHPUnit Compatibility

The composer.json uses a flexible PHPUnit version constraint (`^7.5 || ^8.0 || ^9.0`) to ensure compatibility across all PHP versions:

- **PHP 7.4**: PHPUnit 7.5, 8.x, or 9.x
- **PHP 8.0+**: PHPUnit 8.x or 9.x

This approach avoids the issues with PHPUnit 7.5's strict PHP ^7.1 requirement while maintaining backward compatibility.