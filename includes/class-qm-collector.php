<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Query Monitor data collector class.
 *
 * @package   ICC_GG_WordPress_Super_Cache
 * @category  General
 * @author    Ivan Carlos
 * @copyright 2007-2026 Ivan Carlos Consultoria
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0-or-later
 */

/**
 * Query Monitor data collector class definition
 */
class ICC_GG_WordPress_Super_Cache_QM_Collector extends QM_Collector
{
	/**
	 * Collector id
	 *
	 * @var string $id
	 */
	public $id = 'cache';

	/**
	 * Retrieves the collector name
	 *
	 * @return string
	 */
	public function name()
	{
		return __( 'Object Cache', 'icc-gg-wordpress-super-cache' );
	}

	/**
	 * The collector storage
	 *
	 * @return \QM_Data
	 */
	public function get_storage(): QM_Data
	{
		return new QM_Data_Cache();
	}

	/**
	 * Fills the collector with data
	 *
	 * @return void
	 */
	public function process()
	{
		global $wp_object_cache;

		$this->process_defaults();

		$roc = ICC_GG_WordPress_Super_Cache_Plugin::instance();

		$this->data['status'] = $roc->get_status();
		$this->data['has_dropin'] = $roc->object_cache_dropin_exists();
		$this->data['valid_dropin'] = $roc->validate_object_cache_dropin();

		if ( ! method_exists( $wp_object_cache, 'info' ) ) {
			return;
		}

		$info = $wp_object_cache->info();

		$this->data['hits'] = $info->hits;
		$this->data['misses'] = $info->misses;
		$this->data['ratio'] = $info->ratio;
		$this->data['bytes'] = $info->bytes;

		$this->data['errors'] = $info->errors;
		$this->data['meta'] = $info->meta;

		if ( defined( 'WP_REDIS_DISABLED' ) && WP_REDIS_DISABLED ) {
			$this->data['meta'][ __( 'Disabled', 'icc-gg-wordpress-super-cache' ) ] = __( 'Yes', 'icc-gg-wordpress-super-cache' );
		}

		$this->data['groups'] = [
			'global' => $info->groups->global,
			'non_persistent' => $info->groups->non_persistent,
			'unflushable' => $info->groups->unflushable,
		];

		// These are used by Query Monitor
		$this->data['cache_hit_percentage'] = $info->ratio;

		if ( $this->data instanceof QM_Data_Cache ) {
			$this->data->stats['cache_hits'] = $info->hits;
			$this->data->stats['cache_misses'] = $info->misses;
		} else {
			$this->data['stats']['cache_hits'] = $info->hits;
			$this->data['stats']['cache_misses'] = $info->misses;
		}
	}

	/**
	 * Sets collector defaults
	 *
	 * @return void
	 */
	public function process_defaults()
	{
		$this->data['hits'] = 0;
		$this->data['misses'] = 0;
		$this->data['ratio'] = 0;
		$this->data['bytes'] = 0;

		$this->data['cache_hit_percentage'] = 0;

		$this->data['object_cache_extensions'] = [];
		$this->data['opcode_cache_extensions'] = [];

		if ( function_exists( 'extension_loaded' ) ) {
			$this->data['object_cache_extensions'] = array_map(
				'extension_loaded',
				[
					'APCu' => 'apcu',
					'Redis' => 'redis',
					'Relay' => 'relay',
					'Memcache' => 'memcache',
					'Memcached' => 'memcached',
				]
			);

			$this->data['opcode_cache_extensions'] = array_map(
				'extension_loaded',
				[
					'APC' => 'APC',
					'Zend OPcache' => 'Zend OPcache',
				]
			);
		}

		$this->data['has_object_cache'] = (bool) wp_using_ext_object_cache();
		$this->data['has_opcode_cache'] = array_filter( $this->data['opcode_cache_extensions'] ) ? true : false;

		$this->data['display_hit_rate_warning'] = false;
		$this->data['ext_object_cache'] = $this->data['has_object_cache'];
	}
}
