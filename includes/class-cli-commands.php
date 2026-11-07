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
	 * Show the page cache registry and the local page cache status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp icc-gg-wordpress-super-cache page-cache-status
	 *
	 * @subcommand page-cache-status
	 */
	public function page_cache_status()
	{
		$registry = ICC_GG_WordPress_Super_Cache_Page_Cache::instance();
		$local = ICC_GG_WordPress_Super_Cache_Local_Cache::instance();
		$stats = $local->cache_stats();

		WP_CLI::line( sprintf( '%s: %s', __( 'Page Cache', 'icc-gg-wordpress-super-cache' ), $registry->get_status() ) );

		WP_CLI::line(
			sprintf(
				'%s: %s',
				__( 'Active Backend', 'icc-gg-wordpress-super-cache' ),
				$registry->active() !== '' ? $registry->label( $registry->active() ) : __( '(none)', 'icc-gg-wordpress-super-cache' )
			)
		);

		WP_CLI::line(
			sprintf(
				'%s: %s',
				__( 'Conflicts', 'icc-gg-wordpress-super-cache' ),
				implode( ', ', array_map( [ $registry, 'label' ], $registry->enabled_backends() ) )
			)
		);

		WP_CLI::line( sprintf( '%s: %s', __( 'Local Page Cache', 'icc-gg-wordpress-super-cache' ), $local->get_status() ) );
		WP_CLI::line( sprintf( '%s: %s', __( 'Loader', 'icc-gg-wordpress-super-cache' ), $local->bootstrap_mode() ) );
		WP_CLI::line( sprintf( '%s: %s', __( 'Cache Directory', 'icc-gg-wordpress-super-cache' ), $local->cache_root() ) );
		WP_CLI::line( sprintf( '%s: %s', __( 'Cached Pages', 'icc-gg-wordpress-super-cache' ), number_format_i18n( $stats['pages'] ) ) );
		WP_CLI::line( sprintf( '%s: %s', __( 'Cache Files', 'icc-gg-wordpress-super-cache' ), number_format_i18n( $stats['files'] ) ) );
		WP_CLI::line( sprintf( '%s: %s', __( 'Cache Size', 'icc-gg-wordpress-super-cache' ), size_format( $stats['size'] ) ) );
		WP_CLI::line( sprintf( '%s: %s', __( 'Preload Queue', 'icc-gg-wordpress-super-cache' ), number_format_i18n( count( $local->get_preload_queue() ) ) ) );
	}

	/**
	 * Enables the local page cache.
	 *
	 * ## EXAMPLES
	 *
	 *     wp icc-gg-wordpress-super-cache page-cache-enable
	 *
	 * @subcommand page-cache-enable
	 */
	public function page_cache_enable()
	{
		$this->bootstrap_filesystem();

		$registry = ICC_GG_WordPress_Super_Cache_Page_Cache::instance();
		$local = ICC_GG_WordPress_Super_Cache_Local_Cache::instance();

		$result = $registry->set_active( ICC_GG_WordPress_Super_Cache_Page_Cache::LOCAL, true );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		$result = $local->install();

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success( __( 'Local page cache enabled.', 'icc-gg-wordpress-super-cache' ) );

		$wp_config_error = $local->get_last_error();

		if ( $wp_config_error instanceof WP_Error ) {
			WP_CLI::warning( $wp_config_error->get_error_message() );
		}
	}

	/**
	 * Disables the local page cache.
	 *
	 * ## EXAMPLES
	 *
	 *     wp icc-gg-wordpress-super-cache page-cache-disable
	 *
	 * @subcommand page-cache-disable
	 */
	public function page_cache_disable()
	{
		$this->bootstrap_filesystem();

		ICC_GG_WordPress_Super_Cache_Page_Cache::instance()->set_active(
			ICC_GG_WordPress_Super_Cache_Page_Cache::LOCAL,
			false
		);

		$result = ICC_GG_WordPress_Super_Cache_Local_Cache::instance()->install();

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success( __( 'Local page cache disabled.', 'icc-gg-wordpress-super-cache' ) );
	}

	/**
	 * Clears the local page cache.
	 *
	 * ## OPTIONS
	 *
	 * [<url>...]
	 * : Optional URLs to purge instead of the whole cache.
	 *
	 * ## EXAMPLES
	 *
	 *     wp icc-gg-wordpress-super-cache page-cache-purge
	 *     wp icc-gg-wordpress-super-cache page-cache-purge /about/ /blog/*
	 *
	 * @subcommand page-cache-purge
	 */
	public function page_cache_purge( $args = [] )
	{
		$local = ICC_GG_WordPress_Super_Cache_Local_Cache::instance();

		if ( ! $args ) {
			$local->purge();

			WP_CLI::success( __( 'Local page cache purged.', 'icc-gg-wordpress-super-cache' ) );

			return;
		}

		$deleted = $local->purge_urls( $args );

		WP_CLI::success(
			sprintf(
				// translators: %d = Number of purged cache entries.
				_n( '%d cached entry purged.', '%d cached entries purged.', $deleted, 'icc-gg-wordpress-super-cache' ),
				$deleted
			)
		);
	}

	/**
	 * Preloads the local page cache.
	 *
	 * ## EXAMPLES
	 *
	 *     wp icc-gg-wordpress-super-cache page-cache-preload
	 *
	 * @subcommand page-cache-preload
	 */
	public function page_cache_preload()
	{
		$local = ICC_GG_WordPress_Super_Cache_Local_Cache::instance();

		if ( ! $local->is_enabled() ) {
			WP_CLI::error( __( 'The local page cache is disabled.', 'icc-gg-wordpress-super-cache' ) );
		}

		$queued = $local->start_preload();
		$batch = $local->run_preload_batch( 10 );

		WP_CLI::success(
			sprintf(
				// translators: 1: Number of queued URLs, 2: Number of URLs requested in this run.
				__( '%1$d URLs queued, %2$d requested now. The rest runs through WP-Cron.', 'icc-gg-wordpress-super-cache' ),
				$queued,
				$batch['processed']
			)
		);
	}

	/**
	 * Show the Varnish cache status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp icc-gg-wordpress-super-cache varnish-status
	 *
	 * @subcommand varnish-status
	 */
	public function varnish_status()
	{
		$varnish = ICC_GG_WordPress_Super_Cache_Varnish_Cache::instance();
		$endpoint = $varnish->endpoint_url();

		WP_CLI::line( sprintf( '%s: %s', __( 'Varnish', 'icc-gg-wordpress-super-cache' ), $varnish->get_status() ) );
		WP_CLI::line( sprintf( '%s: %s', __( 'Endpoint', 'icc-gg-wordpress-super-cache' ), is_wp_error( $endpoint ) ? '(invalid)' : $endpoint ) );
		WP_CLI::line( sprintf( '%s: %s', __( 'Method', 'icc-gg-wordpress-super-cache' ), strtoupper( (string) $varnish->get_setting( 'method' ) ) ) );
	}

	/**
	 * Tests the Varnish connection.
	 *
	 * ## EXAMPLES
	 *
	 *     wp icc-gg-wordpress-super-cache varnish-test
	 *
	 * @subcommand varnish-test
	 */
	public function varnish_test()
	{
		$result = ICC_GG_WordPress_Super_Cache_Varnish_Cache::instance()->test();

		WP_CLI::line( $result['message'] );

		if ( ! empty( $result['probe']['headers'] ) ) {
			foreach ( $result['probe']['headers'] as $header => $value ) {
				WP_CLI::line( sprintf( '  %s: %s', $header, $value ) );
			}
		}

		if ( empty( $result['ok'] ) ) {
			WP_CLI::error( __( 'Varnish could not be reached.', 'icc-gg-wordpress-super-cache' ) );
		}

		WP_CLI::success( __( 'Varnish answered.', 'icc-gg-wordpress-super-cache' ) );
	}

	/**
	 * Purges a URL from Varnish.
	 *
	 * ## OPTIONS
	 *
	 * [<url>]
	 * : URL to purge. Defaults to the site home page.
	 *
	 * ## EXAMPLES
	 *
	 *     wp icc-gg-wordpress-super-cache varnish-purge /about/
	 *
	 * @subcommand varnish-purge
	 */
	public function varnish_purge( $args = [] )
	{
		$url = isset( $args[0] ) ? $args[0] : '';
		$result = ICC_GG_WordPress_Super_Cache_Varnish_Cache::instance()->purge( $url );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success( __( 'Varnish cache purged.', 'icc-gg-wordpress-super-cache' ) );
	}

	/**
	 * Optimizes a batch of images from the media library.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<limit>]
	 * : Number of images to process. Defaults to the configured batch size.
	 *
	 * [--loop]
	 * : Keep processing batches until the library is done.
	 *
	 * ## EXAMPLES
	 *
	 *     wp icc-gg-wordpress-super-cache images-optimize --limit=10
	 *
	 * @subcommand images-optimize
	 */
	public function images_optimize( $args = [], $assoc_args = [] )
	{
		$optimizer = ICC_GG_WordPress_Super_Cache_Image_Optimizer::instance();

		$limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : null;
		$loop = ! empty( $assoc_args['loop'] );

		do {
			$result = $optimizer->optimize_batch( $limit );

			WP_CLI::line(
				sprintf(
					// translators: 1: Processed images, 2: Failed images, 3: Pending images.
					__( 'Processed %1$d, failed %2$d, pending %3$d.', 'icc-gg-wordpress-super-cache' ),
					$result['processed'],
					$result['failed'],
					$result['remaining']
				)
			);

			foreach ( $result['errors'] as $error ) {
				WP_CLI::warning( $error );
			}
		} while ( $loop && ! $result['done'] );

		WP_CLI::success( __( 'Image optimization finished.', 'icc-gg-wordpress-super-cache' ) );
	}

	/**
	 * Loads the filesystem API for the WP-CLI commands.
	 *
	 * @return void
	 */
	protected function bootstrap_filesystem()
	{
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		WP_Filesystem();
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
