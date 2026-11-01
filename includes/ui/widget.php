<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Dashboard widget template.
 *
 * @package   ICC_GG_WordPress_Super_Cache
 * @category  General
 * @author    Ivan Carlos
 * @copyright 2007-2026 Ivan Carlos Consultoria
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0-or-later
 */

/** @var \ICC_GG_WordPress_Super_Cache_Plugin $this */

?>
<div id="widget-redis-stats">

	<ul>
		<li>
			<a class="active" href="#" data-chart="time" title="<?php esc_attr_e( 'The total amount of time (in milliseconds) it took Redis to return cache data.', 'icc-gg-wordpress-super-cache' ); ?>">
				<?php esc_html_e( 'Time', 'icc-gg-wordpress-super-cache' ); ?>
			</a>
		</li>
		<li>
			<a href="#" data-chart="bytes" title="<?php esc_attr_e( 'The total amount of bytes that was retrieved from Redis.', 'icc-gg-wordpress-super-cache' ); ?>">
				<?php esc_html_e( 'Bytes', 'icc-gg-wordpress-super-cache' ); ?>
			</a>
		</li>
		<li>
			<a href="#" data-chart="ratio" title="<?php esc_attr_e( 'The hit/miss ratio of cache data that was already cached.', 'icc-gg-wordpress-super-cache' ); ?>">
				<?php esc_html_e( 'Ratio', 'icc-gg-wordpress-super-cache' ); ?>
			</a>
		</li>
		<li>
			<a href="#" data-chart="calls" title="<?php esc_attr_e( 'The total amount of commands sent to Redis.', 'icc-gg-wordpress-super-cache' ); ?>">
				<?php esc_html_e( 'Calls', 'icc-gg-wordpress-super-cache' ); ?>
			</a>
		</li>
		<li style="margin-left: auto;">
			<a href="<?php echo esc_url( network_admin_url( $this->page ) ); ?>">
				<?php esc_html_e( 'Settings', 'icc-gg-wordpress-super-cache' ); ?>
			</a>
		</li>
	</ul>

	<div id="redis-stats-chart"></div>

</div>
