<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Nginx cache tab template.
 *
 * @package   ICC_GG_WordPress_Super_Cache
 * @category  General
 * @author    Ivan Carlos
 * @copyright 2007-2026 Ivan Carlos Consultoria
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0-or-later
 */

/** @var \ICC_GG_WordPress_Super_Cache_Plugin $roc */
$nginx = ICC_GG_WordPress_Super_Cache_Nginx_Cache::instance();

$nginx_path = $nginx->get_cache_path();
$nginx_enabled = $nginx->is_enabled();
$nginx_auto_purge = $nginx->auto_purge_enabled();
$nginx_path_valid = $nginx_path === '' ? null : $nginx->is_valid_path();
$nginx_path_usable = true === $nginx_path_valid;

$nginx_stats = $nginx_path_usable ? $nginx->get_cache_stats() : [ 'files' => 0, 'size' => 0 ];

$nginx_example_path = $nginx_path !== '' ? $nginx_path : '/var/cache/nginx/site';

// nginx answers before PHP runs, so the Page Cache exclusions have to be
// repeated here: the plugin cannot apply them for a FastCGI cache.
$nginx_exclusions = ICC_GG_WordPress_Super_Cache_Local_Cache::instance()->exclusion_regexes();

$nginx_exclusion_conf = '';

foreach ( $nginx_exclusions as $nginx_exclusion ) {
	$nginx_exclusion_conf .= sprintf(
		"    if (\$request_uri ~* \"%s\") { set \$icc_gg_skip_cache 1; }\n",
		$nginx_exclusion
	);
}

if ( '' === $nginx_exclusion_conf ) {
	$nginx_exclusion_conf = "    # No URL exclusions are configured on the Page Cache tab.\n";
}

$nginx_example_conf = sprintf(
	<<<'CONF'
# Object cache zones are declared in the http {} block of nginx.conf.
fastcgi_cache_path %1$s levels=1:2 keys_zone=icc_gg_super_cache:100m inactive=60m use_temp_path=off;

server {
    # Bypass the cache for logged in users, POST requests and query strings.
    set $icc_gg_skip_cache 0;
    if ($request_method = POST) { set $icc_gg_skip_cache 1; }
    if ($query_string != "") { set $icc_gg_skip_cache 1; }
    if ($http_cookie ~* "comment_author|wordpress_|wp-postpass_|wordpress_logged_in_") {
        set $icc_gg_skip_cache 1;
    }

    # The URL exclusions from the Page Cache tab, repeated for nginx.
%2$s
    location ~ \.php$ {
        fastcgi_cache icc_gg_super_cache;
        fastcgi_cache_key "$scheme$request_method$host$request_uri";
        fastcgi_cache_valid 200 301 302 60m;
        fastcgi_cache_bypass $icc_gg_skip_cache;
        fastcgi_no_cache $icc_gg_skip_cache;
        add_header X-FastCGI-Cache $upstream_cache_status;
    }
}
CONF,
	$nginx_example_path,
	$nginx_exclusion_conf
);

?>

<?php if ( $nginx_enabled && ! $nginx_path_usable ): ?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'The nginx FastCGI cache zone is unusable:', 'icc-gg-wordpress-super-cache' ); ?></strong>
			<?php echo esc_html( $nginx_path_valid instanceof \WP_Error ? $nginx_path_valid->get_error_message() : '' ); ?>
		</p>
	</div>
<?php endif; ?>

<table class="form-table" style="margin-top: 20px;">

	<tr>
		<th><?php esc_html_e( 'Status:', 'icc-gg-wordpress-super-cache' ); ?></th>
		<td>
			<?php if ( $nginx_enabled && $nginx_path_usable ) : ?>
				<span class="success">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php echo esc_html( $nginx->get_status() ); ?>
				</span>
			<?php else : ?>
				<span class="warning">
					<span class="dashicons dashicons-warning"></span>
					<?php echo esc_html( $nginx->get_status() ); ?>
				</span>
			<?php endif; ?>
		</td>
	</tr>

	<tr>
		<th><?php esc_html_e( 'Cache Zone Path:', 'icc-gg-wordpress-super-cache' ); ?></th>
		<td>
			<?php if ( $nginx_path === '' ) : ?>
				<span class="warning"><?php esc_html_e( 'Not set', 'icc-gg-wordpress-super-cache' ); ?></span>
			<?php else : ?>
				<code><?php echo esc_html( $nginx_path ); ?></code>
			<?php endif; ?>

			<?php if ( $nginx_path_valid instanceof \WP_Error ) : ?>
				<p class="description is-notice">
					<?php echo esc_html( $nginx_path_valid->get_error_message() ); ?>
				</p>
			<?php endif; ?>
		</td>
	</tr>

	<tr>
		<th><?php esc_html_e( 'Auto Purge:', 'icc-gg-wordpress-super-cache' ); ?></th>
		<td>
			<?php if ( $nginx_auto_purge ) : ?>
				<span class="success">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'Enabled', 'icc-gg-wordpress-super-cache' ); ?>
				</span>
			<?php else : ?>
				<span class="warning">
					<span class="dashicons dashicons-warning"></span>
					<?php esc_html_e( 'Disabled', 'icc-gg-wordpress-super-cache' ); ?>
				</span>
			<?php endif; ?>
		</td>
	</tr>

	<?php if ( $nginx_path_usable ) : ?>
		<tr>
			<th><?php esc_html_e( 'Cached Files:', 'icc-gg-wordpress-super-cache' ); ?></th>
			<td><code><?php echo esc_html( number_format_i18n( $nginx_stats['files'] ) ); ?></code></td>
		</tr>

		<tr>
			<th><?php esc_html_e( 'Cache Size:', 'icc-gg-wordpress-super-cache' ); ?></th>
			<td><code><?php echo esc_html( size_format( $nginx_stats['size'] ) ); ?></code></td>
		</tr>
	<?php endif; ?>

</table>

<h2 class="title">
	<?php esc_html_e( 'Settings', 'icc-gg-wordpress-super-cache' ); ?>
</h2>

<form method="post" action="">

	<?php wp_nonce_field( 'icc_gg_wordpress_super_cache_nginx' ); ?>

	<table class="form-table">

		<tr>
			<th scope="row"><?php esc_html_e( 'nginx FastCGI Cache', 'icc-gg-wordpress-super-cache' ); ?></th>
			<td>
				<input type="hidden" name="icc_gg_wordpress_super_cache_nginx[enabled]" value="0" />
				<input
					type="checkbox"
					id="icc-gg-wordpress-super-cache-nginx-enabled"
					name="icc_gg_wordpress_super_cache_nginx[enabled]"
					value="1"
					<?php checked( $nginx_enabled ); ?>
					<?php disabled( $nginx->is_overridden( 'enabled' ) ); ?>
				/>
				<label for="icc-gg-wordpress-super-cache-nginx-enabled">
					<?php esc_html_e( 'Enable the nginx FastCGI cache integration', 'icc-gg-wordpress-super-cache' ); ?>
				</label>

				<p class="description">
					<?php esc_html_e( 'Only one page cache backend can be enabled at a time: enabling nginx FastCGI requires disabling the local page cache and Varnish Cache first.', 'icc-gg-wordpress-super-cache' ); ?>
				</p>

				<?php if ( $nginx->is_overridden( 'enabled' ) ) : ?>
					<p class="description">
						<?php esc_html_e( 'Overridden by the ICC_GG_SUPER_CACHE_NGINX_ENABLED constant in wp-config.php.', 'icc-gg-wordpress-super-cache' ); ?>
					</p>
				<?php endif; ?>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="icc-gg-wordpress-super-cache-nginx-path">
					<?php esc_html_e( 'Cache Zone Path', 'icc-gg-wordpress-super-cache' ); ?>
				</label>
			</th>
			<td>
				<input
					type="text"
					class="regular-text code"
					id="icc-gg-wordpress-super-cache-nginx-path"
					name="icc_gg_wordpress_super_cache_nginx[path]"
					value="<?php echo esc_attr( (string) $nginx->get_setting( 'path' ) ); ?>"
					placeholder="/var/cache/nginx/site"
					<?php disabled( $nginx->is_overridden( 'path' ) ); ?>
				/>
				<p class="description">
					<?php esc_html_e( 'Absolute path of the directory used by fastcgi_cache_path or proxy_cache_path.', 'icc-gg-wordpress-super-cache' ); ?>
				</p>

				<?php if ( $nginx->is_overridden( 'path' ) ) : ?>
					<p class="description">
						<?php esc_html_e( 'Overridden by the ICC_GG_SUPER_CACHE_NGINX_PATH or NGINX_CACHE_PATH constant in wp-config.php.', 'icc-gg-wordpress-super-cache' ); ?>
					</p>
				<?php endif; ?>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Auto Purge', 'icc-gg-wordpress-super-cache' ); ?></th>
			<td>
				<input type="hidden" name="icc_gg_wordpress_super_cache_nginx[auto_purge]" value="0" />
				<input
					type="checkbox"
					id="icc-gg-wordpress-super-cache-nginx-auto-purge"
					name="icc_gg_wordpress_super_cache_nginx[auto_purge]"
					value="1"
					<?php checked( $nginx_auto_purge ); ?>
					<?php disabled( $nginx->is_overridden( 'auto_purge' ) ); ?>
				/>
				<label for="icc-gg-wordpress-super-cache-nginx-auto-purge">
					<?php esc_html_e( 'Purge the cache zone when posts, comments, menus or themes change', 'icc-gg-wordpress-super-cache' ); ?>
				</label>

				<?php if ( $nginx->is_overridden( 'auto_purge' ) ) : ?>
					<p class="description">
						<?php esc_html_e( 'Overridden by the ICC_GG_SUPER_CACHE_NGINX_AUTO_PURGE constant in wp-config.php.', 'icc-gg-wordpress-super-cache' ); ?>
					</p>
				<?php endif; ?>
			</td>
		</tr>

	</table>

	<p class="submit">
		<?php submit_button( __( 'Save nginx FastCGI Settings', 'icc-gg-wordpress-super-cache' ), 'primary', 'submit', false ); ?>

		<?php if ( $nginx_enabled && $nginx_path_usable ) : ?>
			<a href="<?php echo esc_attr( $roc->action_link( 'purge-nginx-cache' ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Purge nginx FastCGI Cache', 'icc-gg-wordpress-super-cache' ); ?>
			</a>
		<?php endif; ?>
	</p>

</form>

<h2 class="title">
	<?php esc_html_e( 'nginx FastCGI Configuration', 'icc-gg-wordpress-super-cache' ); ?>
</h2>

<p>
	<?php esc_html_e( 'The plugin only purges the cache zone; nginx itself has to be configured to store responses in it. A minimal FastCGI configuration looks like this:', 'icc-gg-wordpress-super-cache' ); ?>
</p>

<pre class="icc-gg-wordpress-super-cache-code"><?php echo esc_html( $nginx_example_conf ); ?></pre>

<p class="description">
	<?php esc_html_e( 'nginx answers before WordPress runs, so the URL exclusions on the Page Cache tab are not applied to a FastCGI cache. The example above repeats them with fastcgi_cache_bypass and fastcgi_no_cache; keep it in sync when you edit the exclusion list.', 'icc-gg-wordpress-super-cache' ); ?>
</p>

<p class="description">
	<?php esc_html_e( 'The plugin purges by deleting the files of the cache zone, so no extra PURGE endpoint or location block is required.', 'icc-gg-wordpress-super-cache' ); ?>
</p>

<p class="description">
	<?php esc_html_e( 'The same values can be defined in wp-config.php with the ICC_GG_SUPER_CACHE_NGINX_ENABLED, ICC_GG_SUPER_CACHE_NGINX_PATH and ICC_GG_SUPER_CACHE_NGINX_AUTO_PURGE constants.', 'icc-gg-wordpress-super-cache' ); ?>
</p>
