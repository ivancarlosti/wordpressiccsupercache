<?php
/**
 * Plugin Name: ICC.gg Page Cache Loader
 * Description: Loads the ICC.gg page cache when wp-config.php cannot be edited (no WP_CACHE constant). Installed by the ICC.gg WordPress Super Cache plugin; do not edit.
 * Plugin URI: https://github.com/ivancarlosti/wordpressiccsupercache
 * Version: {{ICC_GG_PAGE_CACHE_VERSION}}
 * Author: Ivan Carlos
 * Author URI: https://ivancarlosti.com
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.txt
 *
 * @package ICC_GG_WordPress_Super_Cache
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ICC_GG_PAGE_CACHE_HANDLED' ) && ! defined( 'ICC_GG_SUPER_CACHE_DISABLE_PAGE_CACHE' ) ) {
	define( 'ICC_GG_PAGE_CACHE_HANDLED', true );
	define( 'ICC_GG_PAGE_CACHE_LOADER', 'mu-plugin' );
	define( 'ICC_GG_PAGE_CACHE_DIR', '{{ICC_GG_PAGE_CACHE_DIR}}' );

	$icc_gg_page_cache_engine = ICC_GG_PAGE_CACHE_DIR . '/engine.php';

	if ( is_readable( $icc_gg_page_cache_engine ) ) {
		require_once $icc_gg_page_cache_engine;

		if ( function_exists( 'icc_gg_page_cache_bootstrap' ) ) {
			icc_gg_page_cache_bootstrap();
		}
	}

	unset( $icc_gg_page_cache_engine );
}
