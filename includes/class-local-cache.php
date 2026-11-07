<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Local PHP page cache controller.
 *
 * Installs and manages the standalone page cache engine shipped in
 * `includes/local-cache-engine.php`: it writes the runtime files, installs the
 * early loader (`advanced-cache.php` when `WP_CACHE` is available, otherwise a
 * must-use plugin loader), stores the settings, purges the cache directory and
 * preloads it.
 *
 * @package   ICC_GG_WordPress_Super_Cache
 * @category  General
 * @author    Ivan Carlos
 * @copyright 2007-2026 Ivan Carlos Consultoria
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0-or-later
 */

/**
 * Local page cache class definition
 */
class ICC_GG_WordPress_Super_Cache_Local_Cache
{
	/**
	 * Option name holding the local page cache settings.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'icc_gg_wordpress_super_cache_local_cache';

	/**
	 * Option name holding the pending preload queue.
	 *
	 * @var string
	 */
	const QUEUE_OPTION = 'icc_gg_wordpress_super_cache_local_preload_queue';

	/**
	 * Cron hook used to preload the cache in batches.
	 *
	 * @var string
	 */
	const PRELOAD_HOOK = 'icc_gg_wordpress_super_cache_local_preload';

	/**
	 * Transient name prefix used to cache the cache directory scan.
	 *
	 * @var string
	 */
	const STATS_TRANSIENT = 'icc_gg_wordpress_super_cache_local_stats';

	/**
	 * Transient used to back off when the runtime files cannot be written.
	 *
	 * @var string
	 */
	const SYNC_TRANSIENT = 'icc_gg_wordpress_super_cache_local_sync';

	/**
	 * Directory inside `wp-content` holding the engine and its configuration.
	 *
	 * @var string
	 */
	const RUNTIME_DIR = 'icc-gg-page-cache';

	/**
	 * Directory inside `wp-content` holding the cached pages.
	 *
	 * @var string
	 */
	const CACHE_SUBDIR = 'cache/icc-gg-page-cache';

	/**
	 * Singleton instance of self.
	 *
	 * @var ICC_GG_WordPress_Super_Cache_Local_Cache|null
	 */
	private static $instance;

	/**
	 * Resolved settings, or null when they still need to be read.
	 *
	 * @var array|null
	 */
	private $settings = null;

	/**
	 * Last error raised while installing the loaders.
	 *
	 * @var WP_Error|null
	 */
	private $last_error = null;

	/**
	 * Create (if needed) and return a singleton of self.
	 *
	 * @return ICC_GG_WordPress_Super_Cache_Local_Cache
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
		$this->load_engine();

		add_action( self::PRELOAD_HOOK, [ $this, 'run_preload_event' ] );
		add_action( 'admin_init', [ $this, 'maybe_sync_runtime' ] );
	}

	/**
	 * Loads the standalone engine so its helpers can be reused.
	 *
	 * The engine may already be loaded: `advanced-cache.php` and the must-use
	 * plugin loader both include the copy stored in `wp-content`, which defines
	 * the same functions as the plugin copy. Loading it twice would be a fatal
	 * redeclaration error, so the functions are the guard.
	 *
	 * @return void
	 */
	protected function load_engine()
	{
		if ( function_exists( 'icc_gg_page_cache_config' ) ) {
			return;
		}

		$engine = ICC_GG_WORDPRESS_SUPER_CACHE_PLUGIN_PATH . '/includes/local-cache-engine.php';

		if ( is_readable( $engine ) ) {
			require_once $engine;
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
			'path' => '',
			'timeout' => 0,
			'auto_purge' => true,
			'gzip' => true,
			'browser_cache' => false,
			'query_strings' => false,
			'mobile' => false,
			'debug_header' => true,
			'preload' => false,
			'preload_batch' => 5,
			'exclude_urls' => '',
			'exclude_agents' => '',
			'exclude_cookies' => '',
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
	 * Whether the local page cache is the active page cache backend.
	 *
	 * @return bool
	 */
	public function is_enabled()
	{
		if ( defined( 'ICC_GG_SUPER_CACHE_LOCAL_ENABLED' ) ) {
			return (bool) ICC_GG_SUPER_CACHE_LOCAL_ENABLED;
		}

		return ICC_GG_WordPress_Super_Cache_Page_Cache::instance()->is_active(
			ICC_GG_WordPress_Super_Cache_Page_Cache::LOCAL
		);
	}

	/**
	 * Whether the cache should be purged automatically when content changes.
	 *
	 * @return bool
	 */
	public function auto_purge_enabled()
	{
		if ( defined( 'ICC_GG_SUPER_CACHE_LOCAL_AUTO_PURGE' ) ) {
			return (bool) ICC_GG_SUPER_CACHE_LOCAL_AUTO_PURGE;
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
				return defined( 'ICC_GG_SUPER_CACHE_LOCAL_ENABLED' );

			case 'auto_purge':
				return defined( 'ICC_GG_SUPER_CACHE_LOCAL_AUTO_PURGE' );

			case 'path':
				return defined( 'ICC_GG_SUPER_CACHE_LOCAL_PATH' );
		}

		return false;
	}

	/**
	 * Returns the human readable status of the local page cache.
	 *
	 * @return string
	 */
	public function get_status()
	{
		if ( ! $this->is_enabled() ) {
			return __( 'Disabled', 'icc-gg-wordpress-super-cache' );
		}

		$mode = $this->bootstrap_mode();

		if ( 'none' === $mode ) {
			return __( 'Enabled, but no loader installed', 'icc-gg-wordpress-super-cache' );
		}

		return 'advanced-cache' === $mode
			? __( 'Enabled (advanced-cache.php)', 'icc-gg-wordpress-super-cache' )
			: __( 'Enabled (must-use plugin loader)', 'icc-gg-wordpress-super-cache' );
	}

	/**
	 * Returns the configured cache path.
	 *
	 * @return string
	 */
	public function get_cache_path()
	{
		if ( defined( 'ICC_GG_SUPER_CACHE_LOCAL_PATH' ) ) {
			return untrailingslashit( (string) ICC_GG_SUPER_CACHE_LOCAL_PATH );
		}

		$path = untrailingslashit( trim( (string) $this->get_setting( 'path' ) ) );

		if ( '' !== $path ) {
			return $path;
		}

		return untrailingslashit( WP_CONTENT_DIR ) . '/' . self::CACHE_SUBDIR;
	}

	/**
	 * Returns a secret directory name that keeps the cache private.
	 *
	 * @return string
	 */
	protected function cache_secret()
	{
		$salt = defined( 'SECURE_AUTH_KEY' ) && SECURE_AUTH_KEY ? SECURE_AUTH_KEY : 'icc-gg-super-cache';

		return substr( md5( $salt . '|icc-gg-page-cache' ), 0, 12 );
	}

	/**
	 * Returns the directory holding the cached pages.
	 *
	 * A random segment is appended so the cache cannot be listed from the web.
	 *
	 * @return string
	 */
	public function cache_root()
	{
		return $this->get_cache_path() . '/' . $this->cache_secret();
	}

	/**
	 * Returns the directory holding the engine and its configuration.
	 *
	 * @return string
	 */
	public function runtime_dir()
	{
		return untrailingslashit( WP_CONTENT_DIR ) . '/' . self::RUNTIME_DIR;
	}

	/**
	 * Builds the configuration consumed by the standalone engine.
	 *
	 * @return array
	 */
	public function get_config()
	{
		$settings = $this->get_settings();

		$timeout = (int) $settings['timeout'];
		$max_age = $timeout > 0 ? min( $timeout, WEEK_IN_SECONDS ) : HOUR_IN_SECONDS;

		$config = [
			'version' => ICC_GG_WORDPRESS_SUPER_CACHE_VERSION,
			'enabled' => $this->is_enabled(),
			'cache_dir' => $this->cache_root(),
			'timeout' => $timeout,
			'max_age' => $max_age,
			'browser_cache' => ! empty( $settings['browser_cache'] ),
			'gzip' => ! empty( $settings['gzip'] ) && function_exists( 'gzencode' ),
			'debug_header' => ! empty( $settings['debug_header'] ),
			'query_strings' => ! empty( $settings['query_strings'] ),
			'mobile' => ! empty( $settings['mobile'] ),
			'exclude_urls' => $this->split_lines( $settings['exclude_urls'] ),
			'exclude_agents' => $this->split_lines( $settings['exclude_agents'] ),
			'exclude_cookies' => $this->split_lines( $settings['exclude_cookies'] ),
		];

		/**
		 * Filters the configuration written for the page cache engine.
		 *
		 * @param array $config Engine configuration.
		 */
		return (array) apply_filters( 'icc_gg_wordpress_super_cache_local_cache_config', $config );
	}

	/**
	 * Splits a textarea value into trimmed, non empty lines.
	 *
	 * @param string $value Raw textarea value.
	 * @return string[]
	 */
	protected function split_lines( $value )
	{
		$lines = preg_split( '/[\r\n]+/', (string) $value );

		if ( ! is_array( $lines ) ) {
			return [];
		}

		$lines = array_map( 'trim', $lines );

		return array_values( array_filter( $lines, 'strlen' ) );
	}

	/**
	 * Tells how the page cache is bootstrapped.
	 *
	 * @return string One of `advanced-cache`, `mu-plugin` or `none`.
	 */
	public function bootstrap_mode()
	{
		if ( $this->dropin_exists() && $this->dropin_is_ours() && defined( 'WP_CACHE' ) && WP_CACHE ) {
			return 'advanced-cache';
		}

		if ( $this->mu_plugin_exists() && $this->mu_plugin_is_ours() ) {
			return 'mu-plugin';
		}

		return 'none';
	}

	/**
	 * Returns the path of the `advanced-cache.php` drop-in.
	 *
	 * @return string
	 */
	public function dropin_path()
	{
		return untrailingslashit( WP_CONTENT_DIR ) . '/advanced-cache.php';
	}

	/**
	 * Returns the path of the must-use plugin loader.
	 *
	 * @return string
	 */
	public function mu_plugin_path()
	{
		return untrailingslashit( WPMU_PLUGIN_DIR ) . '/icc-gg-page-cache.php';
	}

	/**
	 * Whether an `advanced-cache.php` drop-in is present.
	 *
	 * @return bool
	 */
	public function dropin_exists()
	{
		return is_readable( $this->dropin_path() );
	}

	/**
	 * Whether the `advanced-cache.php` drop-in was installed by this plugin.
	 *
	 * @return bool
	 */
	public function dropin_is_ours()
	{
		if ( ! $this->dropin_exists() ) {
			return false;
		}

		return $this->plugin_uri() === $this->read_loader_header( $this->dropin_path(), 'PluginURI' );
	}

	/**
	 * Whether the installed `advanced-cache.php` drop-in is outdated.
	 *
	 * @return bool
	 */
	public function dropin_outdated()
	{
		if ( ! $this->dropin_is_ours() ) {
			return false;
		}

		$version = $this->read_loader_header( $this->dropin_path(), 'Version' );

		return '' !== $version && version_compare( $version, (string) ICC_GG_WORDPRESS_SUPER_CACHE_VERSION, '<' );
	}

	/**
	 * Reads one header field from a generated loader file.
	 *
	 * @param string $file Loader path.
	 * @param string $key  Header key, either `Version` or `PluginURI`.
	 * @return string
	 */
	protected function read_loader_header( $file, $key )
	{
		if ( ! is_readable( $file ) || ! function_exists( 'get_file_data' ) ) {
			return '';
		}

		$data = get_file_data(
			$file,
			[
				'Version' => 'Version',
				'PluginURI' => 'Plugin URI',
			]
		);

		return isset( $data[ $key ] ) ? trim( (string) $data[ $key ] ) : '';
	}

	/**
	 * Whether the must-use plugin loader is present.
	 *
	 * @return bool
	 */
	public function mu_plugin_exists()
	{
		return is_readable( $this->mu_plugin_path() );
	}

	/**
	 * Whether the must-use plugin loader was installed by this plugin.
	 *
	 * @return bool
	 */
	public function mu_plugin_is_ours()
	{
		if ( ! $this->mu_plugin_exists() ) {
			return false;
		}

		return $this->plugin_uri() === $this->read_loader_header( $this->mu_plugin_path(), 'PluginURI' );
	}

	/**
	 * Whether the installed must-use plugin loader is outdated.
	 *
	 * @return bool
	 */
	public function mu_plugin_outdated()
	{
		if ( ! $this->mu_plugin_is_ours() ) {
			return false;
		}

		$version = $this->read_loader_header( $this->mu_plugin_path(), 'Version' );

		return '' !== $version && version_compare( $version, (string) ICC_GG_WORDPRESS_SUPER_CACHE_VERSION, '<' );
	}

	/**
	 * Returns the plugin URI shared by every generated loader.
	 *
	 * @return string
	 */
	protected function plugin_uri()
	{
		return 'https://github.com/ivancarlosti/wordpressiccsupercache';
	}

	/**
	 * Sanitizes the submitted settings.
	 *
	 * The `enabled` flag is deliberately not handled here: it is owned by the
	 * page cache registry so only one backend can be active at a time.
	 *
	 * @param array $input Raw, unslashed settings.
	 * @return array
	 */
	public function sanitize_settings( $input )
	{
		$input = is_array( $input ) ? $input : [];

		$timeout = isset( $input['timeout'] ) ? (int) $input['timeout'] : 0;
		$batch = isset( $input['preload_batch'] ) ? (int) $input['preload_batch'] : 5;

		return [
			'path' => untrailingslashit( sanitize_text_field( isset( $input['path'] ) ? (string) $input['path'] : '' ) ),
			'timeout' => max( 0, $timeout ),
			'auto_purge' => ! empty( $input['auto_purge'] ),
			'gzip' => ! empty( $input['gzip'] ),
			'browser_cache' => ! empty( $input['browser_cache'] ),
			'query_strings' => ! empty( $input['query_strings'] ),
			'mobile' => ! empty( $input['mobile'] ),
			'debug_header' => ! empty( $input['debug_header'] ),
			'preload' => ! empty( $input['preload'] ),
			'preload_batch' => min( 50, max( 1, $batch ) ),
			'exclude_urls' => sanitize_textarea_field( isset( $input['exclude_urls'] ) ? (string) $input['exclude_urls'] : '' ),
			'exclude_agents' => sanitize_textarea_field( isset( $input['exclude_agents'] ) ? (string) $input['exclude_agents'] : '' ),
			'exclude_cookies' => sanitize_textarea_field( isset( $input['exclude_cookies'] ) ? (string) $input['exclude_cookies'] : '' ),
		];
	}

	/**
	 * Initializes the filesystem API.
	 *
	 * @return bool
	 */
	protected function initialize_filesystem()
	{
		global $wp_filesystem;

		if ( $wp_filesystem ) {
			return true;
		}

		return ICC_GG_WordPress_Super_Cache_Plugin::instance()->initialize_filesystem( '', true );
	}

	/**
	 * Recursively creates a directory through the filesystem API.
	 *
	 * `WP_Filesystem::mkdir()` is not recursive unless told so, which fails the
	 * first time a nested path such as `wp-content/cache/plugin/<hash>` is
	 * created.
	 *
	 * @param string $path Directory path.
	 * @return bool
	 */
	protected function make_directory( $path )
	{
		global $wp_filesystem;

		if ( $wp_filesystem && $wp_filesystem->is_dir( $path ) ) {
			return true;
		}

		if ( function_exists( 'wp_mkdir_p' ) && wp_mkdir_p( $path ) ) {
			return true;
		}

		return $wp_filesystem ? (bool) $wp_filesystem->mkdir( $path, FS_CHMOD_DIR, true ) : false;
	}

	/**
	 * Writes a file atomically, so a failed write can never corrupt a loader.
	 *
	 * @param string $path     Destination path.
	 * @param string $contents File contents.
	 * @return true|WP_Error
	 */
	protected function put_file( $path, $contents )
	{
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			return new WP_Error( 'fs', __( 'Filesystem API could not be initialized.', 'icc-gg-wordpress-super-cache' ) );
		}

		$directory = dirname( $path );

		if ( ! $this->make_directory( $directory ) ) {
			return new WP_Error(
				'fs',
				sprintf(
					// translators: %s = Directory path.
					__( 'The directory %s could not be created.', 'icc-gg-wordpress-super-cache' ),
					$directory
				)
			);
		}

		$temporary = $path . '.tmp';

		if ( ! $wp_filesystem->put_contents( $temporary, $contents, FS_CHMOD_FILE ) ) {
			return new WP_Error(
				'fs',
				sprintf(
					// translators: %s = File path.
					__( 'The file %s could not be written.', 'icc-gg-wordpress-super-cache' ),
					$path
				)
			);
		}

		if ( ! $wp_filesystem->move( $temporary, $path, true ) ) {
			$wp_filesystem->delete( $temporary );

			return new WP_Error(
				'fs',
				sprintf(
					// translators: %s = File path.
					__( 'The file %s could not be saved.', 'icc-gg-wordpress-super-cache' ),
					$path
				)
			);
		}

		return true;
	}

	/**
	 * Copies the engine and writes the generated configuration.
	 *
	 * @return true|WP_Error
	 */
	public function write_runtime_files()
	{
		global $wp_filesystem;

		if ( ! $this->initialize_filesystem() ) {
			return new WP_Error( 'fs', __( 'Filesystem API could not be initialized.', 'icc-gg-wordpress-super-cache' ) );
		}

		$runtime = $this->runtime_dir();

		$engine_path = $runtime . '/engine.php';
		$engine_source = ICC_GG_WORDPRESS_SUPER_CACHE_PLUGIN_PATH . '/includes/local-cache-engine.php';

		$engine = @file_get_contents( $engine_source );

		if ( false === $engine ) {
			return new WP_Error( 'fs', __( 'The page cache engine file could not be read.', 'icc-gg-wordpress-super-cache' ) );
		}

		$result = $this->put_file( $engine_path, $engine );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$config = "<?php\n"
			. "/**\n"
			. " * Generated by the ICC.gg WordPress Super Cache plugin. Do not edit.\n"
			. " */\n\n"
			. "return " . var_export( $this->get_config(), true ) . ";\n";

		$result = $this->put_file( $runtime . '/config.php', $config );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$root = $this->cache_root();

		if ( ! $this->make_directory( $root ) ) {
			return new WP_Error(
				'fs',
				sprintf(
					// translators: %s = Cache directory path.
					__( 'The cache directory %s could not be created.', 'icc-gg-wordpress-super-cache' ),
					$root
				)
			);
		}

		$this->flush_stats_cache();

		return true;
	}

	/**
	 * Writes one of the generated loader files.
	 *
	 * @param string $template    Template file name inside `includes/`.
	 * @param string $destination Destination path.
	 * @return true|WP_Error
	 */
	public function write_loader_file( $template, $destination )
	{
		if ( ! $this->initialize_filesystem() ) {
			return new WP_Error( 'fs', __( 'Filesystem API could not be initialized.', 'icc-gg-wordpress-super-cache' ) );
		}

		$source = ICC_GG_WORDPRESS_SUPER_CACHE_PLUGIN_PATH . '/includes/' . $template;
		$contents = @file_get_contents( $source );

		if ( false === $contents ) {
			return new WP_Error( 'fs', __( 'The loader template could not be read.', 'icc-gg-wordpress-super-cache' ) );
		}

		$directory = str_replace( [ '\\', "'" ], [ '\\\\', "\\'" ], $this->runtime_dir() );

		$contents = str_replace(
			[
				'{{ICC_GG_PAGE_CACHE_DIR}}',
				'{{ICC_GG_PAGE_CACHE_VERSION}}',
			],
			[
				$directory,
				ICC_GG_WORDPRESS_SUPER_CACHE_VERSION,
			],
			$contents
		);

		return $this->put_file( $destination, $contents );
	}

	/**
	 * Whether the `WP_CACHE` constant is defined and true.
	 *
	 * @return bool
	 */
	public function has_wp_cache_constant()
	{
		return defined( 'WP_CACHE' ) && WP_CACHE;
	}

	/**
	 * Locates `wp-config.php`.
	 *
	 * @return string Empty string when it could not be found.
	 */
	public function wp_config_path()
	{
		if ( is_readable( ABSPATH . 'wp-config.php' ) ) {
			return ABSPATH . 'wp-config.php';
		}

		$parent = dirname( untrailingslashit( ABSPATH ) ) . '/wp-config.php';

		if ( is_readable( $parent ) && ! is_readable( dirname( untrailingslashit( ABSPATH ) ) . '/wp-settings.php' ) ) {
			return $parent;
		}

		return '';
	}

	/**
	 * Returns the path of the `wp-config.php` backup.
	 *
	 * @return string
	 */
	public function wp_config_backup_path()
	{
		return untrailingslashit( WP_CONTENT_DIR ) . '/wp-config.icc-gg-backup.php';
	}

	/**
	 * Adds the `WP_CACHE` constant to `wp-config.php`.
	 *
	 * The edit is intentionally conservative: the file is backed up, written to
	 * a temporary file, verified and only then renamed over the original. Every
	 * failure returns a `WP_Error` so the caller can fall back to the must-use
	 * plugin loader.
	 *
	 * @return true|WP_Error
	 */
	public function try_edit_wp_config()
	{
		if ( defined( 'ICC_GG_SUPER_CACHE_ALLOW_WP_CONFIG_EDIT' ) && ! ICC_GG_SUPER_CACHE_ALLOW_WP_CONFIG_EDIT ) {
			return new WP_Error(
				'disabled',
				__( 'Editing wp-config.php is disabled by the ICC_GG_SUPER_CACHE_ALLOW_WP_CONFIG_EDIT constant.', 'icc-gg-wordpress-super-cache' )
			);
		}

		if ( $this->has_wp_cache_constant() ) {
			return true;
		}

		$path = $this->wp_config_path();

		if ( '' === $path ) {
			return new WP_Error( 'missing', __( 'wp-config.php could not be located.', 'icc-gg-wordpress-super-cache' ) );
		}

		if ( function_exists( 'is_file_mod_allowed' ) && ! is_file_mod_allowed( 'icc_gg_super_cache_wp_config' ) ) {
			return new WP_Error( 'not_allowed', __( 'File modifications are not allowed on this installation.', 'icc-gg-wordpress-super-cache' ) );
		}

		if ( untrailingslashit( WP_CONTENT_DIR ) !== untrailingslashit( ABSPATH . 'wp-content' ) ) {
			return new WP_Error(
				'custom_content_dir',
				__( 'WP_CONTENT_DIR is customized, so WP_CACHE has to be added to wp-config.php manually.', 'icc-gg-wordpress-super-cache' )
			);
		}

		if ( ! is_writable( $path ) ) {
			return new WP_Error( 'unwritable', __( 'wp-config.php is not writable.', 'icc-gg-wordpress-super-cache' ) );
		}

		$contents = @file_get_contents( $path );

		if ( false === $contents ) {
			return new WP_Error( 'unreadable', __( 'wp-config.php could not be read.', 'icc-gg-wordpress-super-cache' ) );
		}

		if ( false !== strpos( $contents, "'WP_CACHE'" ) || false !== strpos( $contents, '"WP_CACHE"' ) ) {
			// Already enabled in the file but not loaded yet in this request.
			if ( preg_match( '/define\s*\(\s*[\'"]WP_CACHE[\'"]\s*,\s*true\s*\)\s*;/i', $contents ) ) {
				return true;
			}

			// The constant exists but is falsy: flip it.
			$updated = preg_replace(
				'/define\s*\(\s*([\'"])WP_CACHE\1\s*,\s*false\s*\)\s*;/i',
				"define( 'WP_CACHE', true );",
				$contents,
				1
			);

			if ( ! is_string( $updated ) || $updated === $contents ) {
				return new WP_Error(
					'not_recognized',
					__( 'The WP_CACHE constant in wp-config.php could not be updated automatically.', 'icc-gg-wordpress-super-cache' )
				);
			}

			$contents = $updated;
			$snippet = "define( 'WP_CACHE', true );";
		} else {
			$snippet = "define( 'WP_CACHE', true );";

			$anchor = "/* That's all, stop editing! Happy publishing. */";

			if ( false === strpos( $contents, $anchor ) ) {
				if ( ! preg_match( '#require_once\s+ABSPATH\s*\.\s*[\'"]wp-settings\.php[\'"]\s*;#', $contents, $matches ) ) {
					return new WP_Error(
						'anchor',
						__( 'The wp-config.php layout was not recognized, so WP_CACHE was not added.', 'icc-gg-wordpress-super-cache' )
					);
				}

				$anchor = $matches[0];
			}

			$contents = str_replace(
				$anchor,
				$snippet . "\n\n" . $anchor,
				$contents
			);
		}

		$backup = $this->wp_config_backup_path();

		if ( ! file_exists( $backup ) && ! @copy( $path, $backup ) ) {
			return new WP_Error( 'backup', __( 'wp-config.php could not be backed up.', 'icc-gg-wordpress-super-cache' ) );
		}

		$permissions = @fileperms( $path );
		$temporary = $path . '.icc-gg.tmp';

		if ( false === @file_put_contents( $temporary, $contents ) ) {
			return new WP_Error( 'write', __( 'The temporary wp-config.php file could not be written.', 'icc-gg-wordpress-super-cache' ) );
		}

		if ( ! @rename( $temporary, $path ) ) {
			@unlink( $temporary );

			return new WP_Error( 'rename', __( 'wp-config.php could not be replaced.', 'icc-gg-wordpress-super-cache' ) );
		}

		if ( false !== $permissions ) {
			@chmod( $path, $permissions & 0777 );
		}

		$verification = @file_get_contents( $path );

		if ( false === $verification || false === strpos( $verification, $snippet ) ) {
			$this->revert_wp_config();

			return new WP_Error( 'verify', __( 'The wp-config.php edit could not be verified and was reverted.', 'icc-gg-wordpress-super-cache' ) );
		}

		return true;
	}

	/**
	 * Restores the `wp-config.php` backup.
	 *
	 * @return true|WP_Error
	 */
	public function revert_wp_config()
	{
		$path = $this->wp_config_path();
		$backup = $this->wp_config_backup_path();

		if ( '' === $path || ! is_readable( $backup ) ) {
			return new WP_Error( 'missing_backup', __( 'No wp-config.php backup was found.', 'icc-gg-wordpress-super-cache' ) );
		}

		if ( ! is_writable( $path ) || ! @copy( $backup, $path ) ) {
			return new WP_Error( 'restore', __( 'wp-config.php could not be restored from the backup.', 'icc-gg-wordpress-super-cache' ) );
		}

		@unlink( $backup );

		return true;
	}

	/**
	 * Returns the error raised while installing the loaders, if any.
	 *
	 * @return WP_Error|null
	 */
	public function get_last_error()
	{
		return $this->last_error;
	}

	/**
	 * Installs (or refreshes) the runtime files and the page cache loader.
	 *
	 * When `WP_CACHE` cannot be added to `wp-config.php` the must-use plugin
	 * loader is installed instead, so the cache still works on hosts where
	 * `wp-config.php` is read only.
	 *
	 * @return true|WP_Error
	 */
	public function install()
	{
		$this->last_error = null;

		$runtime = $this->write_runtime_files();

		if ( is_wp_error( $runtime ) ) {
			return $runtime;
		}

		if ( ! $this->is_enabled() ) {
			return $this->remove_loaders();
		}

		if ( $this->dropin_exists() && ! $this->dropin_is_ours() ) {
			return new WP_Error(
				'foreign_dropin',
				__( 'Another plugin already installed an advanced-cache.php drop-in. Remove it before enabling the local page cache.', 'icc-gg-wordpress-super-cache' )
			);
		}

		if ( $this->mu_plugin_exists() && ! $this->mu_plugin_is_ours() ) {
			return new WP_Error(
				'foreign_mu_plugin',
				__( 'Another must-use plugin already uses the file wp-content/mu-plugins/icc-gg-page-cache.php. Remove it before enabling the local page cache.', 'icc-gg-wordpress-super-cache' )
			);
		}

		$wp_config = $this->try_edit_wp_config();

		if ( true === $wp_config ) {
			$result = $this->write_loader_file( 'advanced-cache.php', $this->dropin_path() );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$this->remove_loader_file( $this->mu_plugin_path() );

			return true;
		}

		$this->last_error = $wp_config;

		return $this->write_loader_file( 'mu-plugin-loader.php', $this->mu_plugin_path() );
	}

	/**
	 * Removes a loader file, but only when this plugin created it.
	 *
	 * @param string $path Loader path.
	 * @return true|WP_Error
	 */
	public function remove_loader_file( $path )
	{
		if ( ! file_exists( $path ) ) {
			return true;
		}

		if ( $this->plugin_uri() !== $this->read_loader_header( $path, 'PluginURI' ) ) {
			return new WP_Error(
				'foreign_loader',
				sprintf(
					// translators: %s = Loader path.
					__( 'The file %s was not created by this plugin and was left untouched.', 'icc-gg-wordpress-super-cache' ),
					$path
				)
			);
		}

		if ( ! @unlink( $path ) ) {
			return new WP_Error(
				'fs',
				sprintf(
					// translators: %s = Loader path.
					__( 'The file %s could not be removed.', 'icc-gg-wordpress-super-cache' ),
					$path
				)
			);
		}

		return true;
	}

	/**
	 * Removes both page cache loaders.
	 *
	 * @return true|WP_Error
	 */
	public function remove_loaders()
	{
		$errors = [];

		foreach ( [ $this->dropin_path(), $this->mu_plugin_path() ] as $path ) {
			$result = $this->remove_loader_file( $path );

			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			}
		}

		if ( $errors ) {
			return new WP_Error( 'fs', implode( ' ', $errors ) );
		}

		return true;
	}

	/**
	 * Rewrites the runtime files after a plugin update or reactivation.
	 *
	 * @return void
	 */
	public function maybe_sync_runtime()
	{
		if ( ! $this->is_enabled() ) {
			return;
		}

		$needs_loader = 'none' === $this->bootstrap_mode();
		$needs_sync = ! is_readable( $this->runtime_dir() . '/config.php' )
			|| $this->dropin_outdated()
			|| $this->mu_plugin_outdated();

		if ( ! $needs_loader && ! $needs_sync ) {
			return;
		}

		// Back off for an hour when the filesystem does not let us write.
		if ( get_transient( self::SYNC_TRANSIENT ) ) {
			return;
		}

		if ( $needs_loader ) {
			if ( is_wp_error( $this->install() ) ) {
				set_transient( self::SYNC_TRANSIENT, 1, HOUR_IN_SECONDS );
			}

			return;
		}

		if ( is_wp_error( $this->write_runtime_files() ) ) {
			set_transient( self::SYNC_TRANSIENT, 1, HOUR_IN_SECONDS );

			return;
		}

		if ( $this->dropin_is_ours() ) {
			$this->write_loader_file( 'advanced-cache.php', $this->dropin_path() );
		}

		if ( $this->mu_plugin_is_ours() ) {
			$this->write_loader_file( 'mu-plugin-loader.php', $this->mu_plugin_path() );
		}
	}

	/**
	 * Removes the runtime files, the loaders and the cached pages.
	 *
	 * @return true|WP_Error
	 */
	public function uninstall()
	{
		global $wp_filesystem;

		$this->unschedule_preload();

		delete_option( self::QUEUE_OPTION );

		$loaders = $this->remove_loaders();

		if ( ! $this->initialize_filesystem() ) {
			return $loaders;
		}

		$root = $this->cache_root();

		if ( $wp_filesystem->is_dir( dirname( $root ) ) ) {
			$wp_filesystem->rmdir( dirname( $root ), true );
		}

		if ( $wp_filesystem->is_dir( $this->runtime_dir() ) ) {
			$wp_filesystem->rmdir( $this->runtime_dir(), true );
		}

		$this->flush_stats_cache();

		return $loaders;
	}

	/**
	 * Deletes the cached pages.
	 *
	 * @return true
	 */
	public function purge()
	{
		$root = $this->cache_root();

		$this->delete_directory_contents( $root );

		if ( ! is_dir( $root ) ) {
			@mkdir( $root, 0755, true );
		}

		$this->flush_stats_cache();

		/**
		 * Fires after the local page cache was purged.
		 */
		do_action( 'icc_gg_wordpress_super_cache_local_cache_purged' );

		return true;
	}

	/**
	 * Recursively deletes the contents of a directory.
	 *
	 * @param string $path Directory path.
	 * @return void
	 */
	protected function delete_directory_contents( $path )
	{
		if ( ! is_dir( $path ) ) {
			return;
		}

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::CHILD_FIRST,
				RecursiveIteratorIterator::CATCH_GET_CHILD
			);

			foreach ( $iterator as $item ) {
				if ( $item->isDir() ) {
					@rmdir( $item->getPathname() );
				} else {
					@unlink( $item->getPathname() );
				}
			}
		} catch ( Exception $exception ) {
			// Unreadable directories are skipped; the next purge retries them.
			unset( $exception );
		}
	}

	/**
	 * Purges one or more URLs from the cache.
	 *
	 * Entries may be absolute URLs, paths or wildcard prefixes such as
	 * `/blog/*`.
	 *
	 * @param string|string[] $urls URLs or newline separated URLs.
	 * @return int Number of purged entries.
	 */
	public function purge_urls( $urls )
	{
		if ( is_string( $urls ) ) {
			$urls = preg_split( '/[\r\n,]+/', $urls );
		}

		$config = $this->get_config();
		$deleted = 0;

		foreach ( (array) $urls as $url ) {
			$url = trim( (string) $url );

			if ( '' === $url ) {
				continue;
			}

			$deleted += $this->purge_url( $url, $config );
		}

		$this->flush_stats_cache();

		return $deleted;
	}

	/**
	 * Purges a single URL from the cache.
	 *
	 * @param string     $url    URL, path or wildcard prefix.
	 * @param array|null $config Engine configuration.
	 * @return int Number of removed files.
	 */
	public function purge_url( $url, $config = null )
	{
		if ( ! function_exists( 'icc_gg_page_cache_file' ) ) {
			$this->load_engine();
		}

		if ( ! function_exists( 'icc_gg_page_cache_file' ) ) {
			return 0;
		}

		$config = null === $config ? $this->get_config() : $config;
		$url = $this->normalize_url( $url );

		$wildcard = false !== strpos( $url, '*' );

		if ( $wildcard ) {
			$url = str_replace( '*', '', $url );
		}

		$file = icc_gg_page_cache_file( $config, $url );

		if ( '' === $file ) {
			return 0;
		}

		if ( $wildcard ) {
			$this->delete_directory_contents( dirname( $file ) );

			return 1;
		}

		$deleted = 0;

		foreach ( [ $file, $file . '.gz' ] as $path ) {
			if ( file_exists( $path ) && @unlink( $path ) ) {
				$deleted++;
			}
		}

		$root = $this->cache_root();
		$directory = dirname( $file );

		while ( strlen( $directory ) > strlen( $root ) && is_dir( $directory ) ) {
			if ( ! @rmdir( $directory ) ) {
				break;
			}

			$directory = dirname( $directory );
		}

		return $deleted;
	}

	/**
	 * Turns a path into an absolute URL.
	 *
	 * @param string $url URL or path.
	 * @return string
	 */
	protected function normalize_url( $url )
	{
		$url = trim( (string) $url );

		if ( 0 === strpos( $url, 'http://' ) || 0 === strpos( $url, 'https://' ) ) {
			return $url;
		}

		return home_url( '/' . ltrim( $url, '/' ) );
	}

	/**
	 * Scans the cache directory.
	 *
	 * @return array{files:int,size:int,newest:int,oldest:int}
	 */
	public function cache_stats()
	{
		$root = $this->cache_root();
		$transient = self::STATS_TRANSIENT . '_' . md5( $root );

		$cached = get_transient( $transient );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$stats = [
			'pages' => 0,
			'files' => 0,
			'size' => 0,
			'newest' => 0,
			'oldest' => 0,
		];

		if ( is_dir( $root ) ) {
			try {
				$iterator = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
					RecursiveIteratorIterator::LEAVES_ONLY,
					RecursiveIteratorIterator::CATCH_GET_CHILD
				);

				foreach ( $iterator as $file ) {
					if ( ! $file->isFile() ) {
						continue;
					}

					$stats['files']++;
					$stats['size'] += (int) $file->getSize();

					// Every cached page is stored as an index.html file, its
					// optional .gz twin is only counted in the file total.
					if ( 'index.html' === $file->getFilename() ) {
						$stats['pages']++;
					}

					$mtime = (int) $file->getMTime();

					if ( 0 === $stats['newest'] || $mtime > $stats['newest'] ) {
						$stats['newest'] = $mtime;
					}

					if ( 0 === $stats['oldest'] || $mtime < $stats['oldest'] ) {
						$stats['oldest'] = $mtime;
					}

					if ( $stats['files'] >= 50000 ) {
						break;
					}
				}
			} catch ( Exception $exception ) {
				unset( $exception );
			}
		}

		set_transient( $transient, $stats, MINUTE_IN_SECONDS );

		return $stats;
	}

	/**
	 * Deletes the cached directory scan.
	 *
	 * @return void
	 */
	public function flush_stats_cache()
	{
		delete_transient( self::STATS_TRANSIENT . '_' . md5( $this->cache_root() ) );
	}

	/**
	 * Returns the pending preload queue.
	 *
	 * @return string[]
	 */
	public function get_preload_queue()
	{
		$queue = get_option( self::QUEUE_OPTION, [] );

		return is_array( $queue ) ? array_values( array_filter( $queue, 'is_string' ) ) : [];
	}

	/**
	 * Builds the list of URLs to preload.
	 *
	 * @return string[]
	 */
	public function preload_urls()
	{
		$urls = [ home_url( '/' ) ];

		$post_types = get_post_types( [ 'public' => true ], 'names' );
		unset( $post_types['attachment'] );

		if ( $post_types ) {
			$posts = get_posts(
				[
					'post_type' => array_values( $post_types ),
					'post_status' => 'publish',
					'numberposts' => 500,
					'fields' => 'ids',
					'orderby' => 'modified',
					'order' => 'DESC',
					'no_found_rows' => true,
				]
			);

			foreach ( $posts as $post_id ) {
				$permalink = get_permalink( $post_id );

				if ( is_string( $permalink ) && '' !== $permalink ) {
					$urls[] = $permalink;
				}
			}
		}

		$terms = get_terms(
			[
				'taxonomy' => [ 'category', 'post_tag' ],
				'hide_empty' => true,
				'number' => 100,
			]
		);

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$link = get_term_link( $term );

				if ( ! is_wp_error( $link ) && is_string( $link ) ) {
					$urls[] = $link;
				}
			}
		}

		$urls = array_values( array_unique( array_filter( $urls ) ) );

		/**
		 * Filters the URLs used to preload the local page cache.
		 *
		 * @param string[] $urls URLs to preload.
		 */
		return (array) apply_filters( 'icc_gg_wordpress_super_cache_local_preload_urls', $urls );
	}

	/**
	 * Queues every public URL for preloading.
	 *
	 * @return int Number of queued URLs.
	 */
	public function start_preload()
	{
		$urls = $this->preload_urls();

		if ( ! $urls ) {
			return 0;
		}

		update_option( self::QUEUE_OPTION, $urls, false );

		$this->schedule_preload( 5 );

		return count( $urls );
	}

	/**
	 * Schedules the next preload batch.
	 *
	 * @param int $delay Delay in seconds.
	 * @return void
	 */
	protected function schedule_preload( $delay = 60 )
	{
		if ( wp_next_scheduled( self::PRELOAD_HOOK ) ) {
			return;
		}

		wp_schedule_single_event( time() + max( 1, (int) $delay ), self::PRELOAD_HOOK );
	}

	/**
	 * Removes any scheduled preload batch.
	 *
	 * @return void
	 */
	public function unschedule_preload()
	{
		wp_clear_scheduled_hook( self::PRELOAD_HOOK );
	}

	/**
	 * Cron callback that processes one preload batch.
	 *
	 * @return void
	 */
	public function run_preload_event()
	{
		$this->run_preload_batch( (int) $this->get_setting( 'preload_batch' ) );
	}

	/**
	 * Preloads a batch of URLs with non blocking requests.
	 *
	 * @param int $limit Maximum number of URLs.
	 * @return array{processed:int,remaining:int,done:bool}
	 */
	public function run_preload_batch( $limit = 5 )
	{
		$queue = $this->get_preload_queue();
		$limit = max( 1, (int) $limit );

		if ( ! $queue ) {
			return [
				'processed' => 0,
				'remaining' => 0,
				'done' => true,
			];
		}

		$batch = array_splice( $queue, 0, $limit );

		update_option( self::QUEUE_OPTION, $queue, false );

		/**
		 * Filters whether SSL certificates are verified during preload.
		 *
		 * @param bool $verify Whether to verify certificates.
		 */
		$sslverify = (bool) apply_filters( 'icc_gg_wordpress_super_cache_preload_sslverify', true );

		foreach ( $batch as $url ) {
			wp_remote_get(
				$url,
				[
					'timeout' => 5,
					'blocking' => false,
					'redirection' => 3,
					'sslverify' => $sslverify,
					'headers' => [
						'X-ICC-Page-Cache-Preload' => '1',
					],
					'user-agent' => 'ICC.gg Page Cache Preload/1.0; ' . home_url( '/' ),
				]
			);
		}

		if ( $queue ) {
			$this->schedule_preload( 30 );
		}

		return [
			'processed' => count( $batch ),
			'remaining' => count( $queue ),
			'done' => ! $queue,
		];
	}
}
