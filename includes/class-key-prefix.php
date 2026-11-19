<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Redis key prefix helpers.
 *
 * Resolves the effective key prefix shared by the object cache drop-in, the
 * Predis client and the settings screen. The drop-in is copied to
 * `wp-content/object-cache.php` and runs before plugins are loaded, so it keeps
 * an identical, standalone copy of these rules; keep both implementations in
 * sync.
 *
 * @package   ICC_GG_WordPress_Super_Cache
 * @category  General
 * @author    Ivan Carlos
 * @copyright 2007-2026 Ivan Carlos Consultoria
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0-or-later
 */

/**
 * Key prefix helper definition.
 */
class ICC_GG_WordPress_Super_Cache_Key_Prefix
{
	/**#@+
	 * Prefix origins reported by `source_detail()`.
	 *
	 * @var string
	 */
	const SOURCE_GENERATED      = 'generated';
	const SOURCE_CONSTANT       = 'constant';
	const SOURCE_ENVIRONMENT    = 'environment';
	const SOURCE_CACHE_KEY_SALT = 'wp_cache_key_salt';
	const SOURCE_CLOUDWAYS      = 'cloudways';
	/**#@-*/

	/**
	 * Glob metacharacters understood by Redis' string match implementation.
	 *
	 * Kept as public documentation for hosts that build their own patterns.
	 * `sanitize()` no longer needs a blacklist because it only keeps letters
	 * and digits.
	 *
	 * @var string[]
	 */
	const GLOB_CHARACTERS = [ '\\', '*', '?', '[', ']', '{', '}', '(', ')', '|', '@', '!', '+' ];

	/**
	 * Pattern that matches every character not allowed in a key prefix.
	 *
	 * @var string
	 */
	const ALLOWED_PATTERN = '/[^A-Za-z0-9]/';

	/**
	 * Keeps only letters and digits in a key prefix.
	 *
	 * A prefix is interpolated into Redis keys and `SCAN MATCH` patterns.
	 * Punctuation (glob metacharacters, whitespace, `:`, `-`, `_`, ...) can
	 * widen a pattern or break key inspection, so only `[A-Za-z0-9]` is kept.
	 * Hosting providers that inject random salts therefore can no longer put
	 * special characters into Redis keys.
	 *
	 * @param mixed $prefix Prefix configured by the site.
	 * @return string
	 */
	public static function sanitize( $prefix )
	{
		$prefix = (string) $prefix;

		if ( '' === $prefix ) {
			return '';
		}

		$clean = preg_replace( self::ALLOWED_PATTERN, '', $prefix );

		return null === $clean ? '' : $clean;
	}

	/**
	 * Tells whether a prefix already contains only letters and digits.
	 *
	 * @param mixed $prefix Prefix to check.
	 * @return bool
	 */
	public static function is_safe( $prefix )
	{
		$prefix = (string) $prefix;

		return '' === $prefix || 1 === preg_match( '/^[A-Za-z0-9]+$/', $prefix );
	}

	/**
	 * Tells whether a prefix contains Redis glob metacharacters.
	 *
	 * @param mixed $prefix Prefix to check.
	 * @return bool
	 */
	public static function has_glob( $prefix )
	{
		$prefix = (string) $prefix;

		return '' !== $prefix && false !== strpbrk( $prefix, implode( '', self::GLOB_CHARACTERS ) );
	}

	/**
	 * Builds a deterministic, site-specific prefix.
	 *
	 * Used when neither `WP_REDIS_PREFIX` nor `WP_CACHE_KEY_SALT` is defined.
	 * The database name and installation path are stable for a site and differ
	 * between independent installations that share the same Redis database.
	 * Multisite blogs are separated by the blog prefix appended to every key.
	 *
	 * The result only contains letters and digits so it always passes
	 * `sanitize()` unchanged.
	 *
	 * @return string
	 */
	public static function generate()
	{
		global $table_prefix;

		$seed = [
			defined( 'DB_NAME' ) ? DB_NAME : '',
			defined( 'ABSPATH' ) ? ABSPATH : '',
			isset( $table_prefix ) ? $table_prefix : '',
		];

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			$seed[] = 'multisite';
		}

		return 'icc' . substr( md5( implode( '|', $seed ) ), 0, 12 );
	}

	/**
	 * Detects the configured prefix and where it came from.
	 *
	 * Mirrors the constant/environment mapping performed by the drop-in so the
	 * Predis client and the settings screen agree even before the drop-in is
	 * installed. The drop-in records the origin in
	 * `ICC_GG_SUPER_CACHE_PREFIX_ORIGIN`, which keeps the diagnostics honest
	 * when the value was injected by the host through the environment or
	 * `WP_CACHE_KEY_SALT`.
	 *
	 * @return array{0: string, 1: string} Prefix and origin.
	 */
	protected static function detect()
	{
		$origin = defined( 'ICC_GG_SUPER_CACHE_PREFIX_ORIGIN' )
			? (string) ICC_GG_SUPER_CACHE_PREFIX_ORIGIN
			: '';
		$source = ( '' !== $origin && self::SOURCE_GENERATED !== $origin ) ? $origin : null;

		if ( defined( 'WP_REDIS_PREFIX' ) ) {
			return [
				(string) WP_REDIS_PREFIX,
				null !== $source ? $source : self::SOURCE_CONSTANT,
			];
		}

		$env_prefix = getenv( 'WP_REDIS_PREFIX' );

		if ( false !== $env_prefix && '' !== trim( (string) $env_prefix ) ) {
			return [
				(string) $env_prefix,
				null !== $source ? $source : self::SOURCE_ENVIRONMENT,
			];
		}

		if ( defined( 'WP_CACHE_KEY_SALT' ) ) {
			return [
				(string) WP_CACHE_KEY_SALT,
				null !== $source ? $source : self::SOURCE_CACHE_KEY_SALT,
			];
		}

		if ( isset( $_SERVER['cw_allowed_ip'] ) ) {
			$cloudways_prefix = getenv( 'HTTP_X_APP_USER' );

			if ( false !== $cloudways_prefix && '' !== trim( (string) $cloudways_prefix ) ) {
				return [
					(string) $cloudways_prefix,
					null !== $source ? $source : self::SOURCE_CLOUDWAYS,
				];
			}
		}

		return [ '', self::SOURCE_GENERATED ];
	}

	/**
	 * Returns the configured prefix from any supported source.
	 *
	 * @return string
	 */
	protected static function configured()
	{
		$detected = self::detect();

		return self::sanitize( $detected[0] );
	}

	/**
	 * Returns the effective, sanitized key prefix.
	 *
	 * @return string
	 */
	public static function resolve()
	{
		$configured = self::sanitize( self::configured() );

		if ( '' === $configured ) {
			return self::sanitize( self::generate() );
		}

		return $configured;
	}

	/**
	 * Tells whether the effective prefix came from configuration or was generated.
	 *
	 * @return string Either `defined` or `generated`.
	 */
	public static function source()
	{
		return '' === self::configured() ? 'generated' : 'defined';
	}

	/**
	 * Returns the origin of the configured prefix.
	 *
	 * @return string One of the `SOURCE_*` constants.
	 */
	public static function source_detail()
	{
		$detected = self::detect();

		return $detected[1];
	}

	/**
	 * Returns a human-readable label for a prefix origin.
	 *
	 * Technical names are intentionally not translated: they identify a
	 * constant or environment variable in the site configuration.
	 *
	 * @param string|null $source Origin returned by `source_detail()`.
	 * @return string
	 */
	public static function source_label( $source = null )
	{
		if ( null === $source ) {
			$source = self::source_detail();
		}

		switch ( $source ) {
			case self::SOURCE_CONSTANT:
				return 'WP_REDIS_PREFIX';

			case self::SOURCE_ENVIRONMENT:
				return 'WP_REDIS_PREFIX environment variable';

			case self::SOURCE_CACHE_KEY_SALT:
				return 'WP_CACHE_KEY_SALT';

			case self::SOURCE_CLOUDWAYS:
				return 'Cloudways HTTP_X_APP_USER';
		}

		return 'generated';
	}

	/**
	 * Tells whether `wp_cache_flush()` should only delete this site's keys.
	 *
	 * Precedence:
	 * 1. `WP_REDIS_SELECTIVE_FLUSH` in wp-config.php or the environment.
	 * 2. `ICC_GG_SUPER_CACHE_SELECTIVE_FLUSH`, defined by the plugin from the
	 *    Redis tab setting.
	 * 3. A filter, for hosts that prefer to control the value in code.
	 * 4. `true`, the safe default on shared Redis databases.
	 *
	 * @return bool
	 */
	public static function selective_flush_enabled()
	{
		if ( defined( 'WP_REDIS_SELECTIVE_FLUSH' ) ) {
			return self::to_bool( WP_REDIS_SELECTIVE_FLUSH );
		}

		if ( defined( 'ICC_GG_SUPER_CACHE_SELECTIVE_FLUSH' ) ) {
			return self::to_bool( ICC_GG_SUPER_CACHE_SELECTIVE_FLUSH );
		}

		if ( function_exists( 'apply_filters' ) ) {
			$value = apply_filters( 'icc_gg_wordpress_super_cache_selective_flush', null );

			if ( null !== $value ) {
				return self::to_bool( $value );
			}
		}

		return true;
	}

	/**
	 * Normalizes a boolean coming from a constant, environment variable or option.
	 *
	 * @param mixed $value Value to normalize.
	 * @return bool
	 */
	public static function to_bool( $value )
	{
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value ) );

			return ! in_array( $value, [ '', '0', 'false', 'no', 'off', 'disabled' ], true );
		}

		return (bool) $value;
	}

	/**
	 * Quotes a string for usage in a Redis glob pattern.
	 *
	 * @param string $string String to quote.
	 * @return string
	 */
	public static function glob_quote( $string )
	{
		/*
		 * Use a single-pass map so brackets inserted for one character are not
		 * quoted again by the next replacement.
		 */
		return strtr(
			(string) $string,
			[
				'\\' => '\\\\',
				'*' => '[*]',
				'+' => '[+]',
				'?' => '[?]',
				'!' => '[!]',
				'{' => '[{]',
				'}' => '[}]',
				'[' => '[[]',
				']' => '[]]',
				'(' => '[(]',
				')' => '[)]',
				'|' => '[|]',
				'@' => '[@]',
			]
		);
	}

	/**
	 * Escapes a value interpolated inside a single-quoted Lua string.
	 *
	 * @param string $string String to escape.
	 * @return string
	 */
	public static function escape_lua_string( $string )
	{
		return str_replace(
			[ '\\', "'", "\r", "\n" ],
			[ '\\\\', "\\'", '', '' ],
			(string) $string
		);
	}
}
