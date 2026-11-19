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
	/**
	 * Glob metacharacters understood by Redis' string match implementation.
	 *
	 * @var string[]
	 */
	const GLOB_CHARACTERS = [ '\\', '*', '?', '[', ']', '{', '}', '(', ')', '|', '@', '!', '+' ];

	/**
	 * Removes characters that would turn the prefix into a Redis glob pattern.
	 *
	 * A prefix is interpolated into `SCAN MATCH` patterns by the selective
	 * flush routines. Control characters and whitespace are also removed so the
	 * value is safe to display and copy into `wp-config.php`.
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

		$clean = preg_replace( '/[\x00-\x20\x7F]/', '', $prefix );

		if ( null !== $clean ) {
			$prefix = $clean;
		}

		return str_replace( self::GLOB_CHARACTERS, '', $prefix );
	}

	/**
	 * Builds a deterministic, site-specific prefix.
	 *
	 * Used when neither `WP_REDIS_PREFIX` nor `WP_CACHE_KEY_SALT` is defined.
	 * The database name and installation path are stable for a site and differ
	 * between independent installations that share the same Redis database.
	 * Multisite blogs are separated by the blog prefix appended to every key.
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

		return 'icc' . substr( md5( implode( '|', $seed ) ), 0, 12 ) . '-';
	}

	/**
	 * Returns the configured prefix from any supported source.
	 *
	 * Mirrors the constant/environment mapping performed by the drop-in so the
	 * Predis client and the settings screen agree even before the drop-in is
	 * installed.
	 *
	 * @return string
	 */
	protected static function configured()
	{
		if ( defined( 'WP_REDIS_PREFIX' ) ) {
			return self::sanitize( WP_REDIS_PREFIX );
		}

		$env_prefix = getenv( 'WP_REDIS_PREFIX' );

		if ( false !== $env_prefix && '' !== trim( (string) $env_prefix ) ) {
			return self::sanitize( $env_prefix );
		}

		if ( defined( 'WP_CACHE_KEY_SALT' ) ) {
			return self::sanitize( WP_CACHE_KEY_SALT );
		}

		if ( isset( $_SERVER['cw_allowed_ip'] ) ) {
			$cloudways_prefix = getenv( 'HTTP_X_APP_USER' );

			if ( false !== $cloudways_prefix && '' !== trim( (string) $cloudways_prefix ) ) {
				return self::sanitize( $cloudways_prefix );
			}
		}

		return '';
	}

	/**
	 * Returns the effective, sanitized key prefix.
	 *
	 * @return string
	 */
	public static function resolve()
	{
		$configured = self::configured();

		if ( '' === $configured ) {
			return self::generate();
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
