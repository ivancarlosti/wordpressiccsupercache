<?php
/**
 * Standalone page cache engine.
 *
 * This file is loaded by `wp-content/advanced-cache.php` or by the generated
 * `wp-content/mu-plugins/icc-gg-page-cache.php` loader, i.e. before WordPress
 * finished loading. It therefore depends on nothing but PHP itself during the
 * early phase and only uses WordPress functions after checking that they exist.
 *
 * Do not edit the copy installed inside `wp-content/icc-gg-page-cache/`, it is
 * overwritten every time the settings are saved.
 *
 * @package   ICC_GG_WordPress_Super_Cache
 * @category  General
 * @author    Ivan Carlos
 * @copyright 2007-2026 Ivan Carlos Consultoria
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the engine version.
 *
 * @return string
 */
function icc_gg_page_cache_engine_version()
{
	return '1.0.0';
}

/**
 * Returns the directory holding `config.php` and `engine.php`.
 *
 * @return string
 */
function icc_gg_page_cache_config_dir()
{
	if ( defined( 'ICC_GG_PAGE_CACHE_DIR' ) && ICC_GG_PAGE_CACHE_DIR ) {
		return rtrim( (string) ICC_GG_PAGE_CACHE_DIR, '/\\' );
	}

	if ( defined( 'WP_CONTENT_DIR' ) ) {
		return rtrim( WP_CONTENT_DIR, '/\\' ) . '/icc-gg-page-cache';
	}

	return '';
}

/**
 * Loads (once) the engine configuration.
 *
 * @param string|null $config_dir Optional directory holding `config.php`.
 * @return array
 */
function icc_gg_page_cache_config( $config_dir = null )
{
	static $config = null;

	if ( null !== $config ) {
		return $config;
	}

	$defaults = [
		'enabled' => false,
		'cache_dir' => '',
		'timeout' => 0,
		'max_age' => 0,
		'browser_cache' => false,
		'gzip' => false,
		'debug_header' => true,
		'query_strings' => false,
		'mobile' => false,
		'exclude_urls' => [],
		'exclude_agents' => [],
		'exclude_cookies' => [],
	];

	$directory = null === $config_dir ? icc_gg_page_cache_config_dir() : rtrim( (string) $config_dir, '/\\' );

	if ( '' === $directory ) {
		$config = $defaults;

		return $config;
	}

	$file = $directory . '/config.php';

	if ( ! is_readable( $file ) ) {
		$config = $defaults;

		return $config;
	}

	$loaded = include $file;

	$config = is_array( $loaded ) ? array_merge( $defaults, $loaded ) : $defaults;

	return $config;
}

/**
 * Returns the current request host, sanitized for use in a path.
 *
 * @return string
 */
function icc_gg_page_cache_host()
{
	$host = '';

	if ( isset( $_SERVER['HTTP_HOST'] ) ) {
		$host = (string) $_SERVER['HTTP_HOST'];
	} elseif ( isset( $_SERVER['SERVER_NAME'] ) ) {
		$host = (string) $_SERVER['SERVER_NAME'];
	}

	$host = strtolower( preg_replace( '/[^A-Za-z0-9\.\-_:\[\]]/', '', $host ) );
	$host = trim( $host, '.' );

	return '' === $host ? 'localhost' : $host;
}

/**
 * Returns the request path without the query string.
 *
 * @return string
 */
function icc_gg_page_cache_request_path()
{
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';

	$path = parse_url( $uri, PHP_URL_PATH );

	if ( ! is_string( $path ) || '' === $path ) {
		return '/';
	}

	return $path;
}

/**
 * Turns a request path into a safe relative directory path.
 *
 * @param string $path Request path.
 * @return string Sanitized path without a trailing slash, empty for the root.
 */
function icc_gg_page_cache_sanitize_path( $path )
{
	$path = rawurldecode( (string) $path );

	$segments = preg_split( '#[/\\\\]+#', $path );
	$safe = [];

	if ( is_array( $segments ) ) {
		foreach ( $segments as $segment ) {
			$segment = trim( $segment );

			if ( '' === $segment || '.' === $segment || '..' === $segment ) {
				continue;
			}

			$segment = preg_replace( '/[^A-Za-z0-9_\-\.~]/', '-', $segment );

			if ( '' === $segment ) {
				continue;
			}

			$safe[] = substr( $segment, 0, 100 );
		}
	}

	return $safe ? '/' . implode( '/', $safe ) : '';
}

/**
 * Whether the current request comes from a mobile device.
 *
 * @return bool
 */
function icc_gg_page_cache_is_mobile()
{
	$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';

	if ( '' === $agent ) {
		return false;
	}

	return (bool) preg_match(
		'/(Android|webOS|iPhone|iPad|iPod|BlackBerry|Opera Mini|IEMobile|WPDesktop|Windows Phone|Mobile Safari)/i',
		$agent
	);
}

/**
 * Builds the absolute path of the cache file for a request or URL.
 *
 * @param array       $config Engine configuration.
 * @param string|null $url    Optional URL; defaults to the current request.
 * @return string Empty string when the URL is not cacheable.
 */
function icc_gg_page_cache_file( $config, $url = null )
{
	$base = isset( $config['cache_dir'] ) ? rtrim( (string) $config['cache_dir'], '/\\' ) : '';

	if ( '' === $base ) {
		return '';
	}

	$mobile = false;
	$query = '';

	if ( null === $url ) {
		$host = icc_gg_page_cache_host();
		$path = icc_gg_page_cache_request_path();
		$query = isset( $_SERVER['QUERY_STRING'] ) ? (string) $_SERVER['QUERY_STRING'] : '';
		$mobile = ! empty( $config['mobile'] ) && icc_gg_page_cache_is_mobile();
	} else {
		$parts = parse_url( (string) $url );

		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}

		$host = strtolower( $parts['host'] );
		$path = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		$query = isset( $parts['query'] ) ? (string) $parts['query'] : '';
	}

	if ( '' === $host ) {
		return '';
	}

	$sanitized = icc_gg_page_cache_sanitize_path( $path );
	$directory = $base . '/' . $host . ( $mobile ? '-mobile' : '' ) . $sanitized;

	if ( '' !== $query ) {
		if ( empty( $config['query_strings'] ) ) {
			return '';
		}

		$directory = rtrim( $directory, '/' ) . '/q-' . substr( md5( $query ), 0, 12 );
	}

	return rtrim( $directory, '/' ) . '/index.html';
}

/**
 * Whether a URL (path plus query string) matches one of the excluded patterns.
 *
 * Patterns support `*` wildcards and a `regex:` prefix for raw regular
 * expressions.
 *
 * @param string   $url      Path and query string to test.
 * @param string[] $patterns Exclusion patterns.
 * @return bool
 */
function icc_gg_page_cache_is_excluded_url( $url, $patterns )
{
	if ( ! is_array( $patterns ) ) {
		return false;
	}

	$url = (string) $url;

	foreach ( $patterns as $pattern ) {
		$pattern = trim( (string) $pattern );

		if ( '' === $pattern ) {
			continue;
		}

		if ( 0 === strpos( $pattern, 'regex:' ) ) {
			$regex = trim( substr( $pattern, 6 ) );

			if ( '' === $regex ) {
				continue;
			}

			$result = @preg_match( $regex, $url );

			if ( is_int( $result ) && 1 === $result ) {
				return true;
			}

			continue;
		}

		$regex = '#^' . str_replace( '\*', '.*', preg_quote( $pattern, '#' ) ) . '#i';

		if ( preg_match( $regex, $url ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Cookies that always prevent a request from being cached.
 *
 * @return string[]
 */
function icc_gg_page_cache_blocking_cookies()
{
	return [
		'comment_author',
		'wordpress_logged_in',
		'wordpress_sec',
		'wp-postpass',
		'wp_woocommerce_session',
		'woocommerce_items_in_cart',
		'woocommerce_cart_hash',
		'edd_items_in_cart',
		'PHPSESSID',
		'comment_author_email',
	];
}

/**
 * Early cacheability check used before WordPress is loaded.
 *
 * @param array $config Engine configuration.
 * @return bool
 */
function icc_gg_page_cache_request_is_cacheable( $config )
{
	if ( empty( $config['enabled'] ) ) {
		return false;
	}

	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';

	if ( 'GET' !== $method && 'HEAD' !== $method ) {
		return false;
	}

	$query = isset( $_SERVER['QUERY_STRING'] ) ? (string) $_SERVER['QUERY_STRING'] : '';
	$path = icc_gg_page_cache_request_path();

	if ( '' !== $query && empty( $config['query_strings'] ) ) {
		return false;
	}

	if ( preg_match( '#^/(wp-admin|wp-login\.php|wp-json|wp-cron\.php|xmlrpc\.php|wp-comments-post\.php|wp-signup\.php|wp-activate\.php|wp-trackback\.php|feed|comments/feed)(/|$|\?)#i', $path ) ) {
		return false;
	}

	$cookies = isset( $_COOKIE ) && is_array( $_COOKIE ) ? $_COOKIE : [];

	foreach ( array_keys( $cookies ) as $name ) {
		foreach ( icc_gg_page_cache_blocking_cookies() as $needle ) {
			if ( false !== stripos( (string) $name, $needle ) ) {
				return false;
			}
		}
	}

	if ( ! empty( $config['exclude_cookies'] ) && is_array( $config['exclude_cookies'] ) ) {
		foreach ( $config['exclude_cookies'] as $name ) {
			$name = trim( (string) $name );

			if ( '' !== $name && isset( $cookies[ $name ] ) ) {
				return false;
			}
		}
	}

	if ( ! empty( $config['exclude_agents'] ) && is_array( $config['exclude_agents'] ) ) {
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';

		foreach ( $config['exclude_agents'] as $needle ) {
			$needle = trim( (string) $needle );

			if ( '' !== $needle && false !== stripos( $agent, $needle ) ) {
				return false;
			}
		}
	}

	$target = '' === $query ? $path : $path . '?' . $query;

	if ( ! empty( $config['exclude_urls'] ) && icc_gg_page_cache_is_excluded_url( $target, $config['exclude_urls'] ) ) {
		return false;
	}

	return true;
}

/**
 * Whether a cache file is still fresh.
 *
 * @param string $file   Cache file.
 * @param array  $config Engine configuration.
 * @return bool
 */
function icc_gg_page_cache_file_is_fresh( $file, $config )
{
	$timeout = isset( $config['timeout'] ) ? (int) $config['timeout'] : 0;

	if ( $timeout <= 0 ) {
		return true;
	}

	$mtime = @filemtime( $file );

	if ( false === $mtime ) {
		return false;
	}

	return ( $mtime + $timeout ) >= time();
}

/**
 * Serves a cached page when available.
 *
 * @param array $config Engine configuration.
 * @return bool False when nothing could be served.
 */
function icc_gg_page_cache_serve( $config )
{
	$file = icc_gg_page_cache_file( $config );

	if ( '' === $file ) {
		return false;
	}

	$accepts_gzip = isset( $_SERVER['HTTP_ACCEPT_ENCODING'] )
		&& false !== stripos( (string) $_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip' );

	$variants = [];

	if ( $accepts_gzip ) {
		$variants['gz'] = $file . '.gz';
	}

	$variants['html'] = $file;

	$source = '';
	$encoding = '';

	foreach ( $variants as $variant => $candidate ) {
		if ( ! file_exists( $candidate ) ) {
			continue;
		}

		if ( ! icc_gg_page_cache_file_is_fresh( $candidate, $config ) ) {
			@unlink( $candidate );
			continue;
		}

		$source = $candidate;
		$encoding = 'gz' === $variant ? 'gzip' : '';
		break;
	}

	if ( '' === $source ) {
		return false;
	}

	$mtime = @filemtime( $source );
	$size = @filesize( $source );

	if ( false === $mtime || false === $size ) {
		return false;
	}

	$modified = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
	$last_modified = gmdate( 'D, d M Y H:i:s', $mtime ) . ' GMT';

	if ( '' !== $modified && strtotime( $modified ) >= $mtime && ! headers_sent() ) {
		header( 'Last-Modified: ' . $last_modified, true, 304 );
		exit;
	}

	if ( ! headers_sent() ) {
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'Last-Modified: ' . $last_modified );
		header( 'Vary: Accept-Encoding', false );

		$zlib = (bool) ini_get( 'zlib.output_compression' );

		if ( '' !== $encoding ) {
			// The body is already compressed: avoid a second encoding layer.
			@ini_set( 'zlib.output_compression', '0' );
			$zlib = false;
			header( 'Content-Encoding: gzip', true );
		}

		if ( ! empty( $config['browser_cache'] ) && ! empty( $config['max_age'] ) ) {
			$max_age = (int) $config['max_age'];
			header( 'Cache-Control: public, max-age=' . $max_age );
			header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $max_age ) . ' GMT' );
		} else {
			header( 'Cache-Control: public, max-age=0, must-revalidate' );
		}

		if ( ! empty( $config['debug_header'] ) ) {
			header( 'X-ICC-Page-Cache: HIT' );
		}

		if ( $zlib ) {
			// Let PHP compute the length of the compressed body.
			header( 'X-ICC-Page-Cache-Length: ' . $size, true, 200 );
		} else {
			header( 'Content-Length: ' . $size, true, 200 );
		}

		if ( ! empty( $config['gzip'] ) ) {
			header( 'X-ICC-Page-Cache-Gzip: ' . ( '' === $encoding ? 'no' : 'yes' ) );
		}
	}

	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'HEAD' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) {
		exit;
	}

	readfile( $source );

	exit;
}

/**
 * Output buffer callback that stores the generated page.
 *
 * @param string $buffer Buffered output.
 * @return string Unmodified buffer.
 */
function icc_gg_page_cache_ob_callback( $buffer )
{
	if ( is_string( $buffer ) && '' !== $buffer ) {
		icc_gg_page_cache_store( $buffer );
	}

	return $buffer;
}

/**
 * Writes a page to the cache directory.
 *
 * @param string $buffer Generated HTML.
 * @return void
 */
function icc_gg_page_cache_store( $buffer )
{
	$config = icc_gg_page_cache_config();

	if ( empty( $config['enabled'] ) || headers_sent() ) {
		return;
	}

	if ( ! is_string( $buffer ) || strlen( $buffer ) < 255 ) {
		return;
	}

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return;
	}

	if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
		return;
	}

	if ( function_exists( 'is_admin' ) && ( is_admin() || is_network_admin() ) ) {
		return;
	}

	if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
		return;
	}

	if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
		return;
	}

	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return;
	}

	if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
		return;
	}

	if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
		return;
	}

	$skip_conditionals = [
		'is_404',
		'is_search',
		'is_feed',
		'is_preview',
		'is_trackback',
		'is_robots',
		'is_favicon',
	];

	foreach ( $skip_conditionals as $conditional ) {
		if ( function_exists( $conditional ) && call_user_func( $conditional ) ) {
			return;
		}
	}

	if ( ! icc_gg_page_cache_request_is_cacheable( $config ) ) {
		return;
	}

	$status = function_exists( 'http_response_code' ) ? (int) http_response_code() : 200;

	// The CLI SAPI and some SAPIs report 0 when no status was set explicitly.
	if ( 0 === $status ) {
		$status = 200;
	}

	if ( 200 !== $status ) {
		return;
	}

	foreach ( headers_list() as $header ) {
		if ( 0 === stripos( $header, 'Set-Cookie:' ) ) {
			return;
		}

		if ( 0 === stripos( $header, 'Content-Encoding:' ) ) {
			return;
		}

		if ( 0 === stripos( $header, 'Content-Type:' ) && false === stripos( $header, 'text/html' ) ) {
			return;
		}

		if ( 0 === stripos( $header, 'Cache-Control:' ) && false !== stripos( $header, 'no-store' ) ) {
			return;
		}
	}

	if ( false !== strpos( $buffer, '<!-- icc-gg-page-cache:no -->' ) ) {
		return;
	}

	$file = icc_gg_page_cache_file( $config );

	if ( '' === $file ) {
		return;
	}

	$directory = dirname( $file );

	if ( ! is_dir( $directory ) && ! @mkdir( $directory, 0755, true ) && ! is_dir( $directory ) ) {
		return;
	}

	$temporary = $file . '.' . uniqid( '', true ) . '.tmp';

	if ( false === @file_put_contents( $temporary, $buffer ) ) {
		return;
	}

	if ( ! @rename( $temporary, $file ) ) {
		@unlink( $temporary );

		return;
	}

	if ( empty( $config['gzip'] ) || ! function_exists( 'gzencode' ) ) {
		return;
	}

	$compressed = @gzencode( $buffer, 6 );

	if ( false === $compressed ) {
		return;
	}

	$temporary_gz = $file . '.gz.' . uniqid( '', true ) . '.tmp';

	if ( false === @file_put_contents( $temporary_gz, $compressed ) ) {
		return;
	}

	if ( ! @rename( $temporary_gz, $file . '.gz' ) ) {
		@unlink( $temporary_gz );
	}
}

/**
 * Boots the page cache for the current request.
 *
 * Called by `advanced-cache.php` or by the must-use plugin loader.
 *
 * @return void
 */
function icc_gg_page_cache_bootstrap()
{
	$config = icc_gg_page_cache_config();

	if ( empty( $config['enabled'] ) ) {
		return;
	}

	if ( ! icc_gg_page_cache_request_is_cacheable( $config ) ) {
		return;
	}

	if ( icc_gg_page_cache_serve( $config ) ) {
		return;
	}

	ob_start( 'icc_gg_page_cache_ob_callback' );
}
