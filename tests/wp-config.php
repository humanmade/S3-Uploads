<?php // phpcs:ignore PSR1.Files.SideEffects.FoundWithSymbols

/* Path to the WordPress codebase you'd like to test. Add a forward slash in the end. */
define( 'ABSPATH', dirname( dirname( __FILE__ ) ) . '/wordpress/' );

/*
 * Path to the theme to test with.
 *
 * The 'default' theme is symlinked from test/phpunit/data/themedir1/default into
 * the themes directory of the WordPress installation defined above.
 */
define( 'WP_DEFAULT_THEME', 'default' );

// Test with multisite enabled.
// Alternatively, use the tests/phpunit/multisite.xml configuration file.
// define( 'WP_TESTS_MULTISITE', true );

// Force known bugs to be run.
// Tests with an associated Trac ticket that is still open are normally skipped.
// define( 'WP_TESTS_FORCE_KNOWN_BUGS', true );

// Test with WordPress debug mode (default).
define( 'WP_DEBUG', true );

// ** MySQL settings ** //

// This configuration file will be used by the copy of WordPress being tested.
// wordpress/wp-config.php will be ignored.

// WARNING WARNING WARNING!
// These tests will DROP ALL TABLES in the database with the prefix named below.
// DO NOT use a production database or one that is shared with something else.

define( 'DB_NAME', getenv( 'WP_DB_NAME' ) ?: 'wp_tests' );
define( 'DB_USER', getenv( 'WP_DB_USER' ) ?: 'wordpress' );
define( 'DB_PASSWORD', getenv( 'WP_DB_PASS' ) ?: 'wordpress' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 */
define( 'AUTH_KEY', '*V+D5;=Pas=duws<Pm-gP-JQe?XA9rYsW`iIR5q|09Ul!~[.KYv|a$]+a5NoY4@l' );
define( 'SECURE_AUTH_KEY', '2RD+pT8x%F+itM-^.`22&|6zCCk|sN?0IKFIg-c9J|17+mr1k$3#=%eP.]w]1kC7' );
define( 'LOGGED_IN_KEY', 'JD!mp~kKL~T+JxB,j;6SEI6GwRanYvo`Bbr~e&E*jyFgAI(xHP8f&22f-&>?DGY~' );
define( 'NONCE_KEY', 'OIN-MrXRkW||jF:|Gkkl teqP/l|6^Y[*gvkSb7u|5?Lck|$(2ZA|Fb*xMiN:8sQ' );
define( 'AUTH_SALT', ',l*4r3brBDUq]MdNNT| SWK0r=.rx9[1yynfSV[Z3Skz:m,hRz-AMEL((Cx(S@k/' );
define( 'SECURE_AUTH_SALT', 'ztyE?wIgS_X|,)#Y74]rDA8wI+!FXlA-,DP:)jd~Gk-J 6,Q3~ :Wz0]Yq>R-T{3' );
define( 'LOGGED_IN_SALT', '@L,VWk(sc ]F*WmF/7]N$rhC5t)Yov$R_^nSWZ%|u8_F$J4ZSiBK {oAs6J0(.|{' );
define( 'NONCE_SALT', '[bb*:}e;x=pBDDdw6v.Fmd99f_x2-!H=c/*5m]]^e=FJ:FwP;?p#S:Dpp+!@QjGv' );

$table_prefix = 'wpphpunittests_';   // Only numbers, letters, and underscores please!

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );

define( 'WPLANG', '' );
