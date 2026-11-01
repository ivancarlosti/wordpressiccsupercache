<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * WP-CLI commands class.
 *
 * @package   ICC_GG_WordPress_Super_Cache
 * @category  General
 * @author    Ivan Carlos
 * @copyright 2007-2026 Ivan Carlos Consultoria
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0-or-later
 */

/**
 * Enables, disabled and checks the status of the object cache.
 * To flush call `wp cache flush`.
 *
 * @package wp-cli
 */
class ICC_GG_WordPress_Super_Cache_CLI_Commands extends WP_CLI_Command
{
	/**
	 * Show the Redis object cache status and (when possible) client.
	 *
	 * ## EXAMPLES
	 *
	 *     wp icc-gg-wordpress-super-cache status
	 */
	public function status()
	{
		$roc = ICC_GG_WordPress_Super_Cache_Plugin::instance();

		require_once __DIR__ . '/diagnostics.php';
	}

	/**
	 * Enables the Redis object cache.
	 *
	 * Default behavior is to create the object cache drop-in,
	 * unless an unknown object cache drop-in is present.
	 *
	 * ## EXAMPLES
	 *
	 *     wp icc-gg-wordpress-super-cache enable
	 */
	public function enable()
	{
		global $wp_filesystem;

		$plugin = ICC_GG_WordPress_Super_Cache_Plugin::instance();

		if ( $plugin->object_cache_dropin_exists() ) {

			if ( $plugin->validate_object_cache_dropin() ) {
				WP_CLI::line( __( 'Redis object cache already enabled.', 'icc-gg-wordpress-super-cache' ) );
			} else {
				WP_CLI::error( __( 'A foreign object cache drop-in was found. To use Redis for object caching, run: `wp icc-gg-wordpress-super-cache update-dropin`.', 'icc-gg-wordpress-super-cache' ) );
			}
		} else {
			$flush = $this->flush_redis();

			if ( is_string( $flush ) ) {
				// translators: %s = The Redis connection error message.
				WP_CLI::error( sprintf( __( "Object cache could not be enabled. Redis server is unreachable: %s", 'icc-gg-wordpress-super-cache' ), $flush ) );
			}

			WP_Filesystem();

			$copy = $wp_filesystem->copy(
				ICC_GG_WORDPRESS_SUPER_CACHE_PLUGIN_PATH . '/includes/object-cache.php',
				WP_CONTENT_DIR . '/object-cache.php',
				true,
				FS_CHMOD_FILE
			);

			/**
			 * Fires on cache enable event
			 *
			 * @since 1.3.5
			 * @param bool $result Whether the filesystem event (copy of the `object-cache.php` file) was successful.
			 */
			do_action( 'icc_gg_wordpress_super_cache_enable', $copy );

			if ( $copy ) {
				WP_CLI::success( __( 'Object cache enabled.', 'icc-gg-wordpress-super-cache' ) );
			} else {
				WP_CLI::error( __( 'Object cache could not be enabled.', 'icc-gg-wordpress-super-cache' ) );
			}
		}

	}

	/**
	 * Disables the Redis object cache.
	 *
	 * Default behavior is to delete the object cache drop-in,
	 * unless an unknown object cache drop-in is present.
	 *
	 * ## EXAMPLES
	 *
	 *     wp icc-gg-wordpress-super-cache disable
	 */
	public function disable()
	{
		global $wp_filesystem;

		$plugin = ICC_GG_WordPress_Super_Cache_Plugin::instance();

		if ( ! $plugin->object_cache_dropin_exists() ) {

			WP_CLI::error( __( 'No object cache drop-in found.', 'icc-gg-wordpress-super-cache' ) );

		} else {

			if ( ! $plugin->validate_object_cache_dropin() ) {

				WP_CLI::error( __( 'A foreign object cache drop-in was found. To use Redis for object caching, run: `wp icc-gg-wordpress-super-cache update-dropin`.', 'icc-gg-wordpress-super-cache' ) );

			} else {

				WP_Filesystem();

				$result = $wp_filesystem->delete( WP_CONTENT_DIR . '/object-cache.php' );

				/**
				 * Fires on cache disable event
				 *
				 * @param bool $result Whether the deletion of the `object-cache.php` drop-in was successful.
				 * @since 1.3.5
				 */
				do_action( 'icc_gg_wordpress_super_cache_disable', $result );

				if ( $result ) {
					$this->flush_redis();

					WP_CLI::success( __( 'Object cache disabled.', 'icc-gg-wordpress-super-cache' ) );
				} else {
					WP_CLI::error( __( 'Object cache could not be disabled.', 'icc-gg-wordpress-super-cache' ) );
				}
			}
		}

	}

	/**
	 * Updates the Redis object cache drop-in.
	 *
	 * Default behavior is to overwrite any existing object cache drop-in.
	 *
	 * ## EXAMPLES
	 *
	 *     wp icc-gg-wordpress-super-cache update-dropin
	 *
	 * @subcommand update-dropin
	 */
	public function update_dropin()
	{
		global $wp_filesystem;

		WP_Filesystem();

		$copy = $wp_filesystem->copy(
			ICC_GG_WORDPRESS_SUPER_CACHE_PLUGIN_PATH . '/includes/object-cache.php',
			WP_CONTENT_DIR . '/object-cache.php',
			true,
			FS_CHMOD_FILE
		);

		/**
		 * Fires on cache update-dropin event
		 *
		 * @param bool $result Whether the `object-cache.php` drop-in was updated successful.
		 * @since 1.3.5
		 */
		do_action( 'icc_gg_wordpress_super_cache_update_dropin', $copy );

		if ( $copy ) {
			$flush = $this->flush_redis();

			if ( is_string( $flush ) ) {
				// translators: %s = The Redis connection error message.
				WP_CLI::error( sprintf( __( "Object cache drop-in could not be updated. Redis server is unreachable: %s", 'icc-gg-wordpress-super-cache' ), $flush ) );
			}

			WP_CLI::success( __( 'Updated object cache drop-in and enabled Redis object cache.', 'icc-gg-wordpress-super-cache' ) );
		} else {
			WP_CLI::error( __( 'Object cache drop-in could not be updated.', 'icc-gg-wordpress-super-cache' ) );
		}

	}

	/**
	 * Shows the Nginx page cache status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp icc-gg-wordpress-super-cache nginx-status
	 *
	 * @subcommand nginx-status
	 */
	public function nginx_status()
	{
		$nginx = ICC_GG_WordPress_Super_Cache_Nginx_Cache::instance();
		$stats = $nginx->get_cache_stats();

		WP_CLI::line( sprintf( '%s: %s', __( 'Nginx', 'icc-gg-wordpress-super-cache' ), $nginx->get_status() ) );

		WP_CLI::line(
			sprintf(
				'%s: %s',
				__( 'Cache Zone Path', 'icc-gg-wordpress-super-cache' ),
				$nginx->get_cache_path() !== '' ? $nginx->get_cache_path() : __( '(not set)', 'icc-gg-wordpress-super-cache' )
			)
		);

		WP_CLI::line(
			sprintf(
				'%s: %s',
				__( 'Auto Purge', 'icc-gg-wordpress-super-cache' ),
				$nginx->auto_purge_enabled() ? __( 'Enabled', 'icc-gg-wordpress-super-cache' ) : __( 'Disabled', 'icc-gg-wordpress-super-cache' )
			)
		);

		WP_CLI::line( sprintf( '%s: %s', __( 'Files', 'icc-gg-wordpress-super-cache' ), number_format_i18n( $stats['files'] ) ) );
		WP_CLI::line( sprintf( '%s: %s', __( 'Size', 'icc-gg-wordpress-super-cache' ), size_format( $stats['size'] ) ) );
	}

	/**
	 * Purges the Nginx page cache zone.
	 *
	 * ## EXAMPLES
	 *
	 *     wp icc-gg-wordpress-super-cache nginx-purge
	 *
	 * @subcommand nginx-purge
	 */
	public function nginx_purge()
	{
		$result = ICC_GG_WordPress_Super_Cache_Nginx_Cache::instance()->purge_zone();

		if ( true === $result ) {
			WP_CLI::success( __( 'Nginx cache purged.', 'icc-gg-wordpress-super-cache' ) );
		} elseif ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		} else {
			WP_CLI::warning( __( 'Nginx cache purge was skipped.', 'icc-gg-wordpress-super-cache' ) );
		}
	}

	/**
	 * Flush the Redis cache via Predis.
	 *
	 * @return bool|string
	 */
	protected function flush_redis()
	{
		try {
			return (new ICC_GG_WordPress_Super_Cache_Predis)->flushOrFail();
		} catch ( Exception $exception ) {
			error_log( $exception ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			return $exception->getMessage();
		}
	}
}
