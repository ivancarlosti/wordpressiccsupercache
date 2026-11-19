<?php
/**
 * Standalone checks for the Redis key prefix rules.
 *
 * Usage: php tests/key-prefix.php
 *
 * No WordPress or Redis connection is needed: the script covers the allow-list
 * that keeps configured prefixes out of Redis glob patterns, the deterministic
 * generated prefix, the reported host-injected salts, and the parity between
 * the plugin helper and the object-cache drop-in.
 *
 * @package ICC_GG_WordPress_Super_Cache
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'DB_NAME', 'demo' );

$GLOBALS['table_prefix'] = 'wp_';

// The generated-prefix assertions must not inherit a prefix from the shell.
putenv( 'WP_REDIS_PREFIX' );
unset( $_SERVER['cw_allowed_ip'] );

require dirname( __DIR__ ) . '/includes/class-key-prefix.php';

$prefix_class = 'ICC_GG_WordPress_Super_Cache_Key_Prefix';
$failures     = 0;

/**
 * Records one assertion.
 *
 * @param string $label     Description of the expectation.
 * @param bool   $condition Result of the expectation.
 * @param string $detail    Extra context shown on failure.
 * @return void
 */
function icc_assert( $label, $condition, $detail = '' )
{
	global $failures;

	if ( $condition ) {
		echo "ok - {$label}\n";

		return;
	}

	++$failures;

	echo "not ok - {$label}" . ( '' !== $detail ? " ({$detail})" : '' ) . "\n";
}

/**
 * Asserts that a sanitized prefix only contains letters and digits.
 *
 * @param string $label Description of the case.
 * @param string $clean Sanitized prefix.
 * @return void
 */
function icc_assert_safe( $label, $clean )
{
	icc_assert(
		$label,
		1 === preg_match( '/^[A-Za-z0-9]*$/', $clean ),
		var_export( $clean, true )
	);
}

/*
 * The two salts reported by sites whose host injects a random prefix. They use
 * the full `wp_generate_password( 40, true, true )` charset, so the sanitizer
 * must reduce them to letters and digits.
 */
$reported = [
	'<$iIZ^o2xAd4-a>Ub3Vp4mYL0%oMZ1Xlo6dOCzJ:azzz_y>7GkN~Ms:D5' => 'iIZo2xAd4aUb3Vp4mYL0oMZ1Xlo6dOCzJazzzy7GkNMsD5',
	'bvCxv3~uT&TM~P1Mp837OS6=9cyC3M<yD`r;ZK0Ac:X$_e$O~#3' => 'bvCxv3uTTMP1Mp837OS69cyC3MyDrZK0AcXeO3',
];

foreach ( $reported as $input => $expected ) {
	$clean = $prefix_class::sanitize( $input );

	icc_assert( 'reported host salt is reduced to the expected prefix', $expected === $clean, $clean );
	icc_assert_safe( 'reported host salt leaves no punctuation', $clean );
	icc_assert( 'reported host salt leaves no glob metacharacters', ! $prefix_class::has_glob( $clean ) );
}

$cases = [
	[ 'demo*{site}', 'demosite' ],
	[ 'demo?[x]', 'demox' ],
	[ 'host:8080', 'host8080' ],
	[ '  spaced  ', 'spaced' ],
	[ 'a-b_c.d/e', 'abcde' ],
	[ 'back\\slash', 'backslash' ],
	[ '<>##', '' ],
	[ 'päfix', 'pfix' ],
	[ 'prefix_1', 'prefix1' ],
	[ 'ABC123', 'ABC123' ],
	[ '', '' ],
	[ null, '' ],
	[ false, '' ],
	[ 123, '123' ],
];

foreach ( $cases as $case ) {
	list( $input, $expected ) = $case;

	$clean = $prefix_class::sanitize( $input );

	icc_assert( 'sanitize(' . var_export( $input, true ) . ')', $expected === $clean, $clean );
	icc_assert_safe( 'sanitized value for ' . var_export( $input, true ) . ' is alphanumeric', $clean );
}

icc_assert( 'is_safe() accepts letters and digits', $prefix_class::is_safe( 'abc123' ) );
icc_assert( 'is_safe() rejects punctuation', ! $prefix_class::is_safe( 'abc-123' ) );
icc_assert( 'has_glob() detects a wildcard', $prefix_class::has_glob( 'site*' ) );
icc_assert( 'has_glob() detects brackets', $prefix_class::has_glob( 'site[1]' ) );
icc_assert( 'has_glob() accepts a clean prefix', ! $prefix_class::has_glob( 'site123' ) );

$generated = $prefix_class::generate();

icc_assert( 'generate() returns the documented shape', 1 === preg_match( '/^icc[0-9a-f]{12}$/', $generated ), $generated );
icc_assert( 'generate() returns an alphanumeric prefix', $prefix_class::is_safe( $generated ) );
icc_assert( 'generate() is stable for the same site', $generated === $prefix_class::generate() );

icc_assert( 'resolve() falls back to the generated prefix', $generated === $prefix_class::resolve(), $prefix_class::resolve() );
icc_assert( 'source() reports a generated prefix', 'generated' === $prefix_class::source(), $prefix_class::source() );
icc_assert( 'source_detail() reports a generated prefix', 'generated' === $prefix_class::source_detail(), $prefix_class::source_detail() );
icc_assert( 'source_label() reports a generated prefix', 'generated' === $prefix_class::source_label(), $prefix_class::source_label() );

/*
 * Drop-in parity. The drop-in keeps a standalone copy of the rules because it
 * runs before plugins load; both implementations must agree.
 */
require dirname( __DIR__ ) . '/includes/dropins/object-cache.php';

$reflection = new ReflectionClass( 'WP_Object_Cache' );
$cache      = $reflection->newInstanceWithoutConstructor();

$generate_method = $reflection->getMethod( 'generate_key_prefix' );
$generate_method->setAccessible( true );

foreach ( $reported as $input => $expected ) {
	icc_assert(
		'drop-in sanitizer matches the plugin helper',
		$expected === $cache->sanitize_key_prefix( $input ),
		$cache->sanitize_key_prefix( $input )
	);
	icc_assert_safe( 'drop-in sanitizer leaves no punctuation', $cache->sanitize_key_prefix( $input ) );
}

$dropin_generated = $generate_method->invoke( $cache );

icc_assert( 'drop-in generated prefix matches the plugin helper', $generated === $dropin_generated, $dropin_generated );
icc_assert( 'drop-in generated prefix is alphanumeric', 1 === preg_match( '/^icc[0-9a-f]{12}$/', $dropin_generated ), $dropin_generated );

/*
 * Configured sources are sanitized on read instead of being trusted.
 */
define( 'WP_REDIS_PREFIX', 'demo*{site}:x' );

icc_assert( 'resolve() sanitizes a configured constant', 'demositex' === $prefix_class::resolve(), $prefix_class::resolve() );
icc_assert( 'source_detail() reports the constant', 'constant' === $prefix_class::source_detail(), $prefix_class::source_detail() );
icc_assert( 'source_label() names the constant', 'WP_REDIS_PREFIX' === $prefix_class::source_label(), $prefix_class::source_label() );
icc_assert( 'source() reports a defined prefix', 'defined' === $prefix_class::source(), $prefix_class::source() );

if ( $failures > 0 ) {
	echo "\n{$failures} assertion(s) failed.\n";
	exit( 1 );
}

echo "\nAll key prefix assertions passed.\n";
