<?php

/**
 * Determines where the GlotPress test suite lives.
 */
if ( false !== getenv( 'GP_TESTS_DIR' ) ) {
	define( 'GP_TESTS_DIR', getenv( 'GP_TESTS_DIR' ) );
} else {
	define( 'GP_TESTS_DIR', dirname( dirname( dirname( __DIR__ ) ) ) . '/glotpress/tests/phpunit' );
}

if ( ! file_exists( GP_TESTS_DIR . '/bootstrap.php' ) ) {
	die( "GlotPress test suite could not be found.\n" );
}

require_once getenv( 'WP_TESTS_DIR' ) . '/includes/functions.php';

/**
 * Load GlotPress and the plugin.
 */
function _bootstrap_plugins() {
	require GP_TESTS_DIR . '/includes/loader.php';

	require dirname( dirname( __DIR__ ) ) . '/gp-translation-propagation.php';
}
tests_add_filter( 'muplugins_loaded', '_bootstrap_plugins' );

global $wp_tests_options;
$wp_tests_options['permalink_structure'] = '/%postname%';

require getenv( 'WP_TESTS_DIR' ) . '/includes/bootstrap.php';

require GP_TESTS_DIR . '/lib/testcase.php';
require GP_TESTS_DIR . '/lib/testcase-route.php';
