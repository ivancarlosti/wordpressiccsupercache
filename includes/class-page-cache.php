<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Page cache registry.
 *
 * Keeps track of the page cache backends shipped with the plugin (local PHP
 * page cache, Varnish and Nginx) and enforces that only one of them is
 * enabled at a time. It also owns the automatic purge hooks shared by every
 * backend so content changes cannot flush the same layer twice.
 *
 * @package   ICC_GG_WordPress_Super_Cache
 * @category  General
 * @author    Ivan Carlos
 * @copyright 2007-2026 Ivan Carlos Consultoria
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0-or-later
 */

/**
 * Page cache registry class definition
 */
class ICC_GG_WordPress_Super_Cache_Page_Cache
{
	/**
	 * Option name holding the active page cache backend.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'icc_gg_wordpress_super_cache_page_cache';

	/**
	 * Local PHP page cache backend slug.
	 *
	 * @var string
	 */
	const LOCAL = 'local';

	/**
	 * Varnish page cache backend slug.
	 *
	 * @var string
	 */
	const VARNISH = 'varnish';

	/**
	 * Nginx page cache backend slug.
	 *
	 * @var string
	 */
	const NGINX = 'nginx';

	/**
	 * Singleton instance of self.
	 *
	 * @var ICC_GG_WordPress_Super_Cache_Page_Cache|null
	 */
	private static $instance;

	/**
	 * Registry state, or null when it still needs to be read.
	 *
	 * @var array|null
	 */
	private $state = null;

	/**
	 * Whether the shared hooks have been registered already.
	 *
	 * @var bool
	 */
	private $hooks_registered = false;

	/**
	 * Create (if needed) and return a singleton of self.
	 *
	 * @return ICC_GG_WordPress_Super_Cache_Page_Cache
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
		$this->maybe_migrate();
		$this->register_hooks();
	}

	/**
	 * Returns the registered backends and their labels.
	 *
	 * @return array<string, string>
	 */
	public function backends()
	{
		/**
		 * Filters the available page cache backends.
		 *
		 * The array must stay a `slug => label` map; slugs are used as option
		 * values and action suffixes.
		 *
		 * @param array $backends Backend labels keyed by slug.
		 */
		return (array) apply_filters(
			'icc_gg_wordpress_super_cache_page_cache_backends',
			[
				self::LOCAL => __( 'Page Cache', 'icc-gg-wordpress-super-cache' ),
				self::VARNISH => __( 'Varnish', 'icc-gg-wordpress-super-cache' ),
				self::NGINX => __( 'Nginx', 'icc-gg-wordpress-super-cache' ),
			]
		);
	}

	/**
	 * Returns the label of a backend.
	 *
	 * @param string $slug Backend slug.
	 * @return string
	 */
	public function label( $slug )
	{
		$backends = $this->backends();

		return isset( $backends[ $slug ] ) ? $backends[ $slug ] : $slug;
	}

	/**
	 * Whether the backends are mutually exclusive.
	 *
	 * @return bool
	 */
	public function is_exclusive()
	{
		/**
		 * Filters whether only one page cache backend may be enabled at a time.
		 *
		 * Varnish usually sits in front of Nginx, so advanced setups may want
		 * to run both layers. Returning false lifts the mutual exclusion.
		 *
		 * @param bool $exclusive Whether backends are exclusive.
		 */
		return (bool) apply_filters( 'icc_gg_wordpress_super_cache_page_cache_exclusive', true );
	}

	/**
	 * Returns the raw registry state.
	 *
	 * @return array
	 */
	public function get_state()
	{
		if ( ! isset( $this->state ) ) {
			$stored = get_option( self::OPTION_KEY, [] );
			$stored = is_array( $stored ) ? $stored : [];

			$this->state = wp_parse_args(
				$stored,
				[
					'active' => '',
				]
			);
		}

		return $this->state;
	}

	/**
	 * Forgets the cached registry state.
	 *
	 * @return void
	 */
	public function flush_state_cache()
	{
		$this->state = null;
	}

	/**
	 * Returns the active backend slug, or an empty string.
	 *
	 * @return string
	 */
	public function active()
	{
		if ( defined( 'ICC_GG_SUPER_CACHE_PAGE_CACHE' ) && ICC_GG_SUPER_CACHE_PAGE_CACHE ) {
			$forced = (string) ICC_GG_SUPER_CACHE_PAGE_CACHE;

			return array_key_exists( $forced, $this->backends() ) ? $forced : '';
		}

		$state = $this->get_state();

		return array_key_exists( $state['active'], $this->backends() ) ? $state['active'] : '';
	}

	/**
	 * Whether a specific backend is the active one.
	 *
	 * @param string $slug Backend slug.
	 * @return bool
	 */
	public function is_active( $slug )
	{
		return $this->active() === $slug;
	}

	/**
	 * Returns the backend controller instance.
	 *
	 * @param string $slug Backend slug.
	 * @return object|null
	 */
	public function backend( $slug )
	{
		switch ( $slug ) {
			case self::LOCAL:
				return class_exists( 'ICC_GG_WordPress_Super_Cache_Local_Cache' )
					? ICC_GG_WordPress_Super_Cache_Local_Cache::instance()
					: null;

			case self::VARNISH:
				return class_exists( 'ICC_GG_WordPress_Super_Cache_Varnish_Cache' )
					? ICC_GG_WordPress_Super_Cache_Varnish_Cache::instance()
					: null;

			case self::NGINX:
				return class_exists( 'ICC_GG_WordPress_Super_Cache_Nginx_Cache' )
					? ICC_GG_WordPress_Super_Cache_Nginx_Cache::instance()
					: null;
		}

		return null;
	}

	/**
	 * Returns every backend reporting itself as enabled.
	 *
	 * More than one entry means a configuration conflict, usually caused by
	 * constants in `wp-config.php` that the UI cannot unset.
	 *
	 * @return string[]
	 */
	public function enabled_backends()
	{
		$enabled = [];

		foreach ( array_keys( $this->backends() ) as $slug ) {
			$backend = $this->backend( $slug );

			if ( $backend && method_exists( $backend, 'is_enabled' ) && $backend->is_enabled() ) {
				$enabled[] = $slug;
			}
		}

		return $enabled;
	}

	/**
	 * Builds the conflict error for a backend.
	 *
	 * @param string   $slug   Backend slug the user wants to enable.
	 * @param string[] $others Backends that are already enabled.
	 * @return WP_Error
	 */
	protected function conflict_error( $slug, $others )
	{
		$labels = array_map( [ $this, 'label' ], $others );

		return new WP_Error(
			'icc_gg_page_cache_conflict',
			sprintf(
				// translators: 1: enabled page cache names, 2: page cache the user wants to enable.
				__( 'The %1$s page cache is enabled. Disable it before enabling the %2$s page cache.', 'icc-gg-wordpress-super-cache' ),
				implode( ', ', $labels ),
				$this->label( $slug )
			)
		);
	}

	/**
	 * Checks whether a backend can be enabled right now.
	 *
	 * @param string $slug Backend slug.
	 * @return true|WP_Error
	 */
	public function can_activate( $slug )
	{
		if ( defined( 'ICC_GG_SUPER_CACHE_PAGE_CACHE' ) && ICC_GG_SUPER_CACHE_PAGE_CACHE ) {
			$forced = (string) ICC_GG_SUPER_CACHE_PAGE_CACHE;

			if ( $forced !== $slug ) {
				return new WP_Error(
					'icc_gg_page_cache_constant',
					sprintf(
						// translators: 1: forced backend name, 2: backend the user wants to enable.
						__( 'The %1$s cache is forced by the ICC_GG_SUPER_CACHE_PAGE_CACHE constant in wp-config.php, so the %2$s cache cannot be enabled.', 'icc-gg-wordpress-super-cache' ),
						$this->label( $forced ),
						$this->label( $slug )
					)
				);
			}

			return true;
		}

		if ( ! $this->is_exclusive() ) {
			return true;
		}

		$others = array_values( array_diff( $this->enabled_backends(), [ $slug ] ) );

		if ( $others ) {
			return $this->conflict_error( $slug, $others );
		}

		return true;
	}

	/**
	 * Enables or disables a backend, enforcing the mutual exclusion.
	 *
	 * @param string $slug    Backend slug.
	 * @param bool   $enabled Whether the backend should be enabled.
	 * @return true|WP_Error
	 */
	public function set_active( $slug, $enabled )
	{
		if ( ! array_key_exists( $slug, $this->backends() ) ) {
			return new WP_Error(
				'icc_gg_page_cache_unknown',
				__( 'Unknown page cache backend.', 'icc-gg-wordpress-super-cache' )
			);
		}

		if ( $enabled ) {
			$check = $this->can_activate( $slug );

			if ( is_wp_error( $check ) ) {
				return $check;
			}
		}

		$state = $this->get_state();

		$state['active'] = $enabled
			? $slug
			: ( $state['active'] === $slug ? '' : $state['active'] );

		update_option( self::OPTION_KEY, $state );

		$this->state = $state;

		/**
		 * Fires after a page cache backend was enabled or disabled.
		 *
		 * @param string $active  The new active backend slug.
		 * @param string $backend The backend that changed.
		 * @param bool   $enabled Whether the backend was enabled or disabled.
		 */
		do_action( 'icc_gg_wordpress_super_cache_page_cache_changed', $state['active'], $slug, (bool) $enabled );

		return true;
	}

	/**
	 * Migrates the pre-registry Nginx setting into the registry.
	 *
	 * @return void
	 */
	public function maybe_migrate()
	{
		$stored = get_option( self::OPTION_KEY, false );

		if ( is_array( $stored ) && isset( $stored['active'] ) ) {
			return;
		}

		$active = '';

		// Nginx first: it was the only backend before the registry existed.
		foreach ( [ self::NGINX, self::LOCAL, self::VARNISH ] as $slug ) {
			$option_key = $this->option_key( $slug );

			if ( '' === $option_key ) {
				continue;
			}

			$settings = get_option( $option_key, [] );

			if ( is_array( $settings ) && ! empty( $settings['enabled'] ) ) {
				$active = $slug;

				break;
			}
		}

		update_option(
			self::OPTION_KEY,
			[
				'active' => $active,
			]
		);

		$this->state = null;
	}

	/**
	 * Returns the option name a backend stores its settings in.
	 *
	 * @param string $slug Backend slug.
	 * @return string Empty string for unknown backends.
	 */
	protected function option_key( $slug )
	{
		switch ( $slug ) {
			case self::NGINX:
				return class_exists( 'ICC_GG_WordPress_Super_Cache_Nginx_Cache' )
					? ICC_GG_WordPress_Super_Cache_Nginx_Cache::OPTION_KEY
					: 'icc_gg_wordpress_super_cache_nginx_cache';

			case self::LOCAL:
				return class_exists( 'ICC_GG_WordPress_Super_Cache_Local_Cache' )
					? ICC_GG_WordPress_Super_Cache_Local_Cache::OPTION_KEY
					: 'icc_gg_wordpress_super_cache_local_cache';

			case self::VARNISH:
				return class_exists( 'ICC_GG_WordPress_Super_Cache_Varnish_Cache' )
					? ICC_GG_WordPress_Super_Cache_Varnish_Cache::OPTION_KEY
					: 'icc_gg_wordpress_super_cache_varnish_cache';
		}

		return '';
	}

	/**
	 * Registers the shared automatic purge hooks.
	 *
	 * @return void
	 */
	public function register_hooks()
	{
		if ( $this->hooks_registered ) {
			return;
		}

		$this->hooks_registered = true;

		add_action( 'init', [ $this, 'register_purge_actions' ], 20 );
	}

	/**
	 * Returns the actions that trigger an automatic page cache purge.
	 *
	 * @return string[]
	 */
	public function default_purge_actions()
	{
		return [
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
			'activated_plugin',
			'deactivated_plugin',
		];
	}

	/**
	 * Hooks the purge callback to every content change trigger.
	 *
	 * @return void
	 */
	public function register_purge_actions()
	{
		if ( ! $this->enabled_backends() ) {
			return;
		}

		/**
		 * Filters the actions that trigger an automatic page cache purge.
		 *
		 * @param string[] $purge_actions Hook names.
		 */
		$purge_actions = (array) apply_filters(
			'icc_gg_wordpress_super_cache_page_cache_purge_actions',
			$this->default_purge_actions()
		);

		// Backwards compatibility with the pre-registry Nginx filter.
		$purge_actions = (array) apply_filters(
			'icc_gg_wordpress_super_cache_nginx_purge_actions',
			$purge_actions
		);

		foreach ( array_unique( $purge_actions ) as $action ) {
			if ( did_action( $action ) ) {
				$this->purge_enabled_once();
			} else {
				add_action( $action, [ $this, 'purge_enabled_once' ] );
			}
		}
	}

	/**
	 * Purges every enabled backend at most once per request.
	 *
	 * @return void
	 */
	public function purge_enabled_once()
	{
		static $completed = false;

		if ( $completed ) {
			return;
		}

		$completed = true;

		foreach ( $this->enabled_backends() as $slug ) {
			$backend = $this->backend( $slug );

			if ( ! $backend ) {
				continue;
			}

			if ( method_exists( $backend, 'auto_purge_enabled' ) && ! $backend->auto_purge_enabled() ) {
				continue;
			}

			$this->purge_backend( $slug );
		}
	}

	/**
	 * Purges a single backend.
	 *
	 * @param string $slug Backend slug.
	 * @return true|false|WP_Error
	 */
	public function purge_backend( $slug )
	{
		$backend = $this->backend( $slug );

		if ( ! $backend ) {
			return new WP_Error(
				'icc_gg_page_cache_unavailable',
				__( 'The page cache backend is not available.', 'icc-gg-wordpress-super-cache' )
			);
		}

		if ( self::NGINX === $slug && method_exists( $backend, 'purge_zone' ) ) {
			return $backend->purge_zone();
		}

		if ( method_exists( $backend, 'purge' ) ) {
			return $backend->purge();
		}

		return new WP_Error(
			'icc_gg_page_cache_unsupported',
			__( 'This page cache backend cannot be purged.', 'icc-gg-wordpress-super-cache' )
		);
	}

	/**
	 * Purges the active backend.
	 *
	 * @return true|false|WP_Error
	 */
	public function purge_active()
	{
		$active = $this->active();

		if ( '' === $active ) {
			return new WP_Error(
				'icc_gg_page_cache_inactive',
				__( 'No page cache is enabled.', 'icc-gg-wordpress-super-cache' )
			);
		}

		return $this->purge_backend( $active );
	}

	/**
	 * Returns the human readable status of the registry.
	 *
	 * @return string
	 */
	public function get_status()
	{
		$enabled = $this->enabled_backends();

		if ( ! $enabled ) {
			return __( 'No page cache enabled', 'icc-gg-wordpress-super-cache' );
		}

		if ( count( $enabled ) > 1 ) {
			return sprintf(
				// translators: %s = Comma separated list of enabled page caches.
				__( 'Conflict: %s are enabled at the same time', 'icc-gg-wordpress-super-cache' ),
				implode( ', ', array_map( [ $this, 'label' ], $enabled ) )
			);
		}

		$backend = $this->backend( $enabled[0] );

		if ( $backend && method_exists( $backend, 'get_status' ) ) {
			return $backend->get_status();
		}

		return $this->label( $enabled[0] );
	}
}
