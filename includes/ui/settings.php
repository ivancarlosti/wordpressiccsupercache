<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Admin settings page template.
 *
 * @package   ICC_GG_WordPress_Super_Cache
 * @category  General
 * @author    Ivan Carlos
 * @copyright 2007-2026 Ivan Carlos Consultoria
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0-or-later
 */
?>
<div id="icc-gg-wordpress-super-cache" class="wrap">

	<h1>
		<?php esc_html_e( 'ICC.gg WordPress Super Cache', 'icc-gg-wordpress-super-cache' ); ?>
	</h1>

	<?php settings_errors(); ?>

	<div class="columns">

		<div class="content-column">

			<h2 class="nav-tab-wrapper">
				<?php foreach ( ICC_GG_WordPress_Super_Cache_UI::get_tabs() as $ui_tab ) : ?>
					<?php if ( $ui_tab->is_disabled() ) : ?>

						<span
							class="<?php echo esc_attr( $ui_tab->nav_classes() ); ?>"
							title="<?php echo esc_attr( $ui_tab->disabled_notice() ); ?>"
						>
							<?php echo esc_html( $ui_tab->label() ); ?>
						</span>

					<?php else : ?>

						<a
							id="<?php echo esc_attr( $ui_tab->nav_id() ); ?>"
							class="<?php echo esc_attr( $ui_tab->nav_classes() ); ?>"
							data-toggle="<?php echo esc_attr( $ui_tab->slug() ); ?>"
							href="#<?php echo esc_attr( $ui_tab->slug() ); ?>"
						>
							<?php echo esc_html( $ui_tab->label() ); ?>
						</a>

					<?php endif; ?>
				<?php endforeach; ?>
			</h2>

			<div class="tab-content">
				<?php foreach ( ICC_GG_WordPress_Super_Cache_UI::get_tabs() as $ui_tab ) : ?>
					<?php if ( ! $ui_tab->is_disabled() ) : ?>
						<div id="<?php echo esc_attr( $ui_tab->id() ); ?>"
							class="<?php echo esc_attr( $ui_tab->classes() ); ?>"
						>
							<?php $ui_tab->display(); ?>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>

		</div>

	</div>

	<hr style="margin-top: 30px;">
	<p style="text-align: center; color: #666; font-size: 12px;">
		<?php esc_html_e( 'ICC.gg WordPress Super Cache - Redis object cache and Nginx page cache in one plugin. Enable, configure and purge each layer independently.', 'icc-gg-wordpress-super-cache' ); ?><br>
		<a href="https://github.com/ivancarlosti/wordpressiccsupercache" target="_blank" rel="noopener noreferrer">
			github.com/ivancarlosti/wordpressiccsupercache
		</a><br>
		<?php echo esc_html( sprintf( 'v%s', ICC_GG_WORDPRESS_SUPER_CACHE_VERSION ) ); ?>
	</p>

</div>
