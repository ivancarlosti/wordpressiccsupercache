<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Main plugin class.
 *
 * @package   ICC_GG_WordPress_Super_Cache
 * @category  General
 * @author    Ivan Carlos
 * @copyright 2007-2026 Ivan Carlos Consultoria
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0-or-later
 */

/**
 * Main plugin class definition
 */
class ICC_GG_WordPress_Super_Cache_Plugin
{
	/**
	 * Settings page uri
	 *
	 * @var string $page
	 */
	private $page = '';

	/**
	 * Settings page slug
	 *
	 * @var string $screen
	 */
	private $screen = '';

	/**
	 * Allowed setting page actions
	 *
	 * @var string[] $actions
	 */
	private $actions = [
		'enable-cache',
		'disable-cache',
		'flush-cache',
		'update-dropin',
		'purge-nginx-cache',
		'enable-local-cache',
		'disable-local-cache',
		'purge-local-cache',
		'preload-local-cache',
		'replace-advanced-cache',
		'write-page-cache-loader',
		'reset-local-exclusions',
		'revert-wp-config',
		'enable-varnish-cache',
		'disable-varnish-cache',
		'purge-varnish-cache',
		'test-varnish-cache',
		'reset-image-optimizer',
	];

	/**
	 * Plugin instance property
	 *
	 * @var ICC_GG_WordPress_Super_Cache_Plugin|null
	 */
	private static $instance;

	/**
	 * Plugin instantiation method
	 *
	 * @return ICC_GG_WordPress_Super_Cache_Plugin
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
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		if ( is_multisite() ) {
			$this->page = 'settings.php?page=icc-gg-wordpress-super-cache';
			$this->screen = 'settings_page_icc-gg-wordpress-super-cache-network';
		} else {
			$this->page = 'options-general.php?page=icc-gg-wordpress-super-cache';
			$this->screen = 'settings_page_icc-gg-wordpress-super-cache';
		}

		ICC_GG_WordPress_Super_Cache_Metrics::init();
		ICC_GG_WordPress_Super_Cache_Nginx_Cache::instance();
		ICC_GG_WordPress_Super_Cache_Page_Cache::instance();
		ICC_GG_WordPress_Super_Cache_Local_Cache::instance();
		ICC_GG_WordPress_Super_Cache_Varnish_Cache::instance();
		ICC_GG_WordPress_Super_Cache_Image_Optimizer::instance();

		$this->add_actions_and_filters();
	}

	/**
	 * Adds all necessary hooks
	 *
	 * @return void
	 */
	public function add_actions_and_filters()
	{
		add_action( 'deactivate_plugin', [ $this, 'on_deactivation' ] );
		add_action( 'admin_init', [ $this, 'maybe_update_dropin' ] );
		add_action( 'admin_init', [ $this, 'maybe_redirect' ] );
		add_action( 'init', [ $this, 'init' ] );

		add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu', [ $this, 'add_admin_menu_page' ] );

		add_action( 'admin_notices', [ $this, 'show_admin_notices' ] );
		add_action( 'network_admin_notices', [ $this, 'show_admin_notices' ] );

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_styles' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_redis_metrics' ] );

		add_action( 'admin_bar_menu', [ $this, 'render_admin_bar' ], 998 );

		add_action( sprintf( 'load-%s', $this->screen ), [ $this, 'do_admin_actions' ] );

		add_action( 'wp_dashboard_setup', [ $this, 'setup_dashboard_widget' ] );
		add_action( 'wp_network_dashboard_setup', [ $this, 'setup_dashboard_widget' ] );

		add_action( 'wp_ajax_icc_gg_wordpress_super_cache_flush_cache', [ $this, 'ajax_flush_cache' ] );
		add_action( 'wp_ajax_icc_gg_wordpress_super_cache_optimize_images', [ $this, 'ajax_optimize_images' ] );

		add_filter( 'plugin_row_meta', [ $this, 'add_plugin_row_meta' ], 10, 2 );
		add_filter( sprintf( '%splugin_action_links_%s', is_multisite() ? 'network_admin_' : '', ICC_GG_WORDPRESS_SUPER_CACHE_BASENAME ), [ $this, 'add_plugin_actions_links' ] );

		add_action( 'wp_head', [ $this, 'register_shutdown_hooks' ] );

		add_action( 'icc_gg_wordpress_super_cache_enable', [ $this, 'maybe_delete_transients' ] );

		add_filter( 'qm/collectors', [ $this, 'register_qm_collector' ], 25 );
		add_filter( 'qm/outputter/html', [ $this, 'register_qm_output' ] );

		add_filter( 'perflab_disable_object_cache_dropin', '__return_true' );
		add_filter( 'w3tc_config_item_objectcache.enabled', '__return_false' );
		add_action( 'litespeed_init', [ $this, 'litespeed_disable_objectcache' ] );
	}

	/**
	 * Callback of the `init` hook.
	 *
	 * @return void
	 */
	public function init()
	{
		if ( is_admin() && ! wp_next_scheduled( 'icc_gg_wordpress_super_cache_discard_metrics' ) ) {
			wp_schedule_event( time(), 'hourly', 'icc_gg_wordpress_super_cache_discard_metrics' );
		}
	}

	/**
	 * Adds a submenu page to "Settings"
	 *
	 * @return void
	 */
	public function add_admin_menu_page()
	{
		add_submenu_page(
			is_multisite() ? 'settings.php' : 'options-general.php',
			__( 'ICC.gg WordPress Super Cache', 'icc-gg-wordpress-super-cache' ),
			__( 'Super Cache', 'icc-gg-wordpress-super-cache' ),
			$this->manage_redis_capability(),
			'icc-gg-wordpress-super-cache',
			[ $this, 'show_admin_page' ]
		);
	}

	/**
	 * Displays the settings page
	 *
	 * @return void
	 */
	public function show_admin_page()
	{
		// Request filesystem credentials?
		if ( isset( $_GET['_wpnonce'], $_GET['action'] ) ) {
			$action = sanitize_key( $_GET['action'] );
			$nonce = sanitize_key( $_GET['_wpnonce'] );

			foreach ( $this->actions as $name ) {
				// Nonce verification.
				if ( $action === $name && wp_verify_nonce( $nonce, $action ) ) {
					$url = $this->action_link( $action );

					if ( $this->initialize_filesystem( $url ) === false ) {
						return; // Request filesystem credentials.
					}
				}
			}
		}

		if ( wp_next_scheduled( 'icc_gg_wordpress_super_cache_gather_metrics' ) ) {
			wp_clear_scheduled_hook( 'icc_gg_wordpress_super_cache_gather_metrics' );
		}

		ICC_GG_WordPress_Super_Cache_UI::register_tab(
			'redis',
			__( 'Redis Object Cache', 'icc-gg-wordpress-super-cache' ),
			[ 'default' => true ]
		);

		ICC_GG_WordPress_Super_Cache_UI::register_tab(
			'local',
			__( 'Page Cache', 'icc-gg-wordpress-super-cache' )
		);

		ICC_GG_WordPress_Super_Cache_UI::register_tab(
			'varnish',
			__( 'Varnish Cache', 'icc-gg-wordpress-super-cache' )
		);

		ICC_GG_WordPress_Super_Cache_UI::register_tab(
			'nginx',
			__( 'nginx FastCGI', 'icc-gg-wordpress-super-cache' )
		);

		ICC_GG_WordPress_Super_Cache_UI::register_tab(
			'images',
			__( 'Optimize Images', 'icc-gg-wordpress-super-cache' )
		);

		ICC_GG_WordPress_Super_Cache_UI::register_tab(
			'diagnostics',
			__( 'Diagnostics', 'icc-gg-wordpress-super-cache' )
		);

		// Show the admin page.
		require_once ICC_GG_WORDPRESS_SUPER_CACHE_PLUGIN_PATH . '/includes/ui/settings.php';
	}

	/**
	 * Adds the dashboard metrics widget
	 *
	 * @return void
	 */
	public function setup_dashboard_widget()
	{
		if ( ! $this->current_user_can_manage_redis() ) {
			return;
		}

		if ( ! ICC_GG_WordPress_Super_Cache_Metrics::is_enabled() ) {
			return;
		}

		wp_add_dashboard_widget(
			'dashboard_icc_gg_wordpress_super_cache',
			__( 'ICC.gg WordPress Super Cache', 'icc-gg-wordpress-super-cache' ),
			[ $this, 'show_dashboard_widget' ],
			null,
			null,
			'normal',
			'high'
		);
	}

	/**
	 * Displays the dashboard widget
	 *
	 * @return void
	 */
	public function show_dashboard_widget()
	{
		require_once ICC_GG_WORDPRESS_SUPER_CACHE_PLUGIN_PATH . '/includes/ui/widget.php';
	}

	/**
	 * Adds the settings page to the plugin action links on the plugin page
	 *
	 * @param string[] $links The current plugin action links.
	 * @return string[]
	 */
	public function add_plugin_actions_links( $links )
	{
		$settings = sprintf(
			'<a href="%s">%s</a>',
			network_admin_url( $this->page ),
			esc_html__( 'Settings', 'icc-gg-wordpress-super-cache' )
		);

		return array_merge( [ $settings ], $links );
	}

	/**
	 * Adds plugin meta links on the plugin page.
	 *
	 * @param string[] $plugin_meta An array of the plugin's metadata.
	 * @param string   $plugin_file Path to the plugin file relative to the plugins directory.
	 * @return string[] An array of the plugin's metadata.
	 */
	public function add_plugin_row_meta( array $plugin_meta, $plugin_file )
	{
		if ( strpos( $plugin_file, 'icc-gg-wordpress-super-cache.php' ) === false ) {
			return $plugin_meta;
		}

		$plugin_meta[] = sprintf(
			'<a href="%1$s" target="_blank" rel="noopener">%2$s</a>',
			'https://github.com/ivancarlosti/wordpressiccsupercache/releases',
			esc_html__( 'Check for updates', 'icc-gg-wordpress-super-cache' )
		);

		return $plugin_meta;
	}

	/**
	 * Enqueues admin style resources
	 *
	 * @return void
	 */
	public function enqueue_admin_styles()
	{
		$screen = get_current_screen();

		if ( ! isset( $screen->id ) ) {
			return;
		}

		$screens = [
			$this->screen,
			'dashboard',
			'dashboard-network',
		];

		if ( ! in_array( $screen->id, $screens, true ) ) {
			return;
		}

		wp_enqueue_style(
			'icc-gg-wordpress-super-cache',
			trailingslashit( ICC_GG_WORDPRESS_SUPER_CACHE_PLUGIN_DIR ) . 'assets/css/admin.css',
			[],
			ICC_GG_WORDPRESS_SUPER_CACHE_VERSION
		);
	}

	/**
	 * Enqueues admin script resources
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts()
	{
		$screen = get_current_screen();

		if ( ! isset( $screen->id ) ) {
			return;
		}

		$screens = [
			$this->screen,
			'dashboard',
			'dashboard-network',
		];

		if ( ! in_array( $screen->id, $screens, true ) ) {
			return;
		}

		$clipboard = file_exists( ABSPATH . WPINC . '/js/clipboard.min.js' );

		wp_enqueue_script(
			'icc-gg-wordpress-super-cache',
			plugins_url( 'assets/js/admin.js', ICC_GG_WORDPRESS_SUPER_CACHE_FILE ),
			array_merge( [ 'jquery', 'underscore' ], $clipboard ? [ 'clipboard' ] : [] ),
			ICC_GG_WORDPRESS_SUPER_CACHE_VERSION,
			true
		);

		wp_localize_script(
			'icc-gg-wordpress-super-cache',
			'icc_gg_wordpress_super_cache',
			[
				'jQuery' => 'jQuery',
				'is_wp7' => version_compare( get_bloginfo( 'version' ), '7.0-dev', '>=' ),
				'l10n' => [
					'time' => __( 'Time', 'icc-gg-wordpress-super-cache' ),
					'bytes' => __( 'Bytes', 'icc-gg-wordpress-super-cache' ),
					'ratio' => __( 'Ratio', 'icc-gg-wordpress-super-cache' ),
					'calls' => __( 'Calls', 'icc-gg-wordpress-super-cache' ),
					'no_data' => __( 'Not enough data collected, yet.', 'icc-gg-wordpress-super-cache' ),
					'no_cache' => __( 'Enable object cache to collect data.', 'icc-gg-wordpress-super-cache' ),
					'optimizing' => __( 'Optimizing…', 'icc-gg-wordpress-super-cache' ),
					'optimize_done' => __( 'All images were optimized.', 'icc-gg-wordpress-super-cache' ),
					'optimize_error' => __( 'The images could not be optimized.', 'icc-gg-wordpress-super-cache' ),
				],
				'images' => [
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'action' => 'icc_gg_wordpress_super_cache_optimize_images',
					'nonce' => wp_create_nonce( 'icc_gg_wordpress_super_cache_images_batch' ),
					'batch' => (int) ICC_GG_WordPress_Super_Cache_Image_Optimizer::instance()->get_setting( 'batch' ),
				],
			]
		);
	}

	/**
	 * Enqueues scripts to display recorded metrics
	 *
	 * @return void
	 */
	public function enqueue_redis_metrics()
	{
		if ( ! ICC_GG_WordPress_Super_Cache_Metrics::is_enabled() ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! isset( $screen->id ) ) {
			return;
		}

		if ( ! in_array( $screen->id, [ $this->screen, 'dashboard', 'dashboard-network' ], true ) ) {
			return;
		}

		wp_enqueue_script(
			'icc-gg-wordpress-super-cache-charts',
			plugins_url( 'assets/js/apexcharts.min.js', ICC_GG_WORDPRESS_SUPER_CACHE_FILE ),
			[],
			ICC_GG_WORDPRESS_SUPER_CACHE_VERSION,
			true
		);

		if ( ! $this->get_redis_status() ) {
			return;
		}

		$min_time = $screen->id === $this->screen
			? ICC_GG_WordPress_Super_Cache_Metrics::max_time()
			: MINUTE_IN_SECONDS * 30;

		$metrics = ICC_GG_WordPress_Super_Cache_Metrics::get( $min_time );

		wp_localize_script( 'icc-gg-wordpress-super-cache', 'icc_gg_wordpress_super_cache_metrics', $metrics );
	}

	/**
	 * Registers a new cache collector for the Query Monitor plugin
	 *
	 * @param array $collectors Array of currently registered collectors.
	 * @return array
	 */
	public function register_qm_collector( array $collectors )
	{
		$collectors['cache'] = new ICC_GG_WordPress_Super_Cache_QM_Collector();

		return $collectors;
	}

	/**
	 * Registers a new cache output using our collector for the Query Monitor plugin
	 *
	 * @param array $output Array of current QM_Output handlers.
	 * @return array
	 */
	public function register_qm_output( $output )
	{
		$collector = \QM_Collectors::get( 'cache' );

		if (
			$collector instanceof ICC_GG_WordPress_Super_Cache_QM_Collector &&
			method_exists( 'QM_Output_Html', 'before_non_tabular_output' )
		) {
			$output['cache'] = new ICC_GG_WordPress_Super_Cache_QM_Output( $collector );
		}

		return $output;
	}

	/**
	 * Checks if the `object-cache.php` drop-in exists
	 *
	 * @return bool
	 */
	public function object_cache_dropin_exists()
	{
		return file_exists( WP_CONTENT_DIR . '/object-cache.php' );
	}

	/**
	 * Returns the `PluginURI` values used by earlier versions of the plugin.
	 *
	 * Existing installations still carry a drop-in file that was written before
	 * the plugin was renamed, so those URIs must keep validating as our own.
	 *
	 * @return string[]
	 */
	protected function legacy_plugin_uris()
	{
		return [
			'https://github.com/ivancarlosti/wordpressiccredisobjectcache',
		];
	}

	/**
	 * Checks whether a drop-in `PluginURI` belongs to this plugin.
	 *
	 * @param string $uri The `PluginURI` of the drop-in.
	 * @return bool
	 */
	protected function is_own_dropin_uri( $uri )
	{
		$plugin = get_plugin_data( ICC_GG_WORDPRESS_SUPER_CACHE_PLUGIN_PATH . '/includes/object-cache.php' );

		if ( isset( $plugin['PluginURI'] ) && $uri === $plugin['PluginURI'] ) {
			return true;
		}

		return in_array( $uri, $this->legacy_plugin_uris(), true );
	}

	/**
	 * Validates the `object-cache.php` drop-in
	 *
	 * @return bool
	 */
	public function validate_object_cache_dropin()
	{
		if ( ! $this->object_cache_dropin_exists() ) {
			return false;
		}

		$dropin = get_plugin_data( WP_CONTENT_DIR . '/object-cache.php' );
		$plugin = get_plugin_data( ICC_GG_WORDPRESS_SUPER_CACHE_PLUGIN_PATH . '/includes/object-cache.php' );

		/**
		 * Filters the drop-in validation state
		 *
		 * @since 2.0.16
		 * @param bool   $state      The validation state of the drop-in.
		 * @param string $dropin     The `PluginURI` of the drop-in.
		 * @param string $plugin     The `PluginURI` of the plugin.
		 */
		return apply_filters(
			'icc_gg_wordpress_super_cache_validate_dropin',
			$dropin['PluginURI'] === $plugin['PluginURI'] || $this->is_own_dropin_uri( $dropin['PluginURI'] ),
			$dropin['PluginURI'],
			$plugin['PluginURI']
		);
	}

	/**
	 * Checks if the `object-cache.php` drop-in is outdated
	 *
	 * @return bool
	 */
	public function object_cache_dropin_outdated()
	{
		if ( ! $this->object_cache_dropin_exists() ) {
			return false;
		}

		$dropin = get_plugin_data( WP_CONTENT_DIR . '/object-cache.php' );
		$plugin = get_plugin_data( ICC_GG_WORDPRESS_SUPER_CACHE_PLUGIN_PATH . '/includes/object-cache.php' );

		// Drop-ins created by earlier versions are always updated.
		if ( $dropin['PluginURI'] !== $plugin['PluginURI'] && $this->is_own_dropin_uri( $dropin['PluginURI'] ) ) {
			return true;
		}

		if ( $dropin['PluginURI'] === $plugin['PluginURI'] ) {
			return version_compare( $dropin['Version'], $plugin['Version'], '<' );
		}

		return false;
	}

	/**
	 * Retrieves the current human-readable status
	 *
	 * @return string
	 */
	public function get_status()
	{
		global $wp_object_cache;

		if ( defined( 'WP_REDIS_DISABLED' ) && WP_REDIS_DISABLED ) {
			return __( 'Disabled', 'icc-gg-wordpress-super-cache' );
		}

		if ( ! $this->object_cache_dropin_exists() ) {
			return __( 'Not enabled', 'icc-gg-wordpress-super-cache' );
		}

		if ( $this->object_cache_dropin_outdated() ) {
			return __( 'Drop-in is outdated', 'icc-gg-wordpress-super-cache' );
		}

		if ( ! $this->validate_object_cache_dropin() ) {
			return __( 'Drop-in is invalid', 'icc-gg-wordpress-super-cache' );
		}

		if ( method_exists( $wp_object_cache, 'redis_status' ) ) {
			return $wp_object_cache->redis_status()
				? __( 'Connected', 'icc-gg-wordpress-super-cache' )
				: __( 'Not connected', 'icc-gg-wordpress-super-cache' );
		}

		return __( 'Unknown', 'icc-gg-wordpress-super-cache' );
	}

	/**
	 * Retrieves the Redis connection status
	 *
	 * @return bool|null Boolean Redis connection status if available, null otherwise.
	 */
	public function get_redis_status()
	{
		global $wp_object_cache;

		if ( defined( 'WP_REDIS_DISABLED' ) && WP_REDIS_DISABLED ) {
			return null;
		}

		if ( $this->object_cache_dropin_outdated() ) {
			return null;
		}

		if ( ! $this->validate_object_cache_dropin() ) {
			return null;
		}

		if ( ! method_exists( $wp_object_cache, 'redis_status' ) ) {
			return null;
		}

		return $wp_object_cache->redis_status();
	}

	/**
	 * Check whether we can connect to Redis via Predis.
	 *
	 * @return bool|string
	 */
	public function check_redis_connection()
	{
		try {
			$predis = new ICC_GG_WordPress_Super_Cache_Predis();
			$predis->connect();
		} catch ( Exception $error ) {
			return $error->getMessage();
		}

		return true;
	}

	/**
	 * Returns the redis version if possible
	 *
	 * @see WP_Object_Cache::redis_version()
	 * @return null|string
	 */
	public function get_redis_version()
	{
		global $wp_object_cache;

		if ( defined( 'WP_REDIS_DISABLED' ) && WP_REDIS_DISABLED ) {
			return null;
		}

		if ( ! $this->validate_object_cache_dropin() || ! method_exists( $wp_object_cache, 'redis_version' ) ) {
			return null;
		}

		return $wp_object_cache->redis_version();
	}

	/**
	 * Returns the currently used redis client (if any)
	 *
	 * @return null|string
	 */
	public function get_redis_client_name()
	{
		global $wp_object_cache;

		if ( isset( $wp_object_cache->diagnostics[ 'client' ] ) ) {
			return $wp_object_cache->diagnostics[ 'client' ];
		}

		if ( ! defined( 'WP_REDIS_CLIENT' ) ) {
			return null;
		}

		return WP_REDIS_CLIENT;
	}

	/**
	 * Fetches the redis diagnostics data
	 *
	 * @return null|array
	 */
	public function get_diagnostics()
	{
		global $wp_object_cache;

		if ( ! $this->validate_object_cache_dropin() || ! property_exists( $wp_object_cache, 'diagnostics' ) ) {
			return null;
		}

		return $wp_object_cache->diagnostics;
	}

	/**
	 * Retrieves the redis prefix
	 *
	 * @return null|mixed
	 */
	public function get_redis_prefix()
	{
		return defined( 'WP_REDIS_PREFIX' ) ? WP_REDIS_PREFIX : null;
	}

	/**
	 * Retrieves the redis maximum time to live
	 *
	 * @return null|mixed
	 */
	public function get_redis_maxttl()
	{
		return defined( 'WP_REDIS_MAXTTL' ) ? WP_REDIS_MAXTTL : null;
	}

	/**
	 * Displays admin notices
	 *
	 * @return void
	 */
	public function show_admin_notices()
	{
		// Only show admin notices to users with the right capability.
		if ( ! $this->current_user_can_manage_redis() ) {
			return;
		}

		// Do not display the dropin message if you want
		if ( defined( 'WP_REDIS_DISABLE_DROPIN_BANNERS' ) && WP_REDIS_DISABLE_DROPIN_BANNERS ) {
			return;
		}

		if ( $this->object_cache_dropin_exists() ) {
			if ( $this->validate_object_cache_dropin() ) {
				if ( $this->object_cache_dropin_outdated() ) {
					$message = sprintf(
						// translators: %s = Action link to update the drop-in.
						__( 'The Redis object cache drop-in is outdated. Please <a href="%s">update the drop-in</a>.', 'icc-gg-wordpress-super-cache' ),
						$this->action_link( 'update-dropin' )
					);
				}
			} else {
				$message = sprintf(
					// translators: %s = Link to settings page.
					__( 'A foreign object cache drop-in was found. To use Redis for object caching, please <a href="%s">enable the drop-in</a>.', 'icc-gg-wordpress-super-cache' ),
					esc_url( network_admin_url( $this->page ) )
				);
			}

			if ( isset( $message ) ) {
				printf( '<div class="update-nag notice notice-warning inline">%s</div>', wp_kses_post( $message ) );
			}
		}
	}

	/**
	 * Display the admin bar menu item.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar
	 *
	 * @return void
	 */
	public function render_admin_bar( $wp_admin_bar )
	{
		if ( defined( 'WP_REDIS_DISABLE_ADMINBAR' ) && WP_REDIS_DISABLE_ADMINBAR ) {
			return;
		}

		if ( ! $this->current_user_can_manage_redis() ) {
			return;
		}

		$nodeTitle = __( 'Object Cache', 'icc-gg-wordpress-super-cache' );

		$style = preg_replace( '/\s+/', ' ', $this->admin_bar_style() );
		$script = preg_replace( '/\s+/', ' ', $this->admin_bar_script() );
		$html = "\n{$style}\n{$script}\n";

		$redis_status = $this->get_redis_status();

		$wp_admin_bar->add_node([
			'id' => 'icc-gg-wordpress-super-cache',
			'title' => $nodeTitle,
			'meta' => [
				'html' => $html,
				'class' => $redis_status === false ? 'icc-gg-wordpress-super-cache-error' : '',
			],
		]);

		if ( $redis_status ) {
			$wp_admin_bar->add_node([
				'parent' => 'icc-gg-wordpress-super-cache',
				'id' => 'icc-gg-wordpress-super-cache-flush',
				'title' => __( 'Flush Cache', 'icc-gg-wordpress-super-cache' ),
				'href' => $this->action_link( 'flush-cache' ),
			]);
		}

		$local = ICC_GG_WordPress_Super_Cache_Local_Cache::instance();
		$varnish = ICC_GG_WordPress_Super_Cache_Varnish_Cache::instance();
		$nginx = ICC_GG_WordPress_Super_Cache_Nginx_Cache::instance();

		if ( $local->is_enabled() ) {
			$wp_admin_bar->add_node([
				'parent' => 'icc-gg-wordpress-super-cache',
				'id' => 'icc-gg-wordpress-super-cache-purge-local',
				'title' => __( 'Clear Page Cache', 'icc-gg-wordpress-super-cache' ),
				'href' => $this->action_link( 'purge-local-cache' ),
			]);
		}

		if ( $varnish->is_enabled() ) {
			$wp_admin_bar->add_node([
				'parent' => 'icc-gg-wordpress-super-cache',
				'id' => 'icc-gg-wordpress-super-cache-purge-varnish',
				'title' => __( 'Purge Varnish Cache', 'icc-gg-wordpress-super-cache' ),
				'href' => $this->action_link( 'purge-varnish-cache' ),
			]);
		}

		if ( $nginx->is_enabled() ) {
			$wp_admin_bar->add_node([
				'parent' => 'icc-gg-wordpress-super-cache',
				'id' => 'icc-gg-wordpress-super-cache-purge-nginx',
				'title' => __( 'Purge nginx FastCGI Cache', 'icc-gg-wordpress-super-cache' ),
				'href' => $this->action_link( 'purge-nginx-cache' ),
			]);
		}

		$wp_admin_bar->add_node([
			'parent' => 'icc-gg-wordpress-super-cache',
			'id' => 'icc-gg-wordpress-super-cache-metrics',
			'title' => __( 'Settings', 'icc-gg-wordpress-super-cache' ),
			'href' => network_admin_url( $this->page ),
		]);

		$wp_admin_bar->add_group(
			[
				'id' => 'icc-gg-wordpress-super-cache-tabs',
				'parent' => 'icc-gg-wordpress-super-cache',
				'meta' => [
					'class' => 'ab-sub-secondary',
				],
			]
		);

		$tab_links = [
			'local' => __( 'Page Cache Settings', 'icc-gg-wordpress-super-cache' ),
			'varnish' => __( 'Varnish Cache Settings', 'icc-gg-wordpress-super-cache' ),
			'nginx' => __( 'nginx FastCGI Settings', 'icc-gg-wordpress-super-cache' ),
			'images' => __( 'Optimize Images', 'icc-gg-wordpress-super-cache' ),
		];

		foreach ( $tab_links as $tab => $label ) {
			$wp_admin_bar->add_node([
				'parent' => 'icc-gg-wordpress-super-cache-tabs',
				'id' => 'icc-gg-wordpress-super-cache-tab-' . $tab,
				'title' => $label,
				'href' => network_admin_url( $this->page . '#' . $tab ),
			]);
		}

		$wp_admin_bar->add_group(
			[
				'id' => 'icc-gg-wordpress-super-cache-info',
				'parent' => 'icc-gg-wordpress-super-cache',
				'meta' => [
					'class' => 'ab-sub-secondary',
				],
			]
		);

		$value = $this->get_status();
		// translators: %s = The status of the Redis connection.
		$title = sprintf( __( 'Status: %s', 'icc-gg-wordpress-super-cache' ), $value );

		if ( $redis_status ) {
			global $wp_object_cache;

			$info = $wp_object_cache->info();
			$hits = number_format_i18n( $info->hits );
			$misses = number_format_i18n( $info->misses );
			$size = size_format( $info->bytes );

			$value = sprintf(
				'%s%%&nbsp;&nbsp;%s/%s&nbsp;&nbsp;%s',
				$info->ratio,
				$hits,
				$misses,
				$size
			);

			$title = sprintf(
				// translators: 1: Hit ratio, 2: Hits, 3: Misses. 4: Human-readable size of cache.
				__( '(Current page) Hit Ratio: %1$s%%, Hits %2$s, Misses: %3$s, Size: %4$s', 'icc-gg-wordpress-super-cache' ),
				$info->ratio,
				$hits,
				$misses,
				$size
			);
		}

		$wp_admin_bar->add_node([
			'parent' => 'icc-gg-wordpress-super-cache-info',
			'id' => 'icc-gg-wordpress-super-cache-info-details',
			'title' => $value,
			'href' => $redis_status && ICC_GG_WordPress_Super_Cache_Metrics::is_enabled() ? network_admin_url( $this->page . '#redis' ) : '',
			'meta' => [
				'title' => $title,
			],
		]);
	}

	/**
	 * Returns the admin-bar <style> tag.
	 *
	 * @return string
	 */
	protected function admin_bar_style()
	{
		// phpcs:disable Squiz.PHP.Heredoc.NotAllowed
		return <<<HTML
			<style id="icc-gg-wordpress-super-cache-admin-bar-style">
				#wpadminbar ul li.icc-gg-wordpress-super-cache-error {
					background: #b30000;
				}

				#wpadminbar:not(.mobile) .ab-top-menu > li.icc-gg-wordpress-super-cache-error:hover > .ab-item {
					background: #b30000;
					color: #fff;
				}
			</style>
HTML;
	}

	/**
	 * Returns the admin-bar <script> tag.
	 *
	 * @return string
	 */
	protected function admin_bar_script()
	{
		$nonce = wp_create_nonce();
		$ajaxurl = esc_url( admin_url( 'admin-ajax.php' ) );
		$flushMessage = __( 'Flushing cache...', 'icc-gg-wordpress-super-cache' );

		return <<<HTML
			<script id="icc-gg-wordpress-super-cache-admin-bar">
				(function (element) {
					if (! element) {
						return;
					}

					element.addEventListener('click', async function (event) {
						event.preventDefault();

						var node = document.querySelector('#wp-admin-bar-icc-gg-wordpress-super-cache');
						var textNode = node.querySelector('.ab-item:first-child');

						if (! textNode.dataset.text) {
							textNode.dataset.text = textNode.innerText;
						}

						node.classList.remove('hover');
						textNode.innerText = '{$flushMessage}';

						try {
							var data = new FormData();
							data.append('action', 'icc_gg_wordpress_super_cache_flush_cache');
							data.append('nonce', '{$nonce}');

							var response = await fetch('{$ajaxurl}', {
								method: 'POST',
								body: data,
							});

							textNode.innerText = await response.text();

							setTimeout(function () {
								textNode.innerText = textNode.dataset.text;
							}, 3000);
						} catch (error) {
							textNode.innerText = textNode.dataset.text;
							alert('Object cache could not be flushed: ' + error);
						}
					});
				})(
					document.querySelector('#wp-admin-bar-icc-gg-wordpress-super-cache-flush > a')
				);
			</script>
HTML;
	}

	/**
	 * Executes admin actions
	 *
	 * @return void
	 */
	public function do_admin_actions()
	{
		global $wp_filesystem;

		if ( isset( $_POST['icc_gg_wordpress_super_cache_nginx'] ) ) {
			$this->save_nginx_settings();

			return;
		}

		if ( isset( $_POST['icc_gg_wordpress_super_cache_local'] ) ) {
			$this->save_local_settings();

			return;
		}

		if ( isset( $_POST['icc_gg_wordpress_super_cache_local_urls'] ) ) {
			$this->purge_local_urls();

			return;
		}

		if ( isset( $_POST['icc_gg_wordpress_super_cache_varnish'] ) ) {
			$this->save_varnish_settings();

			return;
		}

		if ( isset( $_POST['icc_gg_wordpress_super_cache_images'] ) ) {
			$this->save_image_settings();

			return;
		}

		if ( isset( $_GET['_wpnonce'], $_GET['action'] ) ) {
			$action = sanitize_key( $_GET['action'] );
			$nonce = sanitize_key( $_GET['_wpnonce'] );

			// Nonce verification.
			foreach ( $this->actions as $name ) {
				if ( $action === $name && ! wp_verify_nonce( $nonce, $action ) ) {
					return;
				}
			}

			if ( in_array( $action, $this->actions, true ) ) {
				$url = $this->action_link( $action );

				if ( $action === 'flush-cache' ) {
					wp_cache_flush()
						? add_settings_error(
							'icc-gg-wordpress-super-cache',
							'flush',
							__( 'Object cache flushed.', 'icc-gg-wordpress-super-cache' ),
							'updated'
						)
						: add_settings_error(
							'icc-gg-wordpress-super-cache',
							'flush',
							__( 'Object cache could not be flushed.', 'icc-gg-wordpress-super-cache' ),
							'error'
						);
				}

				if ( $action === 'purge-nginx-cache' ) {
					$result = ICC_GG_WordPress_Super_Cache_Nginx_Cache::instance()->purge_zone();

					if ( true === $result ) {
						add_settings_error(
							'icc-gg-wordpress-super-cache',
							'nginx',
							__( 'nginx FastCGI cache purged.', 'icc-gg-wordpress-super-cache' ),
							'updated'
						);
					} elseif ( is_wp_error( $result ) ) {
						add_settings_error(
							'icc-gg-wordpress-super-cache',
							'nginx',
							sprintf(
								// translators: %s = The reason the nginx FastCGI cache could not be purged.
								__( 'nginx FastCGI cache could not be purged: %s', 'icc-gg-wordpress-super-cache' ),
								$result->get_error_message()
							),
							'error'
						);
					} else {
						add_settings_error(
							'icc-gg-wordpress-super-cache',
							'nginx',
							__( 'nginx FastCGI cache purge was skipped.', 'icc-gg-wordpress-super-cache' ),
							'error'
						);
					}
				}

				$handled = $this->handle_extra_actions( $action );

				// do we have filesystem credentials?
				if ( ! $handled && $this->initialize_filesystem( $url, true ) ) {

					if ( $action === 'enable-cache' ) {
						$result = $wp_filesystem->copy(
							ICC_GG_WORDPRESS_SUPER_CACHE_PLUGIN_PATH . '/includes/object-cache.php',
							WP_CONTENT_DIR . '/object-cache.php',
							true,
							FS_CHMOD_FILE
						);

						if ( $result ) {
							(new ICC_GG_WordPress_Super_Cache_Predis)->flush();
						}

						/**
						 * Fires on cache enable event
						 *
						 * @since 1.3.5
						 * @param bool $result Whether the filesystem event (copy of the `object-cache.php` file) was successful.
						 */
						do_action( 'icc_gg_wordpress_super_cache_enable', $result );

						$result
							? add_settings_error(
								'icc-gg-wordpress-super-cache',
								'dropin',
								__( 'Object cache enabled.', 'icc-gg-wordpress-super-cache' ),
								'updated'
							)
							: add_settings_error(
								'icc-gg-wordpress-super-cache',
								'dropin',
								__( 'Object cache could not be enabled.', 'icc-gg-wordpress-super-cache' ),
								'error'
							);
					}

					if ( $action === 'disable-cache' ) {
						$result = $wp_filesystem->delete( WP_CONTENT_DIR . '/object-cache.php' );

						if ( $result ) {
							(new ICC_GG_WordPress_Super_Cache_Predis)->flush();
						}

						/**
						 * Fires on cache disable event
						 *
						 * @since 1.3.5
						 * @param bool $result Whether the filesystem event (deletion of the `object-cache.php` file) was successful.
						 */
						do_action( 'icc_gg_wordpress_super_cache_disable', $result );

						$result
							? add_settings_error(
								'icc-gg-wordpress-super-cache',
								'dropin',
								__( 'Object cache disabled.', 'icc-gg-wordpress-super-cache' ),
								'updated'
							)
							: add_settings_error(
								'icc-gg-wordpress-super-cache',
								'dropin',
								__( 'Object cache could not be disabled.', 'icc-gg-wordpress-super-cache' ),
								'error'
							);
					}

					if ( $action === 'update-dropin' ) {
						$result = $wp_filesystem->copy(
							ICC_GG_WORDPRESS_SUPER_CACHE_PLUGIN_PATH . '/includes/object-cache.php',
							WP_CONTENT_DIR . '/object-cache.php',
							true,
							FS_CHMOD_FILE
						);

						/**
						 * Fires on cache update-dropin event
						 *
						 * @since 1.3.5
						 * @param bool $result Whether the filesystem event (copy of the `object-cache.php` file) was successful.
						 */
						do_action( 'icc_gg_wordpress_super_cache_update_dropin', $result );

						$result
							? add_settings_error(
								'icc-gg-wordpress-super-cache',
								'dropin',
								__( 'Updated object cache drop-in and enabled Redis object cache.', 'icc-gg-wordpress-super-cache' ),
								'updated'
							)
							: add_settings_error(
								'icc-gg-wordpress-super-cache',
								'dropin',
								__( 'Object cache drop-in could not be updated.', 'icc-gg-wordpress-super-cache' ),
								'error'
							);
					}
				}

				$messages = get_settings_errors( 'icc-gg-wordpress-super-cache' );

				if ( count( $messages ) !== 0 ) {
					set_transient( 'settings_errors', $messages, 30 );

					$fragment = $this->tab_fragment_for_action( $action );

					wp_safe_redirect(
						network_admin_url( add_query_arg( 'settings-updated', 1, $this->page ) . $fragment )
					);
					exit;
				}
			}
		}
	}

	/**
	 * Saves the nginx FastCGI cache settings submitted from the nginx FastCGI tab.
	 *
	 * @return void
	 */
	protected function save_nginx_settings()
	{
		check_admin_referer( 'icc_gg_wordpress_super_cache_nginx' );

		if ( ! $this->current_user_can_manage_redis() ) {
			return;
		}

		$nginx = ICC_GG_WordPress_Super_Cache_Nginx_Cache::instance();

		$settings = $nginx->sanitize_settings(
			wp_unslash( $_POST['icc_gg_wordpress_super_cache_nginx'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		);

		$registry = ICC_GG_WordPress_Super_Cache_Page_Cache::instance();
		$registry_error = null;

		if ( ! empty( $settings['enabled'] ) && ! $registry->is_active( ICC_GG_WordPress_Super_Cache_Page_Cache::NGINX ) ) {
			$result = $registry->set_active( ICC_GG_WordPress_Super_Cache_Page_Cache::NGINX, true );

			if ( is_wp_error( $result ) ) {
				$registry_error = $result;
				$settings['enabled'] = false;
			}
		} elseif ( empty( $settings['enabled'] ) && $registry->is_active( ICC_GG_WordPress_Super_Cache_Page_Cache::NGINX ) ) {
			$registry->set_active( ICC_GG_WordPress_Super_Cache_Page_Cache::NGINX, false );
		}

		update_option( ICC_GG_WordPress_Super_Cache_Nginx_Cache::OPTION_KEY, $settings );

		$nginx->flush_settings_cache();
		$nginx->flush_stats_cache();

		$path_error = $settings['path'] === '' ? null : $nginx->is_valid_path();

		if ( is_wp_error( $path_error ) ) {
			add_settings_error(
				'icc-gg-wordpress-super-cache',
				'nginx',
				sprintf(
					// translators: %s = The cache zone path validation error.
					__( 'nginx FastCGI cache settings saved, but the cache zone is unusable: %s', 'icc-gg-wordpress-super-cache' ),
					$path_error->get_error_message()
				),
				'error'
			);
		} else {
			add_settings_error(
				'icc-gg-wordpress-super-cache',
				'nginx',
				__( 'nginx FastCGI cache settings saved.', 'icc-gg-wordpress-super-cache' ),
				'updated'
			);
		}

		if ( $registry_error instanceof WP_Error ) {
			add_settings_error(
				'icc-gg-wordpress-super-cache',
				'page_cache_conflict',
				$registry_error->get_error_message(),
				'error'
			);
		}

		set_transient( 'settings_errors', get_settings_errors( 'icc-gg-wordpress-super-cache' ), 30 );

		wp_safe_redirect(
			network_admin_url( add_query_arg( 'settings-updated', 1, $this->page ) . '#nginx' )
		);
		exit;
	}

	/**
	 * Returns the tab fragment a given action should redirect to.
	 *
	 * @param string $action Action name.
	 * @return string
	 */
	protected function tab_fragment_for_action( $action )
	{
		$fragments = [
			'flush-cache' => '#redis',
			'purge-nginx-cache' => '#nginx',
			'enable-local-cache' => '#local',
			'disable-local-cache' => '#local',
			'purge-local-cache' => '#local',
			'preload-local-cache' => '#local',
			'replace-advanced-cache' => '#local',
			'write-page-cache-loader' => '#local',
			'reset-local-exclusions' => '#local',
			'revert-wp-config' => '#local',
			'enable-varnish-cache' => '#varnish',
			'disable-varnish-cache' => '#varnish',
			'purge-varnish-cache' => '#varnish',
			'test-varnish-cache' => '#varnish',
			'reset-image-optimizer' => '#images',
		];

		return isset( $fragments[ $action ] ) ? $fragments[ $action ] : '';
	}

	/**
	 * Adds a settings error shown at the top of the settings page.
	 *
	 * @param string $message Message to display.
	 * @param string $type    Either `updated` or `error`.
	 * @param string $code    Optional error code.
	 * @return void
	 */
	protected function add_action_message( $message, $type = 'updated', $code = 'icc_gg_action' )
	{
		add_settings_error( 'icc-gg-wordpress-super-cache', $code, $message, $type );
	}

	/**
	 * Stores the collected messages and redirects back to the settings page.
	 *
	 * @param string $fragment Optional tab fragment.
	 * @return void
	 */
	protected function redirect_with_messages( $fragment = '' )
	{
		$messages = get_settings_errors( 'icc-gg-wordpress-super-cache' );

		if ( $messages ) {
			set_transient( 'settings_errors', $messages, 30 );
		}

		wp_safe_redirect(
			network_admin_url( add_query_arg( 'settings-updated', 1, $this->page ) . $fragment )
		);
		exit;
	}

	/**
	 * Handles the page cache and image optimizer actions.
	 *
	 * @param string $action Action name.
	 * @return bool Whether the action was handled.
	 */
	protected function handle_extra_actions( $action )
	{
		$handlers = [
			'enable-local-cache' => 'handle_local_cache_action',
			'disable-local-cache' => 'handle_local_cache_action',
			'purge-local-cache' => 'handle_local_cache_action',
			'preload-local-cache' => 'handle_local_cache_action',
			'replace-advanced-cache' => 'handle_local_cache_action',
			'write-page-cache-loader' => 'handle_local_cache_action',
			'reset-local-exclusions' => 'handle_local_cache_action',
			'revert-wp-config' => 'handle_local_cache_action',
			'enable-varnish-cache' => 'handle_varnish_action',
			'disable-varnish-cache' => 'handle_varnish_action',
			'purge-varnish-cache' => 'handle_varnish_action',
			'test-varnish-cache' => 'handle_varnish_action',
			'reset-image-optimizer' => 'handle_image_action',
		];

		if ( ! isset( $handlers[ $action ] ) ) {
			return false;
		}

		$method = $handlers[ $action ];

		return (bool) $this->$method( $action );
	}

	/**
	 * Enables, disables, purges or preloads the local page cache.
	 *
	 * @param string $action Action name.
	 * @return bool
	 */
	protected function handle_local_cache_action( $action )
	{
		$registry = ICC_GG_WordPress_Super_Cache_Page_Cache::instance();
		$local = ICC_GG_WordPress_Super_Cache_Local_Cache::instance();

		switch ( $action ) {
			case 'enable-local-cache':
				$result = $registry->set_active( ICC_GG_WordPress_Super_Cache_Page_Cache::LOCAL, true );

				if ( is_wp_error( $result ) ) {
					$this->add_action_message( $result->get_error_message(), 'error', 'page_cache' );

					break;
				}

				$result = $local->install();

				if ( is_wp_error( $result ) ) {
					$this->add_action_message(
						sprintf(
							// translators: %s = The reason the loader could not be installed.
							__( 'The page cache was enabled, but its loader could not be installed: %s', 'icc-gg-wordpress-super-cache' ),
							$result->get_error_message()
						),
						'error',
						'page_cache'
					);

					if ( in_array( $result->get_error_code(), [ 'foreign_dropin', 'foreign_mu_plugin' ], true ) ) {
						$this->add_action_message(
							__( 'Use "Write / Rewrite advanced-cache.php" in the Maintenance section of the Page Cache tab to replace it; the current file is copied to a backup first.', 'icc-gg-wordpress-super-cache' ),
							'error',
							'page_cache'
						);
					}

					break;
				}

				$this->add_action_message(
					__( 'The local page cache is enabled.', 'icc-gg-wordpress-super-cache' ),
					'updated',
					'page_cache'
				);

				$wp_config_error = $local->get_last_error();

				if ( $wp_config_error instanceof WP_Error ) {
					$this->add_action_message(
						sprintf(
							// translators: %s = The reason wp-config.php could not be edited.
							__( 'WP_CACHE was not added to wp-config.php (%s); the must-use plugin loader was installed instead.', 'icc-gg-wordpress-super-cache' ),
							$wp_config_error->get_error_message()
						),
						'error',
						'page_cache'
					);
				}

				break;

			case 'disable-local-cache':
				$registry->set_active( ICC_GG_WordPress_Super_Cache_Page_Cache::LOCAL, false );

				$result = $local->install();

				if ( is_wp_error( $result ) ) {
					$this->add_action_message( $result->get_error_message(), 'error', 'page_cache' );

					break;
				}

				$this->add_action_message(
					__( 'The local page cache is disabled and its loader was removed.', 'icc-gg-wordpress-super-cache' ),
					'updated',
					'page_cache'
				);

				break;

			case 'purge-local-cache':
				$local->purge();

				$this->add_action_message(
					__( 'The local page cache was purged.', 'icc-gg-wordpress-super-cache' ),
					'updated',
					'page_cache'
				);

				break;

			case 'preload-local-cache':
				if ( ! $local->is_enabled() ) {
					$this->add_action_message(
						__( 'The local page cache is disabled, so it cannot be preloaded.', 'icc-gg-wordpress-super-cache' ),
						'error',
						'page_cache'
					);

					break;
				}

				$queued = $local->start_preload();

				$local->run_preload_batch( (int) $local->get_setting( 'preload_batch' ) );

				$this->add_action_message(
					sprintf(
						// translators: %d = Number of queued URLs.
						_n(
							'%d URL was queued for preloading.',
							'%d URLs were queued for preloading.',
							$queued,
							'icc-gg-wordpress-super-cache'
						),
						$queued
					),
					'updated',
					'page_cache'
				);

				break;

			case 'replace-advanced-cache':
				$result = $local->install();

				if ( is_wp_error( $result ) ) {
					$this->add_action_message( $result->get_error_message(), 'error', 'page_cache' );

					break;
				}

				$this->add_action_message(
					__( 'The page cache loader was regenerated.', 'icc-gg-wordpress-super-cache' ),
					'updated',
					'page_cache'
				);

				break;

			case 'write-page-cache-loader':
				$result = $local->force_write_loader();

				if ( is_wp_error( $result ) ) {
					$this->add_action_message( $result->get_error_message(), 'error', 'page_cache' );

					break;
				}

				$state = $local->loader_state();

				if ( 'mu-plugin' === $state['mode'] ) {
					$this->add_action_message(
						__( 'The must-use plugin loader was written because WP_CACHE could not be added to wp-config.php.', 'icc-gg-wordpress-super-cache' ),
						'updated',
						'page_cache'
					);
				} else {
					$this->add_action_message(
						__( 'advanced-cache.php was written.', 'icc-gg-wordpress-super-cache' ),
						'updated',
						'page_cache'
					);
				}

				$backup = $local->get_last_backup_path();

				if ( '' !== $backup ) {
					$this->add_action_message(
						sprintf(
							// translators: %s = Path of the backup file.
							__( 'The previous file was backed up to %s.', 'icc-gg-wordpress-super-cache' ),
							$backup
						),
						'updated',
						'page_cache'
					);
				}

				if ( ! $local->is_enabled() ) {
					$this->add_action_message(
						__( 'The page cache is disabled, so caching stays off until it is enabled.', 'icc-gg-wordpress-super-cache' ),
						'error',
						'page_cache'
					);
				}

				break;

			case 'reset-local-exclusions':
				$result = $local->reset_exclusions();

				if ( is_wp_error( $result ) ) {
					$this->add_action_message( $result->get_error_message(), 'error', 'page_cache' );

					break;
				}

				$this->add_action_message(
					__( 'The default URL exclusions were restored.', 'icc-gg-wordpress-super-cache' ),
					'updated',
					'page_cache'
				);

				break;

			case 'revert-wp-config':
				$result = $local->revert_wp_config();

				if ( is_wp_error( $result ) ) {
					$this->add_action_message( $result->get_error_message(), 'error', 'page_cache' );

					break;
				}

				$this->add_action_message(
					__( 'wp-config.php was restored from the backup. The page cache keeps working through the must-use plugin loader.', 'icc-gg-wordpress-super-cache' ),
					'updated',
					'page_cache'
				);

				break;
		}

		$this->redirect_with_messages( '#local' );

		return true;
	}

	/**
	 * Enables, disables, purges or tests the Varnish cache.
	 *
	 * @param string $action Action name.
	 * @return bool
	 */
	protected function handle_varnish_action( $action )
	{
		$registry = ICC_GG_WordPress_Super_Cache_Page_Cache::instance();
		$varnish = ICC_GG_WordPress_Super_Cache_Varnish_Cache::instance();

		switch ( $action ) {
			case 'enable-varnish-cache':
				$result = $registry->set_active( ICC_GG_WordPress_Super_Cache_Page_Cache::VARNISH, true );

				if ( is_wp_error( $result ) ) {
					$this->add_action_message( $result->get_error_message(), 'error', 'varnish' );

					break;
				}

				$settings = $varnish->get_settings();
				$settings['enabled'] = true;

				update_option( ICC_GG_WordPress_Super_Cache_Varnish_Cache::OPTION_KEY, $settings );

				$varnish->flush_settings_cache();

				$this->add_action_message(
					__( 'Varnish is enabled.', 'icc-gg-wordpress-super-cache' ),
					'updated',
					'varnish'
				);

				$test = $varnish->test();

				// Shown by the "Last Test" row, not repeated as a settings error.
				set_transient( 'icc_gg_wordpress_super_cache_varnish_test', $test, 120 );

				break;

			case 'disable-varnish-cache':
				$registry->set_active( ICC_GG_WordPress_Super_Cache_Page_Cache::VARNISH, false );

				$settings = $varnish->get_settings();
				$settings['enabled'] = false;

				update_option( ICC_GG_WordPress_Super_Cache_Varnish_Cache::OPTION_KEY, $settings );

				$varnish->flush_settings_cache();

				$this->add_action_message(
					__( 'Varnish is disabled.', 'icc-gg-wordpress-super-cache' ),
					'updated',
					'varnish'
				);

				break;

			case 'purge-varnish-cache':
				$result = $varnish->purge();

				if ( is_wp_error( $result ) ) {
					$this->add_action_message(
						sprintf(
							// translators: %s = The reason the purge failed.
							__( 'Varnish could not be purged: %s', 'icc-gg-wordpress-super-cache' ),
							$result->get_error_message()
						),
						'error',
						'varnish'
					);

					break;
				}

				$this->add_action_message(
					__( 'Varnish cache purged.', 'icc-gg-wordpress-super-cache' ),
					'updated',
					'varnish'
				);

				break;

			case 'test-varnish-cache':
				$test = $varnish->test();

				// The result is stored so the tab can keep showing it; no
				// settings error is queued to avoid printing the same message
				// twice on the same screen.
				set_transient( 'icc_gg_wordpress_super_cache_varnish_test', $test, 120 );

				break;
		}

		$this->redirect_with_messages( '#varnish' );

		return true;
	}

	/**
	 * Handles the image optimizer actions.
	 *
	 * @param string $action Action name.
	 * @return bool
	 */
	protected function handle_image_action( $action )
	{
		if ( 'reset-image-optimizer' === $action ) {
			ICC_GG_WordPress_Super_Cache_Image_Optimizer::instance()->reset_batch_state();

			$this->add_action_message(
				__( 'The image optimization progress was reset.', 'icc-gg-wordpress-super-cache' ),
				'updated',
				'images'
			);
		}

		$this->redirect_with_messages( '#images' );

		return true;
	}

	/**
	 * Saves the local page cache settings submitted from the Page Cache tab.
	 *
	 * @return void
	 */
	protected function save_local_settings()
	{
		check_admin_referer( 'icc_gg_wordpress_super_cache_local' );

		if ( ! $this->current_user_can_manage_redis() ) {
			return;
		}

		$input = wp_unslash( $_POST['icc_gg_wordpress_super_cache_local'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$input = is_array( $input ) ? $input : [];

		$local = ICC_GG_WordPress_Super_Cache_Local_Cache::instance();
		$registry = ICC_GG_WordPress_Super_Cache_Page_Cache::instance();

		$settings = $local->sanitize_settings( $input );

		update_option( ICC_GG_WordPress_Super_Cache_Local_Cache::OPTION_KEY, $settings );

		$local->flush_settings_cache();

		$want_enabled = ! empty( $input['enabled'] );

		if ( $want_enabled && ! $local->is_enabled() ) {
			$result = $registry->set_active( ICC_GG_WordPress_Super_Cache_Page_Cache::LOCAL, true );

			if ( is_wp_error( $result ) ) {
				$this->add_action_message( $result->get_error_message(), 'error', 'page_cache' );
			}
		} elseif ( ! $want_enabled && $local->is_enabled() ) {
			$registry->set_active( ICC_GG_WordPress_Super_Cache_Page_Cache::LOCAL, false );
		}

		$install = $local->install();

		if ( is_wp_error( $install ) ) {
			$this->add_action_message(
				sprintf(
					// translators: %s = The reason the loader could not be written.
					__( 'Settings saved, but the page cache loader could not be written: %s', 'icc-gg-wordpress-super-cache' ),
					$install->get_error_message()
				),
				'error',
				'page_cache'
			);
		} else {
			$this->add_action_message(
				__( 'Page cache settings saved.', 'icc-gg-wordpress-super-cache' ),
				'updated',
				'page_cache'
			);

			$wp_config_error = $local->get_last_error();

			if ( $local->is_enabled() && $wp_config_error instanceof WP_Error ) {
				$this->add_action_message(
					sprintf(
						// translators: %s = The reason wp-config.php could not be edited.
						__( 'WP_CACHE was not added to wp-config.php (%s); the must-use plugin loader was installed instead.', 'icc-gg-wordpress-super-cache' ),
						$wp_config_error->get_error_message()
					),
					'error',
					'page_cache'
				);
			}
		}

		$this->redirect_with_messages( '#local' );
	}

	/**
	 * Purges a list of URLs from the local page cache.
	 *
	 * @return void
	 */
	protected function purge_local_urls()
	{
		check_admin_referer( 'icc_gg_wordpress_super_cache_local_urls' );

		if ( ! $this->current_user_can_manage_redis() ) {
			return;
		}

		$urls = '';

		if ( isset( $_POST['icc_gg_wordpress_super_cache_local_urls']['urls'] ) ) {
			$urls = wp_unslash( $_POST['icc_gg_wordpress_super_cache_local_urls']['urls'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}

		$deleted = ICC_GG_WordPress_Super_Cache_Local_Cache::instance()->purge_urls( (string) $urls );

		$this->add_action_message(
			sprintf(
				// translators: %d = Number of purged cache entries.
				_n(
					'%d cached entry was purged.',
					'%d cached entries were purged.',
					$deleted,
					'icc-gg-wordpress-super-cache'
				),
				$deleted
			),
			$deleted > 0 ? 'updated' : 'error',
			'page_cache'
		);

		$this->redirect_with_messages( '#local' );
	}

	/**
	 * Saves the Varnish settings submitted from the Varnish tab.
	 *
	 * @return void
	 */
	protected function save_varnish_settings()
	{
		check_admin_referer( 'icc_gg_wordpress_super_cache_varnish' );

		if ( ! $this->current_user_can_manage_redis() ) {
			return;
		}

		$input = wp_unslash( $_POST['icc_gg_wordpress_super_cache_varnish'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$input = is_array( $input ) ? $input : [];

		$varnish = ICC_GG_WordPress_Super_Cache_Varnish_Cache::instance();
		$registry = ICC_GG_WordPress_Super_Cache_Page_Cache::instance();

		$settings = $varnish->sanitize_settings( $input );

		update_option( ICC_GG_WordPress_Super_Cache_Varnish_Cache::OPTION_KEY, $settings );

		$varnish->flush_settings_cache();

		$want_enabled = ! empty( $input['enabled'] );

		if ( $want_enabled && ! $varnish->is_enabled() ) {
			$result = $registry->set_active( ICC_GG_WordPress_Super_Cache_Page_Cache::VARNISH, true );

			if ( is_wp_error( $result ) ) {
				$this->add_action_message( $result->get_error_message(), 'error', 'varnish' );
			}
		} elseif ( ! $want_enabled && $varnish->is_enabled() ) {
			$registry->set_active( ICC_GG_WordPress_Super_Cache_Page_Cache::VARNISH, false );
		}

		$this->add_action_message(
			__( 'Varnish settings saved.', 'icc-gg-wordpress-super-cache' ),
			'updated',
			'varnish'
		);

		$this->redirect_with_messages( '#varnish' );
	}

	/**
	 * Saves the image optimizer settings submitted from the Optimize Images tab.
	 *
	 * @return void
	 */
	protected function save_image_settings()
	{
		check_admin_referer( 'icc_gg_wordpress_super_cache_images' );

		if ( ! $this->current_user_can_manage_redis() ) {
			return;
		}

		$input = wp_unslash( $_POST['icc_gg_wordpress_super_cache_images'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$input = is_array( $input ) ? $input : [];

		$optimizer = ICC_GG_WordPress_Super_Cache_Image_Optimizer::instance();

		$settings = $optimizer->sanitize_settings( $input );

		update_option( ICC_GG_WordPress_Super_Cache_Image_Optimizer::OPTION_KEY, $settings );

		$optimizer->flush_settings_cache();

		$this->add_action_message(
			__( 'Image optimization settings saved.', 'icc-gg-wordpress-super-cache' ),
			'updated',
			'images'
		);

		$this->redirect_with_messages( '#images' );
	}

	/**
	 * Optimizes a batch of images over AJAX.
	 *
	 * @return void
	 */
	public function ajax_optimize_images()
	{
		check_ajax_referer( 'icc_gg_wordpress_super_cache_images_batch' );

		if ( ! $this->current_user_can_manage_redis() ) {
			wp_send_json_error(
				[
					'message' => __( 'You are not allowed to optimize images.', 'icc-gg-wordpress-super-cache' ),
				],
				403
			);
		}

		$optimizer = ICC_GG_WordPress_Super_Cache_Image_Optimizer::instance();

		$limit = isset( $_POST['limit'] ) ? (int) $_POST['limit'] : 0;

		$result = $optimizer->optimize_batch( $limit > 0 ? $limit : null );

		$result['stats'] = $optimizer->stats();

		wp_send_json_success( $result );
	}

	/**
	 * Flushes object cache
	 *
	 * @return void
	 */
	public function ajax_flush_cache()
	{
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			wp_die( esc_html__( 'Invalid Nonce.', 'icc-gg-wordpress-super-cache' ) );
		} else if ( wp_cache_flush() ) {
			wp_die( esc_html__( 'Object cache flushed.', 'icc-gg-wordpress-super-cache' ) );
		} else {
			wp_die( esc_html__( 'Object cache could not be flushed.', 'icc-gg-wordpress-super-cache' ) );
		}
	}

	/**
	 * Registers all hooks associated with the shutdown hook
	 *
	 * @return void
	 */
	public function register_shutdown_hooks()
	{
		if ( ! defined( 'WP_REDIS_DISABLE_COMMENT' ) || ! WP_REDIS_DISABLE_COMMENT ) {
			add_action( 'shutdown', [ $this, 'maybe_print_comment' ], 0 );
		}
	}

	/**
	 * Delete all transients if the cache was enabled successfully.
	 * Callback for `icc_gg_wordpress_super_cache_enable` action.
	 *
	 * @param bool $should_delete
	 * @return void
	 */
	public function maybe_delete_transients( $should_delete )
	{
		global $wpdb;

		if ( ! $should_delete ) {
			return;
		}

		if ( is_multisite() ) {
			$this->delete_multisite_transients();

			return;
		}

		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			'_transient_%',
			'_site_transient_%'
		) );
	}

	/**
	 * Deletes the transients for all blogs on the multisite network.
	 *
	 * @return void
	 */
	protected function delete_multisite_transients()
	{
		global $wpdb;

		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s", '_site_transient_%'
		) );

		$sites = get_sites([ 'fields' => 'ids', 'number' => 0 ]);

		foreach ( $sites as $id ) {
			try {
				$prefix = $wpdb->get_blog_prefix( $id );

				$wpdb->query( $wpdb->prepare(
					"DELETE FROM {$prefix}options WHERE option_name LIKE %s", '_transient_%'
				) );
			} catch ( Exception $error ) {
				error_log($error->getMessage());
			}
		}
	}

	/**
	 * Displays the redis cache html comment
	 *
	 * @return void
	 */
	public function maybe_print_comment()
	{
		/** @var \WP_Object_Cache $wp_object_cache */
		global $wp_object_cache;

		if (
			( defined( 'WP_CLI' ) && WP_CLI ) ||
			( defined( 'DOING_CRON' ) && DOING_CRON ) ||
			( defined( 'DOING_AJAX' ) && DOING_AJAX ) ||
			( defined( 'REST_REQUEST' ) && REST_REQUEST ) ||
			( defined( 'JSON_REQUEST' ) && JSON_REQUEST ) ||
			( defined( 'IFRAME_REQUEST' ) && IFRAME_REQUEST ) ||
			( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ||
			( defined( 'WC_API_REQUEST' ) && WC_API_REQUEST )
		) {
			return;
		}

		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return;
		}

		if (
			! isset( $wp_object_cache->cache, $wp_object_cache->diagnostics ) ||
			! is_array( $wp_object_cache->cache )
		) {
			return;
		}

		if ($this->incompatible_content_type()) {
			return;
		}

		$message = 'Performance optimized by ICC.gg WordPress Super Cache.';

		if ( ! WP_DEBUG_DISPLAY ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			printf( "\n<!-- %s -->\n", $message );

			return;
		}

		$bytes = strlen( serialize( $wp_object_cache->cache ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize

		$debug = sprintf(
			// translators: %1$d = number of objects. %2$s = human-readable size of cache. %3$s = name of the used client.
			__( 'Retrieved %1$d objects (%2$s) from Redis using %3$s.', 'icc-gg-wordpress-super-cache' ),
			$wp_object_cache->cache_hits,
			function_exists( 'size_format' ) ? size_format( $bytes ) : "{$bytes} bytes",
			$wp_object_cache->diagnostics['client']
		);

		printf(
			"<!--\n%s\n\n%s\n-->\n",
			$message, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html( $debug )
		);
	}

	/**
	 * Whether incompatible content type headers were sent.
	 *
	 * @return bool
	 */
	protected function incompatible_content_type()
	{
		$jsonContentType = static function ($headers) {
			foreach ($headers as $header => $value) {
				if (stripos((string) $header, 'content-type') === false) {
					continue;
				}

				if (stripos((string) $value, '/json') === false) {
					continue;
				}

				return true;
			}

			return false;
		};

		if (function_exists('headers_list')) {
			$headers = [];

			foreach (headers_list() as $header) {
				[$name, $value] = explode(':', $header);
				$headers[$name] = $value;
			}

			if ($jsonContentType($headers)) {
				return true;
			}
		}

		if (function_exists('apache_response_headers')) {
			if ($headers = apache_response_headers()) {
				return $jsonContentType($headers);
			}
		}

		return false;
	}

	/**
	 * Initializes the WP filesystem API to be ready for use
	 *
	 * @param string       $url     The URL to post the form to.
	 * @param bool         $silent  Whether to ask the user for credentials if necessary or not.
	 * @param string|false $context The directory the filesystem API is used for.
	 * @return bool
	 */
	public function initialize_filesystem( $url, $silent = false, $context = false )
	{
		if ( $silent ) {
			ob_start();
		}

		$credentials = request_filesystem_credentials( $url, '', false, $context );

		if ( false === $credentials ) {
			if ( $silent ) {
				ob_end_clean();
			}

			return false;
		}

		if ( ! WP_Filesystem( $credentials, $context ) ) {
			request_filesystem_credentials( $url, '', false, $context );

			if ( $silent ) {
				ob_end_clean();
			}

			return false;
		}

		return true;
	}

	/**
	 * Determines whether object cache file modifications are allowed.
	 *
	 * @return bool
	 */
	function is_file_mod_allowed()
	{
		return apply_filters(
			'file_mod_allowed',
			! defined( 'DISALLOW_FILE_MODS' ) || ! DISALLOW_FILE_MODS,
			'object_cache_dropin'
		);
	}

	/**
	 * Test if we can write in the WP_CONTENT_DIR and modify the `object-cache.php` drop-in
	 *
	 * @return true|WP_Error
	 */
	public function test_filesystem_writing()
	{
		/** @var \WP_Filesystem_Base $wp_filesystem */
		global $wp_filesystem;

		if ( ! $this->is_file_mod_allowed() ) {
			return new WP_Error( 'disallowed', __( 'File modifications are not allowed.', 'icc-gg-wordpress-super-cache' ) );
		}

		if ( ! $this->initialize_filesystem( '', true ) ) {
			return new WP_Error( 'fs', __( 'Could not initialize filesystem.', 'icc-gg-wordpress-super-cache' ) );
		}

		$dropin_check = ! defined( 'WP_REDIS_DISABLE_DROPIN_CHECK' ) || ! WP_REDIS_DISABLE_DROPIN_CHECK;

		if ( ! $dropin_check ) {
			if ( ! $wp_filesystem->exists( WP_CONTENT_DIR . '/object-cache.php' ) ) {
				return true;
			}

			if ( ! $wp_filesystem->is_writable( WP_CONTENT_DIR . '/object-cache.php' ) ) {
				return new WP_Error( 'writable', __( 'Object cache drop-in is not writable.', 'icc-gg-wordpress-super-cache' ) );
			}

			return true;
		}

		$cachefile = ICC_GG_WORDPRESS_SUPER_CACHE_PLUGIN_PATH . '/includes/object-cache.php';
		$testfile = WP_CONTENT_DIR . '/object-cache.tmp';

		if ( ! $wp_filesystem->exists( $cachefile ) ) {
			return new WP_Error( 'exists', __( 'Object cache file doesn’t exist.', 'icc-gg-wordpress-super-cache' ) );
		}

		if ( $wp_filesystem->exists( $testfile ) ) {
			if ( ! $wp_filesystem->delete( $testfile ) ) {
				return new WP_Error( 'delete', __( 'Test file exists, but couldn’t be deleted.', 'icc-gg-wordpress-super-cache' ) );
			}
		}

		if ( ! $wp_filesystem->is_writable( WP_CONTENT_DIR ) ) {
			return new WP_Error( 'writable', __( 'Content directory is not writable.', 'icc-gg-wordpress-super-cache' ) );
		}

		if ( ! $wp_filesystem->copy( $cachefile, $testfile, true, FS_CHMOD_FILE ) ) {
			return new WP_Error( 'copy', __( 'Failed to copy test file.', 'icc-gg-wordpress-super-cache' ) );
		}

		if ( ! $wp_filesystem->exists( $testfile ) ) {
			return new WP_Error( 'exists', __( 'Copied test file doesn’t exist.', 'icc-gg-wordpress-super-cache' ) );
		}

		$meta = get_file_data( $testfile, [ 'Version' => 'Version' ] );

		if ( $meta['Version'] !== ICC_GG_WORDPRESS_SUPER_CACHE_VERSION ) {
			return new WP_Error( 'version', __( 'Couldn’t verify test file contents.', 'icc-gg-wordpress-super-cache' ) );
		}

		if ( ! $wp_filesystem->delete( $testfile ) ) {
			return new WP_Error( 'delete', __( 'Copied test file couldn’t be deleted.', 'icc-gg-wordpress-super-cache' ) );
		}

		return true;
	}

	/**
	 * Calls the drop-in update method if necessary
	 *
	 * @return void
	 */
	public function maybe_update_dropin()
	{
		if ( defined( 'WP_REDIS_DISABLE_DROPIN_AUTOUPDATE' ) && WP_REDIS_DISABLE_DROPIN_AUTOUPDATE ) {
			return;
		}

		if ( $this->object_cache_dropin_outdated() ) {
			add_action( 'shutdown', [ $this, 'update_dropin' ] );
		}
	}

	/**
	 * Redirects to the plugin settings if the plugin was just activated
	 *
	 * @return void
	 */
	public function maybe_redirect()
	{
		if ( ! get_transient( '_icc_gg_wordpress_super_cache_activation_redirect' ) ) {
			return;
		}

		if ( ! $this->current_user_can_manage_redis() ) {
			return;
		}

		delete_transient( '_icc_gg_wordpress_super_cache_activation_redirect' );

		wp_safe_redirect( network_admin_url( $this->page ) );
	}

	/**
	 * Updates the `object-cache.php` drop-in
	 *
	 * @return void
	 */
	public function update_dropin()
	{
		global $wp_filesystem;

		if ( ! $this->validate_object_cache_dropin() ) {
			return;
		}

		if ( $this->initialize_filesystem( '', true ) ) {
			$result = $wp_filesystem->copy(
				ICC_GG_WORDPRESS_SUPER_CACHE_PLUGIN_PATH . '/includes/object-cache.php',
				WP_CONTENT_DIR . '/object-cache.php',
				true,
				FS_CHMOD_FILE
			);

			/**
			 * Fires on cache update-dropin event
			 *
			 * @since 1.3.5
			 * @param bool $result Whether the filesystem event (copy of the `object-cache.php` file) was successful.
			 */
			do_action( 'icc_gg_wordpress_super_cache_update_dropin', $result );
		}
	}

	/**
	 * Plugin activation hook
	 *
	 * @return void
	 */
	public static function on_activation()
	{
		set_transient( '_icc_gg_wordpress_super_cache_activation_redirect', 1, 30 );
	}

	/**
	 * Plugin deactivation hook
	 *
	 * @param string $plugin Plugin basename.
	 * @return void
	 */
	public function on_deactivation( $plugin )
	{
		global $wp_filesystem;

		ob_start();

		if ( $plugin === ICC_GG_WORDPRESS_SUPER_CACHE_BASENAME ) {
			$timestamp = wp_next_scheduled( 'icc_gg_wordpress_super_cache_discard_metrics' );

			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, 'icc_gg_wordpress_super_cache_discard_metrics' );
			}

			(new ICC_GG_WordPress_Super_Cache_Predis)->flush();

			if ( $this->validate_object_cache_dropin() && $this->initialize_filesystem( '', true ) ) {
				$wp_filesystem->delete( WP_CONTENT_DIR . '/object-cache.php' );
			}

			// Stop page caching while the plugin is inactive; the cached files stay.
			$local = ICC_GG_WordPress_Super_Cache_Local_Cache::instance();

			$local->remove_loaders();
			$local->unschedule_preload();
		}

		ob_end_clean();
	}

	/**
	 * Helper method to retrieve a nonced plugin action link
	 *
	 * @param string $action The action to be executed once the link is followed.
	 * @return string
	 */
	public function action_link( $action )
	{
		if ( ! in_array( $action, $this->actions, true ) ) {
			return '';
		}

		return wp_nonce_url(
			network_admin_url( add_query_arg( 'action', $action, $this->page ) ),
			$action
		);
	}

	/**
	 * Obscure `password` URL parameter.
	 *
	 * @param string $url
	 * @return string
	 */
	public function obscure_url_secrets( $url )
	{
		return preg_replace(
			'/password=[^&]+/',
			'password=*****',
			(string) $url
		);
	}

	/**
	 * The capability required to manage Redis.
	 *
	 * @return string
	 */
	public function manage_redis_capability()
	{
		if ( defined( 'WP_REDIS_MANAGER_CAPABILITY' ) && WP_REDIS_MANAGER_CAPABILITY ) {
			return WP_REDIS_MANAGER_CAPABILITY;
		}

		$capability = is_multisite() ? 'manage_network_options' : 'manage_options';

		/**
		 * Filters the capability used to determine if a user can manage Redis.
		 *
		 * @since 2.6.0
		 * @param string   $capability The default capability to determine if the user can manage cache.
		 */
		return apply_filters( 'icc_gg_wordpress_super_cache_manager_capability', $capability );
	}

	/**
	 * Does the current user have the capability to manage Redis?
	 *
	 * @return bool
	 */
	public function current_user_can_manage_redis()
	{
		return current_user_can( $this->manage_redis_capability() );
	}

	/**
	 * Prevent LiteSpeed Cache from overwriting the `object-cache.php` drop-in.
	 *
	 * @return void
	 */
	public function litespeed_disable_objectcache()
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['LSCWP_CTRL'], $_POST['LSCWP_NONCE'], $_POST['object'] ) ) {
			$_POST['object'] = '0';
		}
	}

}
