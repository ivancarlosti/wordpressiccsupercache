<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * ICC.gg WordPress Super Cache - WordPress Super Cache Plugin
 *
 * Three independent caching layers in one plugin:
 * - Redis object cache (Predis, PhpRedis, Relay, replication, sentinels,
 *   clustering and WP-CLI).
 * - Local PHP page cache (advanced-cache.php drop-in or must-use plugin loader).
 * - Varnish Cache and nginx FastCGI page cache purging.
 *
 * @package   ICC_GG_WordPress_Super_Cache
 * @category  General
 * @author    Ivan Carlos
 * @copyright 2007-2026 Ivan Carlos Consultoria
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0-or-later
 * @link      https://github.com/ivancarlosti/wordpressiccsupercache
 *
 * @wordpress-plugin
 * Plugin Name:       ICC.gg WordPress Super Cache
 * Plugin URI:        https://github.com/ivancarlosti/wordpressiccsupercache
 * Description:       Redis object cache, Varnish Cache and nginx FastCGI page cache in one plugin. Enable, configure and purge each layer independently.
 * Version:           2.0.0
 * Requires at least: 5.0
 * Requires PHP:      8.2
 * Author:            ivancarlosti
 * Author URI:        https://ivancarlos.com.br
 * Text Domain:       icc-gg-wordpress-super-cache
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.txt
 */

/*
 * Notes
 *
 * Configuration constants (wp-config.php):
 * - WP_REDIS_HOST, WP_REDIS_PORT, WP_REDIS_DATABASE, WP_REDIS_PASSWORD, WP_REDIS_USERNAME
 * - WP_REDIS_SCHEME, WP_REDIS_PATH, WP_REDIS_TIMEOUT, WP_REDIS_READ_TIMEOUT, WP_REDIS_RETRY_INTERVAL
 * - WP_REDIS_SERVERS, WP_REDIS_SHARDS, WP_REDIS_CLUSTER, WP_REDIS_SENTINEL
 * - WP_REDIS_PREFIX, WP_REDIS_SELECTIVE_FLUSH, WP_REDIS_MAXTTL
 * - WP_REDIS_GLOBAL_GROUPS, WP_REDIS_IGNORED_GROUPS, WP_REDIS_UNFLUSHABLE_GROUPS
 * - WP_REDIS_DISABLED, WP_REDIS_GRACEFUL, WP_REDIS_CLIENT
 *
 * Nginx cache configuration constants (wp-config.php):
 * - ICC_GG_SUPER_CACHE_NGINX_ENABLED
 * - ICC_GG_SUPER_CACHE_NGINX_PATH (also honours NGINX_CACHE_PATH)
 * - ICC_GG_SUPER_CACHE_NGINX_AUTO_PURGE
 *
 * Actions:
 * - icc_gg_wordpress_super_cache_enable
 * - icc_gg_wordpress_super_cache_disable
 * - icc_gg_wordpress_super_cache_update_dropin
 * - icc_gg_wordpress_super_cache_delete
 * - icc_gg_wordpress_super_cache_get
 * - icc_gg_wordpress_super_cache_get_multiple
 * - icc_gg_wordpress_super_cache_set
 * - icc_gg_wordpress_super_cache_flush
 * - icc_gg_wordpress_super_cache_flush_group
 * - icc_gg_wordpress_super_cache_error
 *
 * Filters:
 * - icc_gg_wordpress_super_cache_expiration
 * - icc_gg_wordpress_super_cache_get_value
 * - icc_gg_wordpress_super_cache_add_non_persistent_groups
 * - icc_gg_wordpress_super_cache_validate_dropin
 * - icc_gg_wordpress_super_cache_manager_capability
 */

/*
 * Hard requirement: PHP 8.2+.
 *
 * Kept in sync with the "Requires PHP" plugin header, readme.txt and
 * composer.json. WordPress validates the header on install and activation, but
 * a site can still auto-update the plugin while running an older PHP version,
 * so the plugin disables itself and explains why instead of failing later.
 */
define( 'ICC_GG_WORDPRESS_SUPER_CACHE_MIN_PHP', '8.2' );

if ( version_compare( PHP_VERSION, ICC_GG_WORDPRESS_SUPER_CACHE_MIN_PHP, '<' ) ) {

	/**
	 * Admin notice shown when the site runs an unsupported PHP version.
	 *
	 * @return void
	 */
	function icc_gg_wordpress_super_cache_php_version_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					// translators: 1: plugin name, 2: required PHP version, 3: current PHP version.
					__( '%1$s requires PHP %2$s or later. This site runs PHP %3$s, so the plugin has been deactivated.', 'icc-gg-wordpress-super-cache' ),
					'ICC.gg WordPress Super Cache',
					ICC_GG_WORDPRESS_SUPER_CACHE_MIN_PHP,
					PHP_VERSION
				)
			)
		);
	}

	/**
	 * Deactivate the plugin when the site runs an unsupported PHP version.
	 *
	 * @return void
	 */
	function icc_gg_wordpress_super_cache_php_version_deactivate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		deactivate_plugins( plugin_basename( __FILE__ ) );

		// Drop the "Plugin activated." notice for a plugin that never started.
		if ( isset( $_GET['activate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			unset( $_GET['activate'] );
		}
	}

	add_action( 'admin_notices', 'icc_gg_wordpress_super_cache_php_version_notice' );
	add_action( 'admin_init', 'icc_gg_wordpress_super_cache_php_version_deactivate' );

	return;
}

define( 'ICC_GG_WORDPRESS_SUPER_CACHE_FILE', __FILE__ );
define( 'ICC_GG_WORDPRESS_SUPER_CACHE_BASENAME', plugin_basename( ICC_GG_WORDPRESS_SUPER_CACHE_FILE ) );
define( 'ICC_GG_WORDPRESS_SUPER_CACHE_PLUGIN_DIR', plugin_dir_url( ICC_GG_WORDPRESS_SUPER_CACHE_FILE ) );
define( 'ICC_GG_WORDPRESS_SUPER_CACHE_PLUGIN_PATH', __DIR__ );
define( 'ICC_GG_WORDPRESS_SUPER_CACHE_VERSION', '2.0.0' );

// Backward-compatible aliases for the legacy bootstrap constants.
if ( ! defined( 'WP_REDIS_FILE' ) ) {
	define( 'WP_REDIS_FILE', ICC_GG_WORDPRESS_SUPER_CACHE_FILE );
}

if ( ! defined( 'WP_REDIS_BASENAME' ) ) {
	define( 'WP_REDIS_BASENAME', ICC_GG_WORDPRESS_SUPER_CACHE_BASENAME );
}

if ( ! defined( 'WP_REDIS_PLUGIN_DIR' ) ) {
	define( 'WP_REDIS_PLUGIN_DIR', ICC_GG_WORDPRESS_SUPER_CACHE_PLUGIN_DIR );
}

if ( ! defined( 'WP_REDIS_PLUGIN_PATH' ) ) {
	define( 'WP_REDIS_PLUGIN_PATH', ICC_GG_WORDPRESS_SUPER_CACHE_PLUGIN_PATH );
}

if ( ! defined( 'WP_REDIS_VERSION' ) ) {
	define( 'WP_REDIS_VERSION', ICC_GG_WORDPRESS_SUPER_CACHE_VERSION );
}

/**
 * ICC_GG_WordPress_Super_Cache class.
 *
 * Defines plugin bootstrap and autoloading functionality.
 *
 * @package ICC_GG_WordPress_Super_Cache
 * @category General
 */
if ( ! class_exists( 'ICC_GG_WordPress_Super_Cache' ) ) {
class ICC_GG_WordPress_Super_Cache {

	/**
	 * Singleton instance of self
	 *
	 * @var ICC_GG_WordPress_Super_Cache
	 */
	protected static $_instance = null;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '2.0.0';

	/**
	 * Simple autoloader.
	 *
	 * Maps prefixed class names to lowercase, dash-separated file names.
	 *
	 * @param string $class The class name.
	 *
	 * @return void
	 */
	public static function autoload( $class ) {
		$prefix = 'ICC_GG_WordPress_Super_Cache_';

		if ( stripos( $class, $prefix ) !== 0 ) {
			return;
		}

		$filename = substr( $class, strlen( $prefix ) );
		$filename = strtolower( str_replace( '_', '-', $filename ) );

		$filepath = __DIR__ . '/includes/class-' . $filename . '.php';

		if ( file_exists( $filepath ) ) {
			require_once $filepath;
		}
	}

	/**
	 * Instantiate the plugin and hook into WordPress.
	 *
	 * @return void
	 */
	public static function bootstrap() {
		// Register the plugin's custom autoloader before instantiating classes.
		spl_autoload_register( array( __CLASS__, 'autoload' ) );

		ICC_GG_WordPress_Super_Cache_Plugin::instance();

		register_activation_hook(
			ICC_GG_WORDPRESS_SUPER_CACHE_FILE,
			array( 'ICC_GG_WordPress_Super_Cache_Plugin', 'on_activation' )
		);

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			add_action(
				'plugins_loaded',
				function () {
					WP_CLI::add_command( 'icc-gg-wordpress-super-cache', ICC_GG_WordPress_Super_Cache_CLI_Commands::class );
				}
			);
		}
	}

	/**
	 * Create (if needed) and return a singleton of self.
	 *
	 * @return ICC_GG_WordPress_Super_Cache
	 */
	public static function instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
			self::bootstrap();
		}

		return self::$_instance;
	}
}
ICC_GG_WordPress_Super_Cache::instance();

if ( ! function_exists( 'icc_gg_wordpress_super_cache' ) ) {
	/**
	 * Returns the plugin instance.
	 *
	 * @return ICC_GG_WordPress_Super_Cache_Plugin
	 */
	function icc_gg_wordpress_super_cache() {
		return ICC_GG_WordPress_Super_Cache_Plugin::instance();
	}
}

/**
 * Initialize the GitHub updater.
 *
 * Runs on `init` and also during the WordPress cron job so the plugin can be
 * updated automatically, mirroring the updater bootstrap used by plugins that
 * rely on a custom update server.
 *
 * @return void
 */
function icc_gg_wordpress_super_cache_init_github_updater() {
	$doing_cron = defined( 'DOING_CRON' ) && DOING_CRON;
	if ( ! current_user_can( 'manage_options' ) && ! $doing_cron ) {
		return;
	}

	new ICC_GG_WordPress_Super_Cache_Github_Updater(
		ICC_GG_WORDPRESS_SUPER_CACHE_BASENAME,
		ICC_GG_WordPress_Super_Cache::VERSION
	);
}
add_action( 'init', 'icc_gg_wordpress_super_cache_init_github_updater' );

} // End if ( ! class_exists( 'ICC_GG_WordPress_Super_Cache' ) ).
