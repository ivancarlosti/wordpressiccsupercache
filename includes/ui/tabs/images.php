<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Image optimizer tab template.
 *
 * @package   ICC_GG_WordPress_Super_Cache
 * @category  General
 * @author    Ivan Carlos
 * @copyright 2007-2026 Ivan Carlos Consultoria
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0-or-later
 */

/** @var \ICC_GG_WordPress_Super_Cache_Plugin $roc */
$optimizer = ICC_GG_WordPress_Super_Cache_Image_Optimizer::instance();

$image_settings = $optimizer->get_settings();
$image_supported = $optimizer->supported_mimes();
$image_stats = $optimizer->stats();
$image_state = $optimizer->get_batch_state();
$image_pending = $optimizer->pending_count( $image_state['failed_ids'] );
?>

<?php if ( ! $image_supported ) : ?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'No image editor is available:', 'icc-gg-wordpress-super-cache' ); ?></strong>
			<?php esc_html_e( 'WordPress needs either the GD or the Imagick extension to optimize images.', 'icc-gg-wordpress-super-cache' ); ?>
		</p>
	</div>
<?php endif; ?>

<table class="form-table" style="margin-top: 20px;">

	<tr>
		<th><?php esc_html_e( 'Status:', 'icc-gg-wordpress-super-cache' ); ?></th>
		<td>
			<span class="<?php echo empty( $image_settings['enabled'] ) ? 'warning' : 'success'; ?>">
				<span class="dashicons <?php echo empty( $image_settings['enabled'] ) ? 'dashicons-warning' : 'dashicons-yes-alt'; ?>"></span>
				<?php echo esc_html( $optimizer->get_status() ); ?>
			</span>
		</td>
	</tr>

	<tr>
		<th><?php esc_html_e( 'Library:', 'icc-gg-wordpress-super-cache' ); ?></th>
		<td>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: Number of optimized images, 2: Total number of images. */
						__( '%1$s of %2$s images optimized', 'icc-gg-wordpress-super-cache' ),
						number_format_i18n( $image_stats['optimized'] ),
						number_format_i18n( $image_stats['total'] )
					)
				);
				?>
			</p>

			<?php if ( $image_stats['saved'] > 0 ) : ?>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s = Human readable file size. */
							__( 'About %s saved so far.', 'icc-gg-wordpress-super-cache' ),
							size_format( $image_stats['saved'], 2 )
						)
					);
					?>
				</p>
			<?php endif; ?>

			<p class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d = Number of images pending optimization. */
						_n( '%d image is still pending.', '%d images are still pending.', $image_pending, 'icc-gg-wordpress-super-cache' ),
						$image_pending
					)
				);
				?>
			</p>
		</td>
	</tr>

	<tr>
		<th><?php esc_html_e( 'Supported Formats:', 'icc-gg-wordpress-super-cache' ); ?></th>
		<td>
			<?php if ( $image_supported ) : ?>
				<?php foreach ( $image_supported as $image_mime => $image_label ) : ?>
					<code><?php echo esc_html( $image_label ); ?></code>
				<?php endforeach; ?>

				<p class="description">
					<?php esc_html_e( 'Detected from the image editor available on this server. Formats the editor cannot write are not offered.', 'icc-gg-wordpress-super-cache' ); ?>
				</p>
			<?php else : ?>
				<span class="warning"><?php esc_html_e( 'None', 'icc-gg-wordpress-super-cache' ); ?></span>
			<?php endif; ?>
		</td>
	</tr>

</table>

<h2 class="title"><?php esc_html_e( 'Optimization Settings', 'icc-gg-wordpress-super-cache' ); ?></h2>

<form method="post" action="">
	<?php wp_nonce_field( 'icc_gg_wordpress_super_cache_images' ); ?>

	<table class="form-table">

		<tr>
			<th scope="row"><?php esc_html_e( 'Formats', 'icc-gg-wordpress-super-cache' ); ?></th>
			<td>
				<?php foreach ( $image_supported as $image_mime => $image_label ) : ?>
					<?php $image_checked = empty( $image_settings['formats'] ) || in_array( $image_mime, (array) $image_settings['formats'], true ); ?>
					<input
						type="checkbox"
						id="icc-gg-wordpress-super-cache-images-format-<?php echo esc_attr( sanitize_title( $image_mime ) ); ?>"
						name="icc_gg_wordpress_super_cache_images[formats][]"
						value="<?php echo esc_attr( $image_mime ); ?>"
						<?php checked( $image_checked ); ?>
					/>
					<label for="icc-gg-wordpress-super-cache-images-format-<?php echo esc_attr( sanitize_title( $image_mime ) ); ?>">
						<?php echo esc_html( $image_label ); ?>
					</label>
					<br />
				<?php endforeach; ?>

				<p class="description">
					<?php esc_html_e( 'Leave every box checked to optimize all supported formats.', 'icc-gg-wordpress-super-cache' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="icc-gg-wordpress-super-cache-images-quality">
					<?php esc_html_e( 'Compression Quality', 'icc-gg-wordpress-super-cache' ); ?>
				</label>
			</th>
			<td>
				<input
					type="number"
					min="30"
					max="100"
					step="1"
					class="small-text"
					id="icc-gg-wordpress-super-cache-images-quality"
					name="icc_gg_wordpress_super_cache_images[quality]"
					value="<?php echo esc_attr( (int) $image_settings['quality'] ); ?>"
				/>

				<p class="description">
					<?php esc_html_e( 'Higher values keep more detail and produce larger files. 82 is a good balance for JPEG and WebP.', 'icc-gg-wordpress-super-cache' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Maximum Dimensions', 'icc-gg-wordpress-super-cache' ); ?></th>
			<td>
				<input
					type="number"
					min="0"
					max="10000"
					class="small-text"
					id="icc-gg-wordpress-super-cache-images-max-width"
					name="icc_gg_wordpress_super_cache_images[max_width]"
					value="<?php echo esc_attr( (int) $image_settings['max_width'] ); ?>"
					aria-label="<?php esc_attr_e( 'Maximum width in pixels', 'icc-gg-wordpress-super-cache' ); ?>"
				/>
				&times;
				<input
					type="number"
					min="0"
					max="10000"
					class="small-text"
					id="icc-gg-wordpress-super-cache-images-max-height"
					name="icc_gg_wordpress_super_cache_images[max_height]"
					value="<?php echo esc_attr( (int) $image_settings['max_height'] ); ?>"
					aria-label="<?php esc_attr_e( 'Maximum height in pixels', 'icc-gg-wordpress-super-cache' ); ?>"
				/>
				<?php esc_html_e( 'pixels', 'icc-gg-wordpress-super-cache' ); ?>

				<p class="description">
					<?php esc_html_e( 'Set to 0 to keep the original dimensions. Resizing regenerates the thumbnail sizes of each image.', 'icc-gg-wordpress-super-cache' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="icc-gg-wordpress-super-cache-images-max-size">
					<?php esc_html_e( 'Maximum File Size', 'icc-gg-wordpress-super-cache' ); ?>
				</label>
			</th>
			<td>
				<input
					type="number"
					min="0"
					max="100"
					class="small-text"
					id="icc-gg-wordpress-super-cache-images-max-size"
					name="icc_gg_wordpress_super_cache_images[max_size_mb]"
					value="<?php echo esc_attr( (int) $image_settings['max_size_mb'] ); ?>"
				/>
				<?php esc_html_e( 'MB', 'icc-gg-wordpress-super-cache' ); ?>

				<p class="description">
					<?php esc_html_e( 'Optional. Images heavier than this are re-compressed with a lower quality until they fit. Set to 0 to disable.', 'icc-gg-wordpress-super-cache' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Behaviour', 'icc-gg-wordpress-super-cache' ); ?></th>
			<td>
				<input type="hidden" name="icc_gg_wordpress_super_cache_images[enabled]" value="0" />
				<input
					type="checkbox"
					id="icc-gg-wordpress-super-cache-images-enabled"
					name="icc_gg_wordpress_super_cache_images[enabled]"
					value="1"
					<?php checked( ! empty( $image_settings['enabled'] ) ); ?>
				/>
				<label for="icc-gg-wordpress-super-cache-images-enabled">
					<?php esc_html_e( 'Optimize new uploads automatically', 'icc-gg-wordpress-super-cache' ); ?>
				</label>
				<br />

				<input type="hidden" name="icc_gg_wordpress_super_cache_images[backup]" value="0" />
				<input
					type="checkbox"
					id="icc-gg-wordpress-super-cache-images-backup"
					name="icc_gg_wordpress_super_cache_images[backup]"
					value="1"
					<?php checked( ! empty( $image_settings['backup'] ) ); ?>
				/>
				<label for="icc-gg-wordpress-super-cache-images-backup">
					<?php esc_html_e( 'Keep a backup of the original images', 'icc-gg-wordpress-super-cache' ); ?>
				</label>
				<br />

				<?php if ( isset( $image_supported['image/webp'] ) ) : ?>
					<input type="hidden" name="icc_gg_wordpress_super_cache_images[convert_to_webp]" value="0" />
					<input
						type="checkbox"
						id="icc-gg-wordpress-super-cache-images-webp"
						name="icc_gg_wordpress_super_cache_images[convert_to_webp]"
						value="1"
						<?php checked( ! empty( $image_settings['convert_to_webp'] ) ); ?>
					/>
					<label for="icc-gg-wordpress-super-cache-images-webp">
						<?php esc_html_e( 'Store new JPEG and PNG uploads as WebP', 'icc-gg-wordpress-super-cache' ); ?>
					</label>
					<br />
				<?php endif; ?>

				<input
					type="number"
					min="1"
					max="20"
					class="small-text"
					id="icc-gg-wordpress-super-cache-images-batch"
					name="icc_gg_wordpress_super_cache_images[batch]"
					value="<?php echo esc_attr( (int) $image_settings['batch'] ); ?>"
					aria-label="<?php esc_attr_e( 'Images per batch', 'icc-gg-wordpress-super-cache' ); ?>"
				/>
				<label for="icc-gg-wordpress-super-cache-images-batch">
					<?php esc_html_e( 'images per batch', 'icc-gg-wordpress-super-cache' ); ?>
				</label>

				<p class="description">
					<?php esc_html_e( 'Backups are stored in wp-content/icc-gg-image-backups so an image can be restored later.', 'icc-gg-wordpress-super-cache' ); ?>
				</p>
			</td>
		</tr>

	</table>

	<p class="submit">
		<?php submit_button( __( 'Save Image Settings', 'icc-gg-wordpress-super-cache' ), 'primary', 'submit', false ); ?>
	</p>

</form>

<h2 class="title"><?php esc_html_e( 'Optimize the Library', 'icc-gg-wordpress-super-cache' ); ?></h2>

<p>
	<?php esc_html_e( 'Runs through the media library in small batches, so you can close the tab at any time and continue later.', 'icc-gg-wordpress-super-cache' ); ?>
</p>

<div
	class="icc-gg-wordpress-super-cache-optimizer"
	data-batch="<?php echo esc_attr( (int) $image_settings['batch'] ); ?>"
	data-enabled="<?php echo $image_supported ? '1' : '0'; ?>"
>
	<div class="icc-gg-wordpress-super-cache-optimizer-track">
		<span class="icc-gg-wordpress-super-cache-optimizer-bar" style="width: <?php echo esc_attr( $image_stats['total'] > 0 ? min( 100, (int) round( ( $image_stats['optimized'] / $image_stats['total'] ) * 100 ) ) : 0 ); ?>%"></span>
	</div>

	<p class="icc-gg-wordpress-super-cache-optimizer-status">
		<?php
		echo esc_html(
			sprintf(
				/* translators: 1: Number of optimized images, 2: Total number of images. */
				__( '%1$s of %2$s images optimized', 'icc-gg-wordpress-super-cache' ),
				number_format_i18n( $image_stats['optimized'] ),
				number_format_i18n( $image_stats['total'] )
			)
		);
		?>
	</p>

	<p>
		<button type="button" class="button button-primary" data-optimizer="start" <?php disabled( ! $image_supported ); ?>>
			<?php esc_html_e( 'Optimize Images', 'icc-gg-wordpress-super-cache' ); ?>
		</button>

		<button type="button" class="button" data-optimizer="pause" disabled>
			<?php esc_html_e( 'Pause', 'icc-gg-wordpress-super-cache' ); ?>
		</button>

		<a class="button" href="<?php echo esc_attr( $roc->action_link( 'reset-image-optimizer' ) ); ?>">
			<?php esc_html_e( 'Reset Progress', 'icc-gg-wordpress-super-cache' ); ?>
		</a>
	</p>
</div>

