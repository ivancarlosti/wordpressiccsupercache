<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Varnish page cache controller.
 *
 * Detects a Varnish (or any `PURGE`/`BAN` capable) HTTP cache sitting in front
 * of the site and purges it, either automatically when content changes or
 * manually from the admin area.
 *
 * @package   ICC_GG_WordPress_Super_Cache
 * @category  General
 * @author    Ivan Carlos
 * @copyright 2007-2026 Ivan Carlos Consultoria
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0-or-later
 */

/**
 * Varnish cache class definition
 */
class ICC_GG_WordPress_Super_Cache_Varnish_Cache
{
	/**
	 * Option name holding the Varnish cache settings.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'icc_gg_wordpress_super_cache_varnish_cache';

	/**
	 * Singleton instance of self.
	 *
	 * @var ICC_GG_WordPress_Super_Cache_Varnish_Cache|null
	 */
	private static $instance;

	/**
	 * Resolved settings, or null when they still need to be read.
	 *
	 * @var array|null
	 */
	private $settings = null;

	/**
	 * Create (if needed) and return a singleton of self.
	 *
	 * @return ICC_GG_WordPress_Super_Cache_Varnish_Cache
	 */
	public static function instance()
	{
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct()
	{
		// The page cache registry registers the shared purge hooks.
	}

	/**
	 * Returns the default settings.
	 *
	 * @return array
	 */
	public function defaults()
	{
		return [
			'host' => '127.0.0.1',
			'port' => 6081,
			'scheme' => 'http',
			'method' => 'purge',
			'key' => '',
			'auto_purge' => true,
			'timeout' => 5,
		];
	}

	/**
	 * Returns the stored settings.
	 *
	 * @return array
	 */
	public function get_settings()
	{
		if ( ! isset( $this->settings ) ) {
			$stored = get_option( self::OPTION_KEY, [] );
			$stored = is_array( $stored ) ? $stored : [];

			$this->settings = wp_parse_args( $stored, $this->defaults() );
		}

		return $this->settings;
	}

	/**
	 * Reads a single setting.
	 *
	 * @param string $key Setting name.
	 * @return mixed
	 */
	public function get_setting( $key )
	{
		$settings = $this->get_settings();

		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	/**
	 * Forgets the cached settings, e.g. after they were updated.
	 *
	 * @return void
	 */
	public function flush_settings_cache()
	{
		$this->settings = null;
	}

	/**
	 * Whether Varnish is the active page cache backend.
	 *
	 * @return bool
	 */
	public function is_enabled()
	{
		if ( defined( 'ICC_GG_SUPER_CACHE_VARNISH_ENABLED' ) ) {
			return (bool) ICC_GG_SUPER_CACHE_VARNISH_ENABLED;
		}

		return ICC_GG_WordPress_Super_Cache_Page_Cache::instance()->is_active(
			ICC_GG_WordPress_Super_Cache_Page_Cache::VARNISH
		);
	}

	/**
	 * Whether the cache should be purged automatically when content changes.
	 *
	 * @return bool
	 */
	public function auto_purge_enabled()
	{
		if ( defined( 'ICC_GG_SUPER_CACHE_VARNISH_AUTO_PURGE' ) ) {
			return (bool) ICC_GG_SUPER_CACHE_VARNISH_AUTO_PURGE;
		}

		return (bool) $this->get_setting( 'auto_purge' );
	}

	/**
	 * Whether a setting is overridden by a constant in `wp-config.php`.
	 *
	 * @param string $key Setting name.
	 * @return bool
	 */
	public function is_overridden( $key )
	{
		switch ( $key ) {
			case 'enabled':
				return defined( 'ICC_GG_SUPER_CACHE_VARNISH_ENABLED' );

			case 'auto_purge':
				return defined( 'ICC_GG_SUPER_CACHE_VARNISH_AUTO_PURGE' );

			case 'host':
				return defined( 'ICC_GG_SUPER_CACHE_VARNISH_HOST' );

			case 'port':
				return defined( 'ICC_GG_SUPER_CACHE_VARNISH_PORT' );

			case 'key':
				return defined( 'ICC_GG_SUPER_CACHE_VARNISH_KEY' );
		}

		return false;
	}

	/**
	 * Sanitizes the submitted settings.
	 *
	 * @param array $input Raw, unslashed settings.
	 * @return array
	 */
	public function sanitize_settings( $input )
	{
		$input = is_array( $input ) ? $input : [];

		$host = isset( $input['host'] ) ? sanitize_text_field( (string) $input['host'] ) : '';
		$host = preg_replace( '#^https?://#i', '', trim( $host ) );
		$host = preg_replace( '#[/\s].*$#', '', (string) $host );

		$port = isset( $input['port'] ) ? (int) $input['port'] : 6081;

		// Accept "host:port" pasted into the host field, unless it is an IPv6 literal.
		if ( '' !== $host && 0 !== strpos( $host, '[' ) && preg_match( '/^(.+):(\d+)$/', $host, $matches ) ) {
			$host = $matches[1];

			if ( empty( $input['port'] ) ) {
				$port = (int) $matches[2];
			}
		}

		if ( '' === $host ) {
			$port = 6081;
		}

		return [
			'host' => '' === $host ? '127.0.0.1' : $host,
			'port' => min( 65535, max( 1, $port ) ),
			'scheme' => ( isset( $input['scheme'] ) && 'https' === $input['scheme'] ) ? 'https' : 'http',
			'method' => ( isset( $input['method'] ) && 'ban' === $input['method'] ) ? 'ban' : 'purge',
			'key' => isset( $input['key'] ) ? sanitize_text_field( (string) $input['key'] ) : '',
			'auto_purge' => ! empty( $input['auto_purge'] ),
			'timeout' => min( 30, max( 1, isset( $input['timeout'] ) ? (int) $input['timeout'] : 5 ) ),
		];
	}

	/**
	 * Returns the host Varnish is listening on.
	 *
	 * @return string
	 */
	public function site_host()
	{
		if ( defined( 'ICC_GG_SUPER_CACHE_VARNISH_HOST' ) && ICC_GG_SUPER_CACHE_VARNISH_HOST ) {
			return (string) ICC_GG_SUPER_CACHE_VARNISH_HOST;
		}

		$host = (string) $this->get_setting( 'host' );

		return '' === $host ? '127.0.0.1' : $host;
	}

	/**
	 * Returns the port Varnish is listening on.
	 *
	 * @return int
	 */
	public function port()
	{
		if ( defined( 'ICC_GG_SUPER_CACHE_VARNISH_PORT' ) && ICC_GG_SUPER_CACHE_VARNISH_PORT ) {
			return (int) ICC_GG_SUPER_CACHE_VARNISH_PORT;
		}

		return (int) $this->get_setting( 'port' );
	}

	/**
	 * Returns the purge key, when configured.
	 *
	 * @return string
	 */
	public function purge_key()
	{
		if ( defined( 'ICC_GG_SUPER_CACHE_VARNISH_KEY' ) ) {
			return (string) ICC_GG_SUPER_CACHE_VARNISH_KEY;
		}

		return (string) $this->get_setting( 'key' );
	}

	/**
	 * Returns the host header used for the purge request.
	 *
	 * @return string
	 */
	protected function host_header()
	{
		$home = wp_parse_url( home_url( '/' ) );

		$host = isset( $home['host'] ) ? $home['host'] : '';

		if ( isset( $home['port'] ) ) {
			$host .= ':' . $home['port'];
		}

		return $host;
	}

	/**
	 * Builds the URL the purge request is sent to.
	 *
	 * @param string $url Optional URL whose path is purged.
	 * @return string|WP_Error
	 */
	public function endpoint_url( $url = '' )
	{
		$target = '' === (string) $url ? home_url( '/' ) : (string) $url;
		$parts = wp_parse_url( $target );

		if ( ! is_array( $parts ) ) {
			return new WP_Error( 'invalid_url', __( 'The URL to purge is not valid.', 'icc-gg-wordpress-super-cache' ) );
		}

		$path = isset( $parts['path'] ) ? $parts['path'] : '/';
		$query = isset( $parts['query'] ) ? '?' . $parts['query'] : '';

		$scheme = 'https' === $this->get_setting( 'scheme' ) ? 'https' : 'http';
		$host = $this->site_host();
		$port = $this->port();

		$default_port = 'https' === $scheme ? 443 : 80;

		$endpoint = $scheme . '://' . $host;

		if ( $port && $port !== $default_port ) {
			$endpoint .= ':' . $port;
		}

		return $endpoint . $path . $query;
	}

	/**
	 * Returns the human readable status of the Varnish integration.
	 *
	 * @return string
	 */
	public function get_status()
	{
		if ( ! $this->is_enabled() ) {
			return __( 'Disabled', 'icc-gg-wordpress-super-cache' );
		}

		return $this->auto_purge_enabled()
			? __( 'Enabled (auto purge)', 'icc-gg-wordpress-super-cache' )
			: __( 'Enabled (manual purge)', 'icc-gg-wordpress-super-cache' );
	}

	/**
	 * Purges a URL from Varnish.
	 *
	 * @param string $url Optional URL; defaults to the site home page.
	 * @return true|WP_Error
	 */
	public function purge( $url = '' )
	{
		if ( ! $this->is_enabled() ) {
			return new WP_Error( 'disabled', __( 'The Varnish cache is disabled.', 'icc-gg-wordpress-super-cache' ) );
		}

		$target = '' === (string) $url ? home_url( '/' ) : (string) $url;
		$endpoint = $this->endpoint_url( $target );

		if ( is_wp_error( $endpoint ) ) {
			return $endpoint;
		}

		$method = 'ban' === $this->get_setting( 'method' ) ? 'BAN' : 'PURGE';
		$key = $this->purge_key();

		$parts = wp_parse_url( $target );
		$path = isset( $parts['path'] ) ? $parts['path'] : '/';

		$headers = [
			'Host' => $this->host_header(),
			'X-Purge-Method' => 'ban' === $this->get_setting( 'method' ) ? 'regex' : 'default',
			'X-Purge-Host' => $this->host_header(),
			'X-Purge-Path' => $path,
			'User-Agent' => 'ICC.gg Varnish Purge/1.0; ' . home_url( '/' ),
		];

		if ( '' !== $key ) {
			$headers['X-Purge-Key'] = $key;
		}

		/**
		 * Filters whether SSL certificates are verified when purging Varnish.
		 *
		 * @param bool $verify Whether to verify certificates.
		 */
		$sslverify = (bool) apply_filters( 'icc_gg_wordpress_super_cache_varnish_sslverify', true );

		$response = wp_remote_request(
			$endpoint,
			[
				'method' => $method,
				'timeout' => max( 1, (int) $this->get_setting( 'timeout' ) ),
				'headers' => $headers,
				'sslverify' => $sslverify,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 400 ) {
			return new WP_Error(
				'varnish_status',
				sprintf(
					// translators: 1: HTTP status code, 2: HTTP method.
					__( 'Varnish answered with HTTP %1$d to the %2$s request.', 'icc-gg-wordpress-super-cache' ),
					$code,
					$method
				)
			);
		}

		/**
		 * Fires after a Varnish purge request was accepted.
		 *
		 * @param string $url    Purged URL.
		 * @param string $method HTTP method used.
		 */
		do_action( 'icc_gg_wordpress_super_cache_varnish_purged', $target, $method );

		return true;
	}

	/**
	 * Inspects the response headers of the home page looking for Varnish.
	 *
	 * @return array{detected:bool,status:int,headers:array,message:string}
	 */
	public function probe()
	{
		$probe = [
			'detected' => false,
			'status' => 0,
			'headers' => [],
			'message' => '',
		];

		$url = add_query_arg( 'icc_gg_varnish_probe', time(), home_url( '/' ) );

		/**
		 * Filters whether SSL certificates are verified when probing Varnish.
		 *
		 * @param bool $verify Whether to verify certificates.
		 */
		$sslverify = (bool) apply_filters( 'icc_gg_wordpress_super_cache_varnish_sslverify', true );

		$response = wp_remote_head(
			$url,
			[
				'timeout' => max( 1, (int) $this->get_setting( 'timeout' ) ),
				'redirection' => 2,
				'sslverify' => $sslverify,
			]
		);

		if ( is_wp_error( $response ) ) {
			$probe['message'] = $response->get_error_message();

			return $probe;
		}

		$probe['status'] = (int) wp_remote_retrieve_response_code( $response );

		$interesting = [
			'x-varnish',
			'x-varnish-cache',
			'x-cache',
			'via',
			'age',
			'server',
		];

		foreach ( $interesting as $header ) {
			$value = wp_remote_retrieve_header( $response, $header );

			if ( '' !== $value && false !== $value ) {
				$probe['headers'][ $header ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
			}
		}

		if ( isset( $probe['headers']['x-varnish'] ) || isset( $probe['headers']['x-varnish-cache'] ) ) {
			$probe['detected'] = true;
		}

		if ( ! $probe['detected'] && isset( $probe['headers']['via'] ) && false !== stripos( $probe['headers']['via'], 'varnish' ) ) {
			$probe['detected'] = true;
		}

		if ( ! $probe['detected'] && isset( $probe['headers']['age'] ) && (int) $probe['headers']['age'] > 0 ) {
			$probe['detected'] = true;
		}

		$probe['message'] = $probe['detected']
			? __( 'Varnish headers were found in the response.', 'icc-gg-wordpress-super-cache' )
			: __( 'No Varnish headers were found in the response.', 'icc-gg-wordpress-super-cache' );

		return $probe;
	}

	/**
	 * Tests the connection and reports what was found.
	 *
	 * @return array
	 */
	public function test()
	{
		$probe = $this->probe();

		$purge = $this->purge();
		$purge_ok = ! is_wp_error( $purge );

		$result = [
			'ok' => $probe['detected'] || $purge_ok,
			'probe' => $probe,
			'purge' => [
				'ok' => $purge_ok,
				'message' => $purge_ok
					? __( 'The purge request was accepted.', 'icc-gg-wordpress-super-cache' )
					: $purge->get_error_message(),
			],
		];

		if ( $probe['detected'] && $purge_ok ) {
			$result['message'] = __( 'Varnish is in front of the site and answers purge requests.', 'icc-gg-wordpress-super-cache' );
		} elseif ( $purge_ok ) {
			$result['message'] = __( 'Varnish answers purge requests, but its headers were not visible on the home page.', 'icc-gg-wordpress-super-cache' );
		} elseif ( $probe['detected'] ) {
			$result['message'] = __( 'Varnish looks active, but the purge request was rejected. Check the VCL and the purge key.', 'icc-gg-wordpress-super-cache' );
		} else {
			$result['message'] = __( 'Varnish could not be reached.', 'icc-gg-wordpress-super-cache' );
		}

		return $result;
	}

	/**
	 * Returns a VCL snippet that accepts the plugin purge requests.
	 *
	 * @return string
	 */
	public function vcl_snippet()
	{
		$key = $this->purge_key();

		$lines = [
			'sub vcl_recv {',
			'    if (req.method == "BAN") {',
		];

		if ( '' !== $key ) {
			$lines[] = '        if (req.http.X-Purge-Key != "' . $key . '") {';
			$lines[] = '            return (synth(403, "Forbidden"));';
			$lines[] = '        }';
		}

		$lines[] = '        ban req.http.host == req.http.X-Purge-Host && req.url ~ req.http.X-Purge-Path;';
		$lines[] = '        return (synth(200, "Purged"));';
		$lines[] = '    }';
		$lines[] = '';
		$lines[] = '    if (req.method == "PURGE") {';

		if ( '' !== $key ) {
			$lines[] = '        if (req.http.X-Purge-Key != "' . $key . '") {';
			$lines[] = '            return (synth(403, "Forbidden"));';
			$lines[] = '        }';
		}

		$lines[] = '        return (purge);';
		$lines[] = '    }';
		$lines[] = '}';

		return implode( "\n", $lines );
	}
}
