<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Varnish cache tab template.
 *
 * @package   ICC_GG_WordPress_Super_Cache
 * @category  General
 * @author    Ivan Carlos
 * @copyright 2007-2026 Ivan Carlos Consultoria
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0-or-later
 */

/** @var \ICC_GG_WordPress_Super_Cache_Plugin $roc */
$varnish = ICC_GG_WordPress_Super_Cache_Varnish_Cache::instance();

$varnish_settings = $varnish->get_settings();
$varnish_enabled = $varnish->is_enabled();
$varnish_auto_purge = $varnish->auto_purge_enabled();
$varnish_endpoint = $varnish->endpoint_url();
$varnish_test = get_transient( 'icc_gg_wordpress_super_cache_varnish_test' );
?>

<table class="form-table" style="margin-top: 20px;">

	<tr>
		<th><?php esc_html_e( 'Status:', 'icc-gg-wordpress-super-cache' ); ?></th>
		<td>
			<span class="<?php echo $varnish_enabled ? 'success' : 'warning'; ?>">
				<span class="dashicons <?php echo $varnish_enabled ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
				<?php echo esc_html( $varnish->get_status() ); ?>
			</span>
		</td>
	</tr>

	<tr>
		<th><?php esc_html_e( 'Purge Endpoint:', 'icc-gg-wordpress-super-cache' ); ?></th>
		<td>
			<code><?php echo esc_html( is_wp_error( $varnish_endpoint ) ? '' : $varnish_endpoint ); ?></code>

			<p class="description">
				<?php esc_html_e( 'The plugin sends PURGE or BAN requests to this address, using the site host header so Varnish can match the virtual host.', 'icc-gg-wordpress-super-cache' ); ?>
			</p>
		</td>
	</tr>

	<?php if ( is_array( $varnish_test ) ) : ?>
		<tr>
			<th><?php esc_html_e( 'Last Test:', 'icc-gg-wordpress-super-cache' ); ?></th>
			<td>
				<span class="<?php echo ! empty( $varnish_test['ok'] ) ? 'success' : 'warning'; ?>">
					<?php echo esc_html( isset( $varnish_test['message'] ) ? $varnish_test['message'] : '' ); ?>
				</span>

				<?php if ( ! empty( $varnish_test['probe']['headers'] ) ) : ?>
					<ul class="description">
						<?php foreach ( $varnish_test['probe']['headers'] as $varnish_header => $varnish_value ) : ?>
							<li><code><?php echo esc_html( $varnish_header . ': ' . $varnish_value ); ?></code></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php if ( isset( $varnish_test['purge']['message'] ) ) : ?>
					<p class="description">
						<?php echo esc_html( $varnish_test['purge']['message'] ); ?>
					</p>
				<?php endif; ?>
			</td>
		</tr>
	<?php endif; ?>

</table>

<h2 class="title"><?php esc_html_e( 'Varnish Cache Settings', 'icc-gg-wordpress-super-cache' ); ?></h2>

<form method="post" action="">
	<?php wp_nonce_field( 'icc_gg_wordpress_super_cache_varnish' ); ?>

	<table class="form-table">

		<tr>
			<th scope="row"><?php esc_html_e( 'Varnish Cache', 'icc-gg-wordpress-super-cache' ); ?></th>
			<td>
				<input type="hidden" name="icc_gg_wordpress_super_cache_varnish[enabled]" value="0" />
				<input
					type="checkbox"
					id="icc-gg-wordpress-super-cache-varnish-enabled"
					name="icc_gg_wordpress_super_cache_varnish[enabled]"
					value="1"
					<?php checked( $varnish_enabled ); ?>
					<?php disabled( $varnish->is_overridden( 'enabled' ) ); ?>
				/>
				<label for="icc-gg-wordpress-super-cache-varnish-enabled">
					<?php esc_html_e( 'Purge the Varnish cache in front of this site', 'icc-gg-wordpress-super-cache' ); ?>
				</label>

				<p class="description">
					<?php esc_html_e( 'Only one page cache backend can be enabled at a time: enabling Varnish Cache requires disabling the local page cache and the nginx FastCGI cache first.', 'icc-gg-wordpress-super-cache' ); ?>
				</p>

				<?php if ( $varnish->is_overridden( 'enabled' ) ) : ?>
					<p class="description">
						<?php esc_html_e( 'Overridden by the ICC_GG_SUPER_CACHE_VARNISH_ENABLED constant in wp-config.php.', 'icc-gg-wordpress-super-cache' ); ?>
					</p>
				<?php endif; ?>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="icc-gg-wordpress-super-cache-varnish-host">
					<?php esc_html_e( 'Varnish Host', 'icc-gg-wordpress-super-cache' ); ?>
				</label>
			</th>
			<td>
				<input
					type="text"
					class="regular-text code"
					id="icc-gg-wordpress-super-cache-varnish-host"
					name="icc_gg_wordpress_super_cache_varnish[host]"
					value="<?php echo esc_attr( (string) $varnish->get_setting( 'host' ) ); ?>"
					placeholder="127.0.0.1"
					<?php disabled( $varnish->is_overridden( 'host' ) ); ?>
				/>

				<label for="icc-gg-wordpress-super-cache-varnish-port">
					<?php esc_html_e( 'Port', 'icc-gg-wordpress-super-cache' ); ?>
				</label>
				<input
					type="number"
					min="1"
					max="65535"
					class="small-text"
					id="icc-gg-wordpress-super-cache-varnish-port"
					name="icc_gg_wordpress_super_cache_varnish[port]"
					value="<?php echo esc_attr( (int) $varnish->get_setting( 'port' ) ); ?>"
					<?php disabled( $varnish->is_overridden( 'port' ) ); ?>
				/>

				<p class="description">
					<?php esc_html_e( 'Address Varnish listens on. Inside a Docker network this is usually the service name, for example varnish.', 'icc-gg-wordpress-super-cache' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="icc-gg-wordpress-super-cache-varnish-method">
					<?php esc_html_e( 'Purge Method', 'icc-gg-wordpress-super-cache' ); ?>
				</label>
			</th>
			<td>
				<select
					id="icc-gg-wordpress-super-cache-varnish-method"
					name="icc_gg_wordpress_super_cache_varnish[method]"
				>
					<option value="purge" <?php selected( 'purge', $varnish->get_setting( 'method' ) ); ?>><?php esc_html_e( 'PURGE (single URL)', 'icc-gg-wordpress-super-cache' ); ?></option>
					<option value="ban" <?php selected( 'ban', $varnish->get_setting( 'method' ) ); ?>><?php esc_html_e( 'BAN (pattern, Varnish 4+)', 'icc-gg-wordpress-super-cache' ); ?></option>
				</select>

				<label for="icc-gg-wordpress-super-cache-varnish-timeout">
					<?php esc_html_e( 'Timeout (seconds)', 'icc-gg-wordpress-super-cache' ); ?>
				</label>
				<input
					type="number"
					min="1"
					max="30"
					class="small-text"
					id="icc-gg-wordpress-super-cache-varnish-timeout"
					name="icc_gg_wordpress_super_cache_varnish[timeout]"
					value="<?php echo esc_attr( (int) $varnish->get_setting( 'timeout' ) ); ?>"
				/>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="icc-gg-wordpress-super-cache-varnish-key">
					<?php esc_html_e( 'Purge Key', 'icc-gg-wordpress-super-cache' ); ?>
				</label>
			</th>
			<td>
				<input
					type="text"
					class="regular-text code"
					id="icc-gg-wordpress-super-cache-varnish-key"
					name="icc_gg_wordpress_super_cache_varnish[key]"
					value="<?php echo esc_attr( (string) $varnish->get_setting( 'key' ) ); ?>"
					autocomplete="off"
					<?php disabled( $varnish->is_overridden( 'key' ) ); ?>
				/>

				<p class="description">
					<?php esc_html_e( 'Optional. When set, it is sent as the X-Purge-Key header and the VCL snippet below rejects requests without it.', 'icc-gg-wordpress-super-cache' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Auto Purge', 'icc-gg-wordpress-super-cache' ); ?></th>
			<td>
				<input type="hidden" name="icc_gg_wordpress_super_cache_varnish[auto_purge]" value="0" />
				<input
					type="checkbox"
					id="icc-gg-wordpress-super-cache-varnish-auto-purge"
					name="icc_gg_wordpress_super_cache_varnish[auto_purge]"
					value="1"
					<?php checked( $varnish_auto_purge ); ?>
					<?php disabled( $varnish->is_overridden( 'auto_purge' ) ); ?>
				/>
				<label for="icc-gg-wordpress-super-cache-varnish-auto-purge">
					<?php esc_html_e( 'Purge Varnish when posts, comments, menus or themes change', 'icc-gg-wordpress-super-cache' ); ?>
				</label>

				<?php if ( $varnish->is_overridden( 'auto_purge' ) ) : ?>
					<p class="description">
						<?php esc_html_e( 'Overridden by the ICC_GG_SUPER_CACHE_VARNISH_AUTO_PURGE constant in wp-config.php.', 'icc-gg-wordpress-super-cache' ); ?>
					</p>
				<?php endif; ?>
			</td>
		</tr>

	</table>

	<p class="submit">
		<?php submit_button( __( 'Save Varnish Settings', 'icc-gg-wordpress-super-cache' ), 'primary', 'submit', false ); ?>

		<?php if ( $varnish_enabled ) : ?>
			<a class="button button-secondary" href="<?php echo esc_attr( $roc->action_link( 'purge-varnish-cache' ) ); ?>">
				<?php esc_html_e( 'Purge Varnish Cache', 'icc-gg-wordpress-super-cache' ); ?>
			</a>
		<?php endif; ?>

		<a class="button" href="<?php echo esc_attr( $roc->action_link( 'test-varnish-cache' ) ); ?>">
			<?php esc_html_e( 'Test Connection', 'icc-gg-wordpress-super-cache' ); ?>
		</a>
	</p>

</form>

<h2 class="title"><?php esc_html_e( 'Varnish Cache Configuration', 'icc-gg-wordpress-super-cache' ); ?></h2>

<p>
	<?php esc_html_e( 'The plugin only sends purge requests; Varnish has to accept them. Add this to the vcl_recv subroutine of your VCL:', 'icc-gg-wordpress-super-cache' ); ?>
</p>

<pre class="icc-gg-wordpress-super-cache-code"><?php echo esc_html( $varnish->vcl_snippet() ); ?></pre>

<h2 class="title"><?php esc_html_e( 'Varnish Cache Exclusions', 'icc-gg-wordpress-super-cache' ); ?></h2>

<p>
	<?php esc_html_e( 'Varnish answers before WordPress runs, so the URL exclusions on the Page Cache tab are not applied to it. Merge this into the same vcl_recv subroutine, before the lookups, so the excluded paths and logged in visitors always reach WordPress:', 'icc-gg-wordpress-super-cache' ); ?>
</p>

<pre class="icc-gg-wordpress-super-cache-code"><?php echo esc_html( $varnish->vcl_bypass_snippet() ); ?></pre>

<p class="description">
	<?php esc_html_e( 'PURGE and BAN remove whatever Varnish stored; they do not respect the exclusion list either. The snippet above is generated from the saved exclusions, so update your VCL when you change them.', 'icc-gg-wordpress-super-cache' ); ?>
</p>

<p class="description">
	<?php esc_html_e( 'The same values can be defined in wp-config.php with the ICC_GG_SUPER_CACHE_VARNISH_ENABLED, ICC_GG_SUPER_CACHE_VARNISH_HOST, ICC_GG_SUPER_CACHE_VARNISH_PORT, ICC_GG_SUPER_CACHE_VARNISH_KEY and ICC_GG_SUPER_CACHE_VARNISH_AUTO_PURGE constants.', 'icc-gg-wordpress-super-cache' ); ?>
</p>

