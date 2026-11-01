<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Nginx page cache controller.
 *
 * Purges the Nginx cache zone (FastCGI, Proxy or uWSGI) by removing the files
 * inside the `fastcgi_cache_path` / `proxy_cache_path` directory, either
 * automatically when content changes or manually from the admin area.
 *
 * The purge behaviour is derived from the Nginx Cache plugin by Till Krüss
 * (https://wordpress.org/plugins/nginx-cache/, GPL-3.0-or-later).
 *
 * @package   ICC_GG_WordPress_Super_Cache
 * @category  General
 * @author    Ivan Carlos
 * @copyright 2007-2026 Ivan Carlos Consultoria
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0-or-later
 */

/**
 * Nginx cache class definition
 */
class ICC_GG_WordPress_Super_Cache_Nginx_Cache
{
	/**
	 * Option name holding the Nginx cache settings.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'icc_gg_wordpress_super_cache_nginx_cache';

	/**
	 * Option name used by the upstream Nginx Cache plugin for the cache path.
	 *
	 * @var string
	 */
	const LEGACY_PATH_OPTION = 'nginx_cache_path';

	/**
	 * Option name used by the upstream Nginx Cache plugin for auto purge.
	 *
	 * @var string
	 */
	const LEGACY_AUTO_PURGE_OPTION = 'nginx_auto_purge';

	/**
	 * Transient name prefix used to cache the cache zone directory scan.
	 *
	 * @var string
	 */
	const STATS_TRANSIENT = 'icc_gg_wordpress_super_cache_nginx_stats';

	/**
	 * Singleton instance of self.
	 *
	 * @var ICC_GG_WordPress_Super_Cache_Nginx_Cache|null
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
	 * @return ICC_GG_WordPress_Super_Cache_Nginx_Cache
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
		$this->maybe_import_legacy_settings();

		if ( $this->is_enabled() && $this->auto_purge_enabled() ) {
			add_action( 'init', [ $this, 'register_purge_actions' ], 20 );
		}
	}

	/**
	 * Returns the default settings.
	 *
	 * @return array
	 */
	public function defaults()
	{
		return [
			'enabled' => false,
			'path' => '',
			'auto_purge' => true,
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
	 * Reads a single setting without applying constant overrides.
	 *
	 * @param string $key Setting name.
	 * @return mixed
	 */
	public function get_setting( $key )
	{
		$settings = $this->get_settings();

		return $settings[ $key ] ?? null;
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
	 * Whether the Nginx cache integration is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled()
	{
		if ( defined( 'ICC_GG_SUPER_CACHE_NGINX_ENABLED' ) ) {
			return (bool) ICC_GG_SUPER_CACHE_NGINX_ENABLED;
		}

		return (bool) $this->get_setting( 'enabled' );
	}

	/**
	 * Whether the cache should be purged automatically when content changes.
	 *
	 * @return bool
	 */
	public function auto_purge_enabled()
	{
		if ( defined( 'ICC_GG_SUPER_CACHE_NGINX_AUTO_PURGE' ) ) {
			return (bool) ICC_GG_SUPER_CACHE_NGINX_AUTO_PURGE;
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
				return defined( 'ICC_GG_SUPER_CACHE_NGINX_ENABLED' );

			case 'auto_purge':
				return defined( 'ICC_GG_SUPER_CACHE_NGINX_AUTO_PURGE' );

			case 'path':
				return defined( 'ICC_GG_SUPER_CACHE_NGINX_PATH' ) || defined( 'NGINX_CACHE_PATH' );
		}

		return false;
	}

	/**
	 * Retrieves the cache zone path.
	 *
	 * @return string
	 */
	public function get_cache_path()
	{
		if ( defined( 'ICC_GG_SUPER_CACHE_NGINX_PATH' ) ) {
			return (string) sanitize_text_field( ICC_GG_SUPER_CACHE_NGINX_PATH );
		}

		if ( defined( 'NGINX_CACHE_PATH' ) ) {
			return (string) sanitize_text_field( NGINX_CACHE_PATH );
		}

		return (string) $this->get_setting( 'path' );
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

		$path = isset( $input['path'] ) ? sanitize_text_field( (string) $input['path'] ) : '';
		$path = untrailingslashit( trim( $path ) );

		return [
			'enabled' => ! empty( $input['enabled'] ),
			'path' => $path,
			'auto_purge' => ! empty( $input['auto_purge'] ),
		];
	}

	/**
	 * Imports the settings of the upstream Nginx Cache plugin (once).
	 *
	 * @return void
	 */
	public function maybe_import_legacy_settings()
	{
		if ( false !== get_option( self::OPTION_KEY, false ) ) {
			return;
		}

		$path = (string) get_option( self::LEGACY_PATH_OPTION, '' );
		$auto_purge = (string) get_option( self::LEGACY_AUTO_PURGE_OPTION, '' );

		if ( '' === $path && '' === $auto_purge ) {
			return;
		}

		update_option(
			self::OPTION_KEY,
			[
				'enabled' => '' !== $path,
				'path' => untrailingslashit( sanitize_text_field( $path ) ),
				'auto_purge' => (bool) $auto_purge,
			]
		);

		$this->flush_settings_cache();
	}

	/**
	 * Registers the actions that trigger a cache purge.
	 *
	 * @return void
	 */
	public function register_purge_actions()
	{
		if ( ! $this->is_enabled() || ! $this->auto_purge_enabled() ) {
			return;
		}

		/**
		 * Filters the actions that trigger an automatic Nginx cache purge.
		 *
		 * @param string[] $purge_actions Hook names.
		 */
		$purge_actions = (array) apply_filters(
			'icc_gg_wordpress_super_cache_nginx_purge_actions',
			[
				'publish_phone',
				'save_post',
				'edit_post',
				'delete_post',
				'wp_trash_post',
				'clean_post_cache',
				'trackback_post',
				'pingback_post',
				'comment_post',
				'edit_comment',
				'delete_comment',
				'wp_set_comment_status',
				'switch_theme',
				'wp_update_nav_menu',
				'edit_user_profile_update',
			]
		);

		foreach ( $purge_actions as $action ) {
			if ( did_action( $action ) ) {
				$this->purge_zone_once();
			} else {
				add_action( $action, [ $this, 'purge_zone_once' ] );
			}
		}
	}

	/**
	 * Purges the cache zone at most once per request.
	 *
	 * @return void
	 */
	public function purge_zone_once()
	{
		static $completed = false;

		if ( $completed ) {
			return;
		}

		$this->purge_zone();

		$completed = true;
	}

	/**
	 * Purges (empties) the Nginx cache zone.
	 *
	 * @return true|false|WP_Error True on success, false when skipped.
	 */
	public function purge_zone()
	{
		global $wp_filesystem;

		if ( ! $this->is_enabled() ) {
			return new WP_Error( 'disabled', __( 'The Nginx cache is disabled.', 'icc-gg-wordpress-super-cache' ) );
		}

		if ( ! $this->should_purge() ) {
			return false;
		}

		$path = $this->get_cache_path();
		$path_error = $this->is_valid_path();

		if ( is_wp_error( $path_error ) ) {
			return $path_error;
		}

		if ( ! $this->initialize_filesystem() ) {
			return new WP_Error( 'fs', __( 'Filesystem API could not be initialized.', 'icc-gg-wordpress-super-cache' ) );
		}

		// Delete the cache directory (recursively) and re-create it empty.
		$wp_filesystem->rmdir( $path, true );
		$wp_filesystem->mkdir( $path );

		$this->flush_stats_cache();

		/**
		 * Fires after the Nginx cache zone was purged.
		 *
		 * @param string $path The purged cache zone path.
		 */
		do_action( 'icc_gg_wordpress_super_cache_nginx_cache_purged', $path );

		return true;
	}

	/**
	 * Whether the current request should result in a cache purge.
	 *
	 * @return bool
	 */
	private function should_purge()
	{
		$post_type = get_post_type();

		if ( ! $post_type ) {
			return true;
		}

		/**
		 * Filters the post types that are excluded from triggering a purge.
		 *
		 * @param string[] $post_types Post type names.
		 */
		$excluded = (array) apply_filters( 'icc_gg_wordpress_super_cache_nginx_excluded_post_types', [] );

		return ! in_array( $post_type, $excluded, true );
	}

	/**
	 * Validates the configured cache zone path.
	 *
	 * @return true|WP_Error
	 */
	public function is_valid_path()
	{
		global $wp_filesystem;

		$path = $this->get_cache_path();

		if ( '' === $path ) {
			return new WP_Error( 'empty', __( '"Cache Zone Path" is not set.', 'icc-gg-wordpress-super-cache' ) );
		}

		if ( ! $this->initialize_filesystem() ) {
			return new WP_Error( 'fs', __( 'Filesystem API could not be initialized.', 'icc-gg-wordpress-super-cache' ) );
		}

		if ( ! $wp_filesystem->exists( $path ) ) {
			return new WP_Error( 'fs', __( '"Cache Zone Path" does not exist.', 'icc-gg-wordpress-super-cache' ) );
		}

		if ( ! $wp_filesystem->is_dir( $path ) ) {
			return new WP_Error( 'fs', __( '"Cache Zone Path" is not a directory.', 'icc-gg-wordpress-super-cache' ) );
		}

		$list = $wp_filesystem->dirlist( $path, true, true );

		if ( is_array( $list ) && ! $this->validate_dirlist( $list ) ) {
			return new WP_Error( 'fs', __( '"Cache Zone Path" does not appear to be an Nginx cache zone directory.', 'icc-gg-wordpress-super-cache' ) );
		}

		if ( ! $wp_filesystem->is_writable( $path ) ) {
			return new WP_Error( 'fs', __( '"Cache Zone Path" is not writable.', 'icc-gg-wordpress-super-cache' ) );
		}

		return true;
	}

	/**
	 * Checks that a directory listing looks like an Nginx cache zone.
	 *
	 * @param array $list Directory listing as returned by the filesystem API.
	 * @return bool
	 */
	private function validate_dirlist( $list )
	{
		foreach ( (array) $list as $item ) {
			if ( ! isset( $item['type'], $item['name'] ) ) {
				continue;
			}

			// Ignore hidden bookkeeping files such as Nginx's own temp files.
			if ( 'f' === $item['type'] && '.' === substr( $item['name'], 0, 1 ) ) {
				continue;
			}

			// Abort if the entry is a file that is not named after an MD5 hash.
			if ( 'f' === $item['type'] && ( 32 !== strlen( $item['name'] ) || ! ctype_xdigit( $item['name'] ) ) ) {
				return false;
			}

			// Validate sub directories recursively.
			if ( 'd' === $item['type'] && isset( $item['files'] ) && is_array( $item['files'] ) ) {
				if ( ! $this->validate_dirlist( $item['files'] ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Initializes the WordPress filesystem API for the cache zone.
	 *
	 * @return bool
	 */
	public function initialize_filesystem()
	{
		$path = $this->get_cache_path();

		return ICC_GG_WordPress_Super_Cache_Plugin::instance()->initialize_filesystem(
			'',
			true,
			'' === $path ? false : $path
		);
	}

	/**
	 * Collects the number of files and their combined size in the cache zone.
	 *
	 * @return array{files: int, size: int}
	 */
	public function get_cache_stats()
	{
		$empty = [
			'files' => 0,
			'size' => 0,
		];

		$path = $this->get_cache_path();

		if ( '' === $path || true !== $this->is_valid_path() ) {
			return $empty;
		}

		$transient = self::STATS_TRANSIENT . '_' . md5( $path );
		$cached = get_transient( $transient );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$stats = $this->scan_directory( $path, 3 );

		set_transient( $transient, $stats, MINUTE_IN_SECONDS );

		return $stats;
	}

	/**
	 * Recursively sums up the files and bytes of a directory.
	 *
	 * @param string $path  Absolute directory path.
	 * @param int    $depth How many levels of sub directories to descend.
	 * @return array{files: int, size: int}
	 */
	private function scan_directory( $path, $depth )
	{
		global $wp_filesystem;

		$stats = [
			'files' => 0,
			'size' => 0,
		];

		$list = $wp_filesystem->dirlist( $path, false, true );

		if ( ! is_array( $list ) ) {
			return $stats;
		}

		foreach ( $list as $item ) {
			if ( ! isset( $item['type'], $item['name'] ) ) {
				continue;
			}

			if ( 'f' === $item['type'] ) {
				$stats['files']++;
				$stats['size'] += (int) ( $item['size'] ?? 0 );

				continue;
			}

			if ( 'd' === $item['type'] && $depth > 0 ) {
				$child = $this->scan_directory( trailingslashit( $path ) . $item['name'], $depth - 1 );

				$stats['files'] += $child['files'];
				$stats['size'] += $child['size'];
			}
		}

		return $stats;
	}

	/**
	 * Deletes the cached directory scan.
	 *
	 * @return void
	 */
	public function flush_stats_cache()
	{
		$path = $this->get_cache_path();

		if ( '' !== $path ) {
			delete_transient( self::STATS_TRANSIENT . '_' . md5( $path ) );
		}
	}

	/**
	 * Returns the human readable status of the Nginx cache integration.
	 *
	 * @return string
	 */
	public function get_status()
	{
		if ( ! $this->is_enabled() ) {
			return __( 'Disabled', 'icc-gg-wordpress-super-cache' );
		}

		$path_error = $this->is_valid_path();

		if ( is_wp_error( $path_error ) ) {
			return sprintf(
				// translators: %s = The cache zone path validation error.
				__( 'Enabled, but unusable: %s', 'icc-gg-wordpress-super-cache' ),
				$path_error->get_error_message()
			);
		}

		return $this->auto_purge_enabled()
			? __( 'Enabled (auto purge)', 'icc-gg-wordpress-super-cache' )
			: __( 'Enabled (manual purge)', 'icc-gg-wordpress-super-cache' );
	}
}
