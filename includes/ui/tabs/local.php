<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Local page cache tab template.
 *
 * @package   ICC_GG_WordPress_Super_Cache
 * @category  General
 * @author    Ivan Carlos
 * @copyright 2007-2026 Ivan Carlos Consultoria
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0-or-later
 */

/** @var \ICC_GG_WordPress_Super_Cache_Plugin $roc */
$local = ICC_GG_WordPress_Super_Cache_Local_Cache::instance();
$registry = ICC_GG_WordPress_Super_Cache_Page_Cache::instance();

$local_settings = $local->get_settings();
$local_enabled = $local->is_enabled();
$local_mode = $local->bootstrap_mode();
$local_stats = $local->cache_stats();
$local_queue = $local->get_preload_queue();
$local_has_backup = file_exists( $local->wp_config_backup_path() );
$local_root = $local->cache_root();

$local_timeouts = [
	0 => __( 'Never expire', 'icc-gg-wordpress-super-cache' ),
	HOUR_IN_SECONDS => __( '1 hour', 'icc-gg-wordpress-super-cache' ),
	6 * HOUR_IN_SECONDS => __( '6 hours', 'icc-gg-wordpress-super-cache' ),
	12 * HOUR_IN_SECONDS => __( '12 hours', 'icc-gg-wordpress-super-cache' ),
	DAY_IN_SECONDS => __( '24 hours', 'icc-gg-wordpress-super-cache' ),
	WEEK_IN_SECONDS => __( '1 week', 'icc-gg-wordpress-super-cache' ),
];

$local_mode_labels = [
	'advanced-cache' => __( 'advanced-cache.php drop-in (WP_CACHE)', 'icc-gg-wordpress-super-cache' ),
	'mu-plugin' => __( 'Must-use plugin loader', 'icc-gg-wordpress-super-cache' ),
	'none' => __( 'No loader installed', 'icc-gg-wordpress-super-cache' ),
];

$local_loader_state = $local->loader_state();
$local_dropin_state = $local_loader_state['dropin'];
$local_mu_state = $local_loader_state['mu_plugin'];

$local_loader_state_labels = [
	'missing' => __( 'Not installed', 'icc-gg-wordpress-super-cache' ),
	'foreign' => __( 'Installed by another plugin', 'icc-gg-wordpress-super-cache' ),
	'outdated' => __( 'Outdated, needs rewriting', 'icc-gg-wordpress-super-cache' ),
	'invalid' => __( 'Damaged or unreadable', 'icc-gg-wordpress-super-cache' ),
	'valid' => __( 'Up to date', 'icc-gg-wordpress-super-cache' ),
];

$local_dropin_backup = $local->dropin_backup_path();
$local_has_dropin_backup = file_exists( $local_dropin_backup );
$local_dropin_broken = in_array( $local_dropin_state, [ 'foreign', 'outdated', 'invalid' ], true );
?>

<?php if ( $local_enabled && 'none' === $local_mode ) : ?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'The page cache is enabled but no loader is installed:', 'icc-gg-wordpress-super-cache' ); ?></strong>
			<?php if ( 'foreign' === $local_dropin_state ) : ?>
				<?php esc_html_e( 'wp-content/advanced-cache.php was installed by another plugin. Use "Write / Rewrite advanced-cache.php" in Maintenance to replace it; the current file is copied to a backup first.', 'icc-gg-wordpress-super-cache' ); ?>
			<?php elseif ( $local_dropin_broken ) : ?>
				<?php esc_html_e( 'wp-content/advanced-cache.php does not match this plugin version. Use "Write / Rewrite advanced-cache.php" in Maintenance to restore it; the current file is copied to a backup first.', 'icc-gg-wordpress-super-cache' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Save the settings or use "Write / Rewrite advanced-cache.php" in Maintenance so pages start being cached.', 'icc-gg-wordpress-super-cache' ); ?>
			<?php endif; ?>
		</p>
	</div>
<?php elseif ( $local_enabled && $local_dropin_broken ) : ?>
	<div class="notice notice-warning">
		<p>
			<strong><?php esc_html_e( 'The advanced-cache.php drop-in is not the one this plugin expects:', 'icc-gg-wordpress-super-cache' ); ?></strong>
			<?php echo esc_html( isset( $local_loader_state_labels[ $local_dropin_state ] ) ? $local_loader_state_labels[ $local_dropin_state ] : $local_dropin_state ); ?>.
			<?php esc_html_e( 'WordPress loads that file on every request, so rewrite it from Maintenance; the current file is copied to a backup first.', 'icc-gg-wordpress-super-cache' ); ?>
		</p>
	</div>
<?php endif; ?>

<table class="form-table" style="margin-top: 20px;">

	<tr>
		<th><?php esc_html_e( 'Status:', 'icc-gg-wordpress-super-cache' ); ?></th>
		<td>
			<span class="<?php echo $local_enabled && 'none' !== $local_mode ? 'success' : 'warning'; ?>">
				<span class="dashicons <?php echo $local_enabled && 'none' !== $local_mode ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
				<?php echo esc_html( $local->get_status() ); ?>
			</span>

			<p class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s = Active page cache backend label. */
						__( 'Active page cache backend: %s', 'icc-gg-wordpress-super-cache' ),
						$registry->label( $registry->active() )
					)
				);
				?>
			</p>
		</td>
	</tr>

	<tr>
		<th><?php esc_html_e( 'Loader:', 'icc-gg-wordpress-super-cache' ); ?></th>
		<td>
			<code><?php echo esc_html( isset( $local_mode_labels[ $local_mode ] ) ? $local_mode_labels[ $local_mode ] : $local_mode ); ?></code>

			<p class="description">
				<?php if ( $local_enabled && 'mu-plugin' === $local_mode ) : ?>
					<?php esc_html_e( 'WP_CACHE could not be added to wp-config.php, so the cache is loaded from wp-content/mu-plugins/icc-gg-page-cache.php.', 'icc-gg-wordpress-super-cache' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Define WP_CACHE in wp-config.php to load the cache through advanced-cache.php, or let the plugin install the must-use plugin loader instead.', 'icc-gg-wordpress-super-cache' ); ?>
				<?php endif; ?>
			</p>

			<p class="description">
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s = Path of the generated engine directory. */
						__( 'Engine and configuration: <code>%1$s</code>', 'icc-gg-wordpress-super-cache' ),
						esc_html( $local->runtime_dir() )
					),
					[ 'code' => [] ]
				);
				?>
			</p>
		</td>
	</tr>

	<tr>
		<th><?php esc_html_e( 'advanced-cache.php:', 'icc-gg-wordpress-super-cache' ); ?></th>
		<td>
			<span class="<?php echo 'valid' === $local_dropin_state || 'missing' === $local_dropin_state ? 'success' : 'warning'; ?>">
				<?php echo esc_html( isset( $local_loader_state_labels[ $local_dropin_state ] ) ? $local_loader_state_labels[ $local_dropin_state ] : $local_dropin_state ); ?>
			</span>

			<p class="description">
				<code><?php echo esc_html( $local->dropin_path() ); ?></code>
			</p>

			<?php if ( 'foreign' === $local_dropin_state ) : ?>
				<p class="description">
					<?php esc_html_e( 'Another plugin owns this file. Rewriting it replaces that plugin loader, so keep the backup until the site is confirmed to work.', 'icc-gg-wordpress-super-cache' ); ?>
				</p>
			<?php endif; ?>

			<?php if ( $local_has_dropin_backup ) : ?>
				<p class="description">
					<?php
					echo wp_kses(
						sprintf(
							/* translators: %s = Path of the advanced-cache.php backup. */
							__( 'Backup of the replaced file: <code>%s</code>', 'icc-gg-wordpress-super-cache' ),
							esc_html( $local_dropin_backup )
						),
						[ 'code' => [] ]
					);
					?>
				</p>
			<?php endif; ?>

			<?php if ( 'missing' !== $local_mu_state && 'foreign' !== $local_mu_state ) : ?>
				<p class="description">
					<?php
					echo wp_kses(
						sprintf(
							/* translators: 1: State of the must-use plugin loader, 2: Path of the must-use plugin loader. */
							__( 'Must-use plugin loader: %1$s (<code>%2$s</code>)', 'icc-gg-wordpress-super-cache' ),
							esc_html( isset( $local_loader_state_labels[ $local_mu_state ] ) ? $local_loader_state_labels[ $local_mu_state ] : $local_mu_state ),
							esc_html( $local->mu_plugin_path() )
						),
						[ 'code' => [] ]
					);
					?>
				</p>
			<?php endif; ?>
		</td>
	</tr>

	<tr>
		<th><?php esc_html_e( 'WP_CACHE:', 'icc-gg-wordpress-super-cache' ); ?></th>
		<td>
			<?php if ( $local->has_wp_cache_constant() ) : ?>
				<code>define( 'WP_CACHE', true );</code>
			<?php else : ?>
				<span class="warning"><?php esc_html_e( 'Not defined', 'icc-gg-wordpress-super-cache' ); ?></span>
			<?php endif; ?>

			<?php if ( $local_has_backup ) : ?>
				<p class="description">
					<?php
					echo wp_kses(
						sprintf(
							/* translators: %s = Path of the wp-config.php backup. */
							__( 'A backup of the original wp-config.php is stored at <code>%s</code>.', 'icc-gg-wordpress-super-cache' ),
							esc_html( $local->wp_config_backup_path() )
						),
						[ 'code' => [] ]
					);
					?>
				</p>
			<?php endif; ?>
		</td>
	</tr>

	<tr>
		<th><?php esc_html_e( 'Cached Pages:', 'icc-gg-wordpress-super-cache' ); ?></th>
		<td>
			<code><?php echo esc_html( $local_root ); ?></code>

			<?php if ( $local_stats['pages'] > 0 ) : ?>
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: Number of cached pages, 2: Cache size. */
							_n( '%1$s page &mdash; %2$s', '%1$s pages &mdash; %2$s', $local_stats['pages'], 'icc-gg-wordpress-super-cache' ),
							number_format_i18n( $local_stats['pages'] ),
							size_format( $local_stats['size'], 2 )
						)
					);
					?>
				</p>

				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: Human readable time difference, 2: Human readable time difference. */
							__( 'Newest entry %1$s ago, oldest entry %2$s ago.', 'icc-gg-wordpress-super-cache' ),
							human_time_diff( $local_stats['newest'], time() ),
							human_time_diff( $local_stats['oldest'], time() )
						)
					);
					?>
				</p>
			<?php else : ?>
				<p class="warning"><?php esc_html_e( 'No cached pages yet.', 'icc-gg-wordpress-super-cache' ); ?></p>
			<?php endif; ?>

			<?php if ( $local_enabled && ! is_writable( $local_root ) && ! is_writable( dirname( $local_root ) ) ) : ?>
				<p class="description is-notice">
					<?php esc_html_e( 'The cache directory is not writable, so pages cannot be cached.', 'icc-gg-wordpress-super-cache' ); ?>
				</p>
			<?php endif; ?>

			<?php if ( $local_queue ) : ?>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d = Number of URLs waiting to be preloaded. */
							_n( '%d URL is waiting to be preloaded.', '%d URLs are waiting to be preloaded.', count( $local_queue ), 'icc-gg-wordpress-super-cache' ),
							count( $local_queue )
						)
					);
					?>
				</p>
			<?php endif; ?>
		</td>
	</tr>

</table>

<?php
$local_checkboxes = [
	'auto_purge' => __( 'Clear the cache when posts, comments, menus or themes change', 'icc-gg-wordpress-super-cache' ),
	'gzip' => __( 'Store a gzipped copy of each page', 'icc-gg-wordpress-super-cache' ),
	'query_strings' => __( 'Also cache URLs with query strings', 'icc-gg-wordpress-super-cache' ),
	'mobile' => __( 'Keep separate copies for mobile devices', 'icc-gg-wordpress-super-cache' ),
	'browser_cache' => __( 'Send browser caching headers with cached pages', 'icc-gg-wordpress-super-cache' ),
	'debug_header' => __( 'Add an X-ICC-Page-Cache header to cached responses', 'icc-gg-wordpress-super-cache' ),
	'preload' => __( 'Preload the cache automatically after it is cleared', 'icc-gg-wordpress-super-cache' ),
];
?>

<h2 class="title"><?php esc_html_e( 'Page Cache Settings', 'icc-gg-wordpress-super-cache' ); ?></h2>

<form method="post" action="">
	<?php wp_nonce_field( 'icc_gg_wordpress_super_cache_local' ); ?>

	<table class="form-table">

		<tr>
			<th scope="row"><?php esc_html_e( 'Page Cache', 'icc-gg-wordpress-super-cache' ); ?></th>
			<td>
				<input type="hidden" name="icc_gg_wordpress_super_cache_local[enabled]" value="0" />
				<input
					type="checkbox"
					id="icc-gg-wordpress-super-cache-local-enabled"
					name="icc_gg_wordpress_super_cache_local[enabled]"
					value="1"
					<?php checked( $local_enabled ); ?>
					<?php disabled( $local->is_overridden( 'enabled' ) ); ?>
				/>
				<label for="icc-gg-wordpress-super-cache-local-enabled">
					<?php esc_html_e( 'Cache pages with the local PHP page cache', 'icc-gg-wordpress-super-cache' ); ?>
				</label>

				<p class="description">
					<?php esc_html_e( 'Only one page cache backend can be enabled at a time: enabling this one requires disabling the Varnish Cache and nginx FastCGI page caches first.', 'icc-gg-wordpress-super-cache' ); ?>
				</p>

				<?php if ( $local->is_overridden( 'enabled' ) ) : ?>
					<p class="description">
						<?php esc_html_e( 'Overridden by the ICC_GG_SUPER_CACHE_LOCAL_ENABLED constant in wp-config.php.', 'icc-gg-wordpress-super-cache' ); ?>
					</p>
				<?php endif; ?>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="icc-gg-wordpress-super-cache-local-timeout">
					<?php esc_html_e( 'Cache Expiry', 'icc-gg-wordpress-super-cache' ); ?>
				</label>
			</th>
			<td>
				<select
					id="icc-gg-wordpress-super-cache-local-timeout"
					name="icc_gg_wordpress_super_cache_local[timeout]"
				>
					<?php foreach ( $local_timeouts as $local_timeout => $local_timeout_label ) : ?>
						<option value="<?php echo esc_attr( $local_timeout ); ?>" <?php selected( (int) $local_settings['timeout'], (int) $local_timeout ); ?>>
							<?php echo esc_html( $local_timeout_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<p class="description">
					<?php esc_html_e( 'Cached pages older than this are regenerated on the next visit. Content changes always clear the cache immediately.', 'icc-gg-wordpress-super-cache' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Behaviour', 'icc-gg-wordpress-super-cache' ); ?></th>
			<td>
				<?php foreach ( $local_checkboxes as $local_key => $local_label ) : ?>
					<input type="hidden" name="icc_gg_wordpress_super_cache_local[<?php echo esc_attr( $local_key ); ?>]" value="0" />
					<input
						type="checkbox"
						id="icc-gg-wordpress-super-cache-local-<?php echo esc_attr( str_replace( '_', '-', $local_key ) ); ?>"
						name="icc_gg_wordpress_super_cache_local[<?php echo esc_attr( $local_key ); ?>]"
						value="1"
						<?php checked( ! empty( $local_settings[ $local_key ] ) ); ?>
						<?php disabled( $local->is_overridden( $local_key ) ); ?>
					/>
					<label for="icc-gg-wordpress-super-cache-local-<?php echo esc_attr( str_replace( '_', '-', $local_key ) ); ?>">
						<?php echo esc_html( $local_label ); ?>
					</label>

					<?php if ( 'gzip' === $local_key && ! function_exists( 'gzencode' ) ) : ?>
						<span class="warning"><?php esc_html_e( '(zlib is not available on this server)', 'icc-gg-wordpress-super-cache' ); ?></span>
					<?php endif; ?>

					<br />
				<?php endforeach; ?>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="icc-gg-wordpress-super-cache-local-preload-batch">
					<?php esc_html_e( 'Preload Batch Size', 'icc-gg-wordpress-super-cache' ); ?>
				</label>
			</th>
			<td>
				<input
					type="number"
					min="1"
					max="50"
					class="small-text"
					id="icc-gg-wordpress-super-cache-local-preload-batch"
					name="icc_gg_wordpress_super_cache_local[preload_batch]"
					value="<?php echo esc_attr( (int) $local_settings['preload_batch'] ); ?>"
				/>

				<p class="description">
					<?php esc_html_e( 'Number of URLs requested per preload batch. Lower values are gentler on small servers.', 'icc-gg-wordpress-super-cache' ); ?>
				</p>
			</td>
		</tr>

	</table>

	<h2 class="title"><?php esc_html_e( 'Exclusions', 'icc-gg-wordpress-super-cache' ); ?></h2>

	<table class="form-table">

		<tr>
			<th scope="row">
				<label for="icc-gg-wordpress-super-cache-local-exclude-urls">
					<?php esc_html_e( 'URLs', 'icc-gg-wordpress-super-cache' ); ?>
				</label>
			</th>
			<td>
				<textarea
					rows="4"
					class="large-text code"
					id="icc-gg-wordpress-super-cache-local-exclude-urls"
					name="icc_gg_wordpress_super_cache_local[exclude_urls]"
				><?php echo esc_textarea( (string) $local_settings['exclude_urls'] ); ?></textarea>

				<p class="description">
					<?php esc_html_e( 'One path or URL per line, for example /cart/ or /my-account/*. Wildcards are supported, and a line can start with regex: to use a regular expression.', 'icc-gg-wordpress-super-cache' ); ?>
				</p>

				<p class="description">
					<?php esc_html_e( 'The plugin already fills in the WordPress paths that should never be cached. The engine also skips /wp-admin, wp-login.php, wp-json, wp-cron.php, xmlrpc.php, feeds and trackbacks even when they are missing from this list.', 'icc-gg-wordpress-super-cache' ); ?>
				</p>

				<p class="description">
					<?php esc_html_e( 'These exclusions only apply to this local page cache. Varnish Cache and nginx FastCGI answer before WordPress runs, so they need the same paths in their own configuration; the snippets on their tabs are generated from this list.', 'icc-gg-wordpress-super-cache' ); ?>
				</p>

				<p>
					<a class="button button-secondary" href="<?php echo esc_attr( $roc->action_link( 'reset-local-exclusions' ) ); ?>">
						<?php esc_html_e( 'Restore default exclusions', 'icc-gg-wordpress-super-cache' ); ?>
					</a>
				</p>

				<p class="description">
					<?php esc_html_e( 'Restoring the defaults replaces the current list, including your own entries. Save the form first if you want to keep a copy.', 'icc-gg-wordpress-super-cache' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="icc-gg-wordpress-super-cache-local-exclude-agents">
					<?php esc_html_e( 'User Agents', 'icc-gg-wordpress-super-cache' ); ?>
				</label>
			</th>
			<td>
				<textarea
					rows="3"
					class="large-text code"
					id="icc-gg-wordpress-super-cache-local-exclude-agents"
					name="icc_gg_wordpress_super_cache_local[exclude_agents]"
				><?php echo esc_textarea( (string) $local_settings['exclude_agents'] ); ?></textarea>

				<p class="description">
					<?php esc_html_e( 'One user agent fragment per line, for example Googlebot or bot. Matching requests are never cached.', 'icc-gg-wordpress-super-cache' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="icc-gg-wordpress-super-cache-local-exclude-cookies">
					<?php esc_html_e( 'Cookies', 'icc-gg-wordpress-super-cache' ); ?>
				</label>
			</th>
			<td>
				<textarea
					rows="3"
					class="large-text code"
					id="icc-gg-wordpress-super-cache-local-exclude-cookies"
					name="icc_gg_wordpress_super_cache_local[exclude_cookies]"
				><?php echo esc_textarea( (string) $local_settings['exclude_cookies'] ); ?></textarea>

				<p class="description">
					<?php esc_html_e( 'One cookie name per line. Requests carrying these cookies are never cached. Logged-in users, commenters and password protected posts are always excluded.', 'icc-gg-wordpress-super-cache' ); ?>
				</p>
			</td>
		</tr>

	</table>

	<p class="submit">
		<?php submit_button( __( 'Save Page Cache Settings', 'icc-gg-wordpress-super-cache' ), 'primary', 'submit', false ); ?>

		<?php if ( $local_enabled ) : ?>
			<a class="button button-secondary" href="<?php echo esc_attr( $roc->action_link( 'purge-local-cache' ) ); ?>">
				<?php esc_html_e( 'Clear Page Cache', 'icc-gg-wordpress-super-cache' ); ?>
			</a>

			<a class="button button-secondary" href="<?php echo esc_attr( $roc->action_link( 'preload-local-cache' ) ); ?>">
				<?php esc_html_e( 'Clear & Preload', 'icc-gg-wordpress-super-cache' ); ?>
			</a>
		<?php endif; ?>
	</p>

</form>

<h2 class="title"><?php esc_html_e( 'Clear Cache', 'icc-gg-wordpress-super-cache' ); ?></h2>

<p>
	<?php esc_html_e( 'Clear everything at once, or purge only the pages that changed. Purging a URL also removes its gzipped copy and any empty directory left behind.', 'icc-gg-wordpress-super-cache' ); ?>
</p>

<form method="post" action="">
	<?php wp_nonce_field( 'icc_gg_wordpress_super_cache_local_urls' ); ?>

	<table class="form-table">

		<tr>
			<th scope="row">
				<label for="icc-gg-wordpress-super-cache-local-purge-urls">
					<?php esc_html_e( 'Purge URLs', 'icc-gg-wordpress-super-cache' ); ?>
				</label>
			</th>
			<td>
				<textarea
					rows="3"
					class="large-text code"
					id="icc-gg-wordpress-super-cache-local-purge-urls"
					name="icc_gg_wordpress_super_cache_local_urls[urls]"
					placeholder="/about/&#10;/blog/*&#10;https://example.com/contact/"
				></textarea>

				<p class="description">
					<?php esc_html_e( 'One URL or path per line. Use a trailing * to purge everything below a path.', 'icc-gg-wordpress-super-cache' ); ?>
				</p>
			</td>
		</tr>

	</table>

	<p class="submit">
		<?php submit_button( __( 'Purge URLs', 'icc-gg-wordpress-super-cache' ), 'secondary', 'submit', false ); ?>
	</p>

</form>

<h2 class="title"><?php esc_html_e( 'Maintenance', 'icc-gg-wordpress-super-cache' ); ?></h2>

<p>
	<a class="button" href="<?php echo esc_attr( $roc->action_link( 'replace-advanced-cache' ) ); ?>">
		<?php esc_html_e( 'Regenerate Page Cache Loader', 'icc-gg-wordpress-super-cache' ); ?>
	</a>

	<a class="button" href="<?php echo esc_attr( $roc->action_link( 'write-page-cache-loader' ) ); ?>">
		<?php esc_html_e( 'Write / Rewrite advanced-cache.php', 'icc-gg-wordpress-super-cache' ); ?>
	</a>

	<?php if ( $local_has_backup ) : ?>
		<a class="button" href="<?php echo esc_attr( $roc->action_link( 'revert-wp-config' ) ); ?>">
			<?php esc_html_e( 'Revert wp-config.php Backup', 'icc-gg-wordpress-super-cache' ); ?>
		</a>
	<?php endif; ?>
</p>

<p class="description">
	<?php esc_html_e( '"Regenerate Page Cache Loader" refreshes the engine files and rewrites the loader only when it already belongs to this plugin.', 'icc-gg-wordpress-super-cache' ); ?>
</p>

<p class="description">
	<?php esc_html_e( '"Write / Rewrite advanced-cache.php" writes the loader even when the file is missing, damaged, outdated or owned by another plugin. The current file is copied to advanced-cache.icc-gg-backup.php before it is replaced.', 'icc-gg-wordpress-super-cache' ); ?>
</p>

<p class="description">
	<?php esc_html_e( 'When WP_CACHE cannot be added to wp-config.php, both buttons write the must-use plugin loader (wp-content/mu-plugins/icc-gg-page-cache.php) instead.', 'icc-gg-wordpress-super-cache' ); ?>
</p>

<p class="description">
	<?php esc_html_e( 'The same values can be defined in wp-config.php with the ICC_GG_SUPER_CACHE_LOCAL_ENABLED, ICC_GG_SUPER_CACHE_LOCAL_AUTO_PURGE and ICC_GG_SUPER_CACHE_LOCAL_PATH constants.', 'icc-gg-wordpress-super-cache' ); ?>
</p>
