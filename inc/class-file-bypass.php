<?php

namespace S3_Uploads;

/**
 * File bypass rules for S3 Uploads.
 *
 * Allows certain file paths to bypass S3 operations entirely, avoiding
 * unnecessary latency and cost from WordPress plugins that constantly
 * read/write irrelevant files (e.g. .htaccess, index.html, index.php).
 *
 * Configuration via PHP constants in wp-config.php:
 *
 * Simple shorthand — all matched files use 'void' action:
 *   define( 'S3_UPLOADS_BYPASS_PATTERNS', '*.htaccess,*\/index.html,*\/index.php' );
 *
 * Full rules with per-rule actions:
 *   define( 'S3_UPLOADS_BYPASS_RULES', [
 *       [ 'pattern' => '*.htaccess',    'action' => 'void' ],
 *       [ 'pattern' => '*\/index.html',  'action' => 'void' ],
 *       [ 'pattern' => '*\/index.php',   'action' => 'exists' ],
 *       [ 'pattern' => '*\/cache/*.txt', 'action' => 'local' ],
 *       [ 'pattern' => '*\/cache/*.php', 'action' => 'local', 'target' => '/tmp/wp-bypass' ],
 *   ] );
 *
 * Actions:
 *   - 'void'   : Operations succeed silently but nothing is stored. file_exists() returns false.
 *   - 'exists' : Operations succeed silently but nothing is stored. file_exists() returns true.
 *   - 'local'  : Redirect read/write to the local filesystem. Uses the original WordPress upload
 *                directory by default, or a custom path via the 'target' key in the rule.
 *
 * Both constants can be defined simultaneously; S3_UPLOADS_BYPASS_RULES is applied first.
 */
class File_Bypass {

	/**
	 * Cached bypass rules.
	 *
	 * @var array[]|null
	 * @psalm-var list<array{pattern: string, action: string, target?: string}>|null
	 */
	private static $rules = null;

	/**
	 * Get all configured bypass rules.
	 *
	 * Rules are loaded from constants and the `s3_uploads_bypass_rules` filter, then cached
	 * for the remainder of the request. Call reset() to clear the cache (e.g. in tests).
	 *
	 * @return array[]
	 * @psalm-return list<array{pattern: string, action: string, target?: string}>
	 */
	public static function get_rules() : array {
		if ( self::$rules !== null ) {
			return self::$rules;
		}

		/** @psalm-var list<array{pattern: string, action: string, target?: string}> $rules */
		$rules = [];

		if ( defined( 'S3_UPLOADS_BYPASS_RULES' ) && is_array( S3_UPLOADS_BYPASS_RULES ) ) {
			/** @psalm-var list<array{pattern: string, action: string, target?: string}> $defined_rules */
			$defined_rules = S3_UPLOADS_BYPASS_RULES;
			$rules = $defined_rules;
		}

		if ( defined( 'S3_UPLOADS_BYPASS_PATTERNS' ) && is_string( S3_UPLOADS_BYPASS_PATTERNS ) && S3_UPLOADS_BYPASS_PATTERNS ) {
			foreach ( explode( ',', (string) S3_UPLOADS_BYPASS_PATTERNS ) as $pattern ) {
				$pattern = trim( $pattern );
				if ( $pattern !== '' ) {
					$rules[] = [ 'pattern' => $pattern, 'action' => 'void' ];
				}
			}
		}

		/**
		 * Filter the list of file bypass rules.
		 *
		 * This runs after constants are loaded, allowing rules to be added or replaced
		 * programmatically (useful for testing and for mu-plugins that cannot use constants).
		 *
		 * @param array[] $rules Existing rules from S3_UPLOADS_BYPASS_RULES / S3_UPLOADS_BYPASS_PATTERNS.
		 * @return array[]
		 */
		if ( function_exists( 'apply_filters' ) ) {
			/** @psalm-var list<array{pattern: string, action: string, target?: string}> $rules */
			$rules = apply_filters( 's3_uploads_bypass_rules', $rules );
		}

		self::$rules = $rules;
		return $rules;
	}

	/**
	 * Clear the cached rules so they are reloaded on the next call to get_rules().
	 *
	 * Intended for use in tests and when rules need to be refreshed at runtime.
	 */
	public static function reset() : void {
		self::$rules = null;
	}

	/**
	 * Return the first bypass rule matching the given S3 path, or null.
	 *
	 * Matching is attempted against both the full path (e.g. s3://bucket/uploads/2024/.htaccess)
	 * and the basename only (e.g. .htaccess), so simple patterns like '*.htaccess' work without
	 * needing to include the full path prefix.
	 *
	 * @param string $path Full S3 path (e.g. s3://bucket/path/to/file).
	 * @return array|null
	 * @psalm-return array{pattern: string, action: string, target?: string}|null
	 */
	public static function match( string $path ) : ?array {
		foreach ( self::get_rules() as $rule ) {
			if ( fnmatch( $rule['pattern'], $path ) || fnmatch( $rule['pattern'], basename( $path ) ) ) {
				return $rule;
			}
		}
		return null;
	}

	/**
	 * Return the first bypass rule matching a directory path or its descendants.
	 *
	 * Rules are commonly written as descendant globs (e.g. *\/redux/*). Directory
	 * operations like opendir() and mkdir() receive the directory itself
	 * (e.g. s3://bucket/uploads/redux), so also try the same path with a trailing slash.
	 *
	 * @param string $path Full S3 directory path.
	 * @return array|null
	 * @psalm-return array{pattern: string, action: string, target?: string}|null
	 */
	public static function match_directory( string $path ) : ?array {
		$path = rtrim( $path, '/' );
		return self::match( $path ) ?: self::match( $path . '/' );
	}
}
