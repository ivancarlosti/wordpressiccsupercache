<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Image optimizer controller.
 *
 * Compresses the images already in the media library and caps their dimensions,
 * either in batches from the admin area or automatically on upload. Formats are
 * discovered at runtime, so a server without WebP or AVIF support simply does
 * not offer them.
 *
 * @package   ICC_GG_WordPress_Super_Cache
 * @category  General
 * @author    Ivan Carlos
 * @copyright 2007-2026 Ivan Carlos Consultoria
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0-or-later
 */

/**
 * Image optimizer class definition
 */
class ICC_GG_WordPress_Super_Cache_Image_Optimizer
{
	/**
	 * Option name holding the optimizer settings.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'icc_gg_wordpress_super_cache_image_optimizer';

	/**
	 * Option name holding the batch progress.
	 *
	 * @var string
	 */
	const STATE_OPTION = 'icc_gg_wordpress_super_cache_image_optimizer_state';

	/**
	 * Post meta key holding the optimization state of an attachment.
	 *
	 * @var string
	 */
	const META_KEY = '_icc_gg_image_optimizer';

	/**
	 * Singleton instance of self.
	 *
	 * @var ICC_GG_WordPress_Super_Cache_Image_Optimizer|null
	 */
	private static $instance;

	/**
	 * Resolved settings, or null when they still need to be read.
	 *
	 * @var array|null
	 */
	private $settings = null;

	/**
	 * Create (if needed) and return a singleton of self.
	 *
	 * @return ICC_GG_WordPress_Super_Cache_Image_Optimizer
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
		add_filter( 'wp_generate_attachment_metadata', [ $this, 'maybe_optimize_on_upload' ], 20, 2 );
		add_filter( 'image_editor_output_format', [ $this, 'filter_output_format' ] );
	}

	/**
	 * Returns the default settings.
	 *
	 * @return array
	 */
	public function defaults()
	{
		return [
			'enabled' => false,
			'max_width' => 0,
			'max_height' => 0,
			'max_size_mb' => 0,
			'quality' => 82,
			'convert_to_webp' => false,
			'backup' => true,
			'batch' => 3,
			'formats' => [],
		];
	}

	/**
	 * Returns the stored settings.
	 *
	 * @return array
	 */
	public function get_settings()
	{
		if ( ! isset( $this->settings ) ) {
			$stored = get_option( self::OPTION_KEY, [] );
			$stored = is_array( $stored ) ? $stored : [];

			$this->settings = wp_parse_args( $stored, $this->defaults() );
		}

		return $this->settings;
	}

	/**
	 * Reads a single setting.
	 *
	 * @param string $key Setting name.
	 * @return mixed
	 */
	public function get_setting( $key )
	{
		$settings = $this->get_settings();

		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	/**
	 * Forgets the cached settings, e.g. after they were updated.
	 *
	 * @return void
	 */
	public function flush_settings_cache()
	{
		$this->settings = null;
	}

	/**
	 * Returns the image formats that can be optimized on this server.
	 *
	 * @return array<string, string> Labels keyed by mime type.
	 */
	public function supported_mimes()
	{
		static $mimes = null;

		if ( isset( $mimes ) ) {
			return $mimes;
		}

		$candidates = [
			'image/jpeg' => __( 'JPEG', 'icc-gg-wordpress-super-cache' ),
			'image/png' => __( 'PNG', 'icc-gg-wordpress-super-cache' ),
			'image/webp' => __( 'WebP', 'icc-gg-wordpress-super-cache' ),
			'image/avif' => __( 'AVIF', 'icc-gg-wordpress-super-cache' ),
		];

		$known = array_values( wp_get_mime_types() );

		$mimes = [];

		foreach ( $candidates as $mime => $label ) {
			if ( ! in_array( $mime, $known, true ) ) {
				continue;
			}

			if ( ! wp_image_editor_supports( [ 'mime_type' => $mime ] ) ) {
				continue;
			}

			$mimes[ $mime ] = $label;
		}

		/**
		 * Filters the image formats offered by the optimizer.
		 *
		 * @param array<string, string> $mimes Labels keyed by mime type.
		 */
		return (array) apply_filters( 'icc_gg_wordpress_super_cache_image_optimizer_mimes', $mimes );
	}

	/**
	 * Returns the mime types the optimizer is allowed to write.
	 *
	 * These are the output formats selected in the settings, used when new
	 * uploads are converted (WebP/AVIF). They are unrelated to the formats the
	 * library scan looks for, see {@see source_mimes()}.
	 *
	 * @return string[]
	 */
	public function output_mimes()
	{
		$supported = $this->supported_mimes();
		$selected = $this->get_setting( 'formats' );

		$selected = is_array( $selected )
			? array_values( array_intersect( $selected, array_keys( $supported ) ) )
			: [];

		if ( ! $selected ) {
			return array_keys( $supported );
		}

		return $selected;
	}

	/**
	 * Returns the mime types the optimizer can read from the media library.
	 *
	 * Every format the site can open is a valid source, so this is not
	 * affected by the selected output formats.
	 *
	 * @return string[]
	 */
	public function source_mimes()
	{
		return array_keys( $this->supported_mimes() );
	}

	/**
	 * Sanitizes the submitted settings.
	 *
	 * @param array $input Raw, unslashed settings.
	 * @return array
	 */
	public function sanitize_settings( $input )
	{
		$input = is_array( $input ) ? $input : [];

		$supported = $this->supported_mimes();
		$formats = isset( $input['formats'] ) && is_array( $input['formats'] ) ? $input['formats'] : [];
		$formats = array_values( array_intersect( array_map( 'sanitize_text_field', $formats ), array_keys( $supported ) ) );

		return [
			'enabled' => ! empty( $input['enabled'] ),
			'max_width' => min( 10000, max( 0, isset( $input['max_width'] ) ? (int) $input['max_width'] : 0 ) ),
			'max_height' => min( 10000, max( 0, isset( $input['max_height'] ) ? (int) $input['max_height'] : 0 ) ),
			'max_size_mb' => min( 100, max( 0, isset( $input['max_size_mb'] ) ? (int) $input['max_size_mb'] : 0 ) ),
			'quality' => min( 100, max( 30, isset( $input['quality'] ) ? (int) $input['quality'] : 82 ) ),
			'convert_to_webp' => ! empty( $input['convert_to_webp'] ),
			'backup' => ! empty( $input['backup'] ),
			'batch' => min( 20, max( 1, isset( $input['batch'] ) ? (int) $input['batch'] : 3 ) ),
			'formats' => $formats,
		];
	}

	/**
	 * Stores JPEG and PNG uploads as WebP when enabled.
	 *
	 * @param array $formats Output formats keyed by mime type.
	 * @return array
	 */
	public function filter_output_format( $formats )
	{
		if ( empty( $this->get_setting( 'convert_to_webp' ) ) || ! is_array( $formats ) ) {
			return $formats;
		}

		$supported = $this->supported_mimes();

		if ( ! isset( $supported['image/webp'] ) ) {
			return $formats;
		}

		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png'] = 'image/webp';

		return $formats;
	}

	/**
	 * Returns the human readable status of the optimizer.
	 *
	 * @return string
	 */
	public function get_status()
	{
		if ( empty( $this->get_setting( 'enabled' ) ) ) {
			return __( 'Manual optimization', 'icc-gg-wordpress-super-cache' );
		}

		return __( 'Optimizing new uploads', 'icc-gg-wordpress-super-cache' );
	}

	/**
	 * Returns the number of attachments in the media library.
	 *
	 * @return int
	 */
	public function total_images()
	{
		$counts = wp_count_posts( 'attachment' );

		return isset( $counts->inherit ) ? (int) $counts->inherit : 0;
	}

	/**
	 * Returns the number of optimized attachments.
	 *
	 * @return int
	 */
	public function optimized_count()
	{
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT( post_id ) FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::META_KEY
			)
		);
	}

	/**
	 * Returns the aggregated optimization statistics.
	 *
	 * @return array{total:int,optimized:int,original:int,optimized_bytes:int,saved:int}
	 */
	public function stats()
	{
		global $wpdb;

		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 5000",
				self::META_KEY
			)
		);

		$original = 0;
		$optimized = 0;

		foreach ( (array) $rows as $row ) {
			$state = maybe_unserialize( $row );

			if ( ! is_array( $state ) ) {
				continue;
			}

			$original += isset( $state['original'] ) ? (int) $state['original'] : 0;
			$optimized += isset( $state['optimized'] ) ? (int) $state['optimized'] : 0;
		}

		return [
			'total' => $this->total_images(),
			'optimized' => $this->optimized_count(),
			'original' => $original,
			'optimized_bytes' => $optimized,
			'saved' => max( 0, $original - $optimized ),
		];
	}

	/**
	 * Returns the optimization state of an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array|null
	 */
	public function get_attachment_state( $attachment_id )
	{
		$state = get_post_meta( (int) $attachment_id, self::META_KEY, true );

		return is_array( $state ) ? $state : null;
	}

	/**
	 * Returns the target size in bytes, or 0 when disabled.
	 *
	 * @param int $max_size_mb Target size in megabytes.
	 * @return int
	 */
	protected function target_bytes( $max_size_mb )
	{
		$max_size_mb = (int) $max_size_mb;

		// 1 MB = 1024 * 1024 bytes (the MB_IN_BYTES constant requires WP 5.3).
		return $max_size_mb > 0 ? $max_size_mb * 1024 * 1024 : 0;
	}

	/**
	 * Optimizes a single attachment.
	 *
	 * @param int        $attachment_id Attachment ID.
	 * @param array|null $settings      Optional settings, defaults to the stored ones.
	 * @return array|WP_Error Optimization state.
	 */
	public function optimize_attachment( $attachment_id, $settings = null )
	{
		$attachment_id = (int) $attachment_id;
		$settings = null === $settings ? $this->get_settings() : $settings;

		$file = get_attached_file( $attachment_id );

		if ( ! $file || ! is_readable( $file ) ) {
			return new WP_Error(
				'missing_file',
				__( 'The attached file could not be read.', 'icc-gg-wordpress-super-cache' )
			);
		}

		$mime = (string) get_post_mime_type( $attachment_id );

		if ( '' !== $mime && ! in_array( $mime, $this->source_mimes(), true ) ) {
			return new WP_Error(
				'unsupported',
				__( 'This image format is not optimized by the current settings.', 'icc-gg-wordpress-super-cache' )
			);
		}

		clearstatcache( true, $file );

		$original_bytes = (int) @filesize( $file );
		$original_size = @getimagesize( $file );

		$editor = wp_get_image_editor( $file );

		if ( is_wp_error( $editor ) ) {
			return $editor;
		}

		$before = $editor->get_size();
		$resized = false;

		$max_width = (int) $settings['max_width'];
		$max_height = (int) $settings['max_height'];

		if ( ( $max_width > 0 && $before['width'] > $max_width ) || ( $max_height > 0 && $before['height'] > $max_height ) ) {
			$result = $editor->resize(
				$max_width > 0 ? $max_width : null,
				$max_height > 0 ? $max_height : null,
				false
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$resized = true;
		}

		$quality = (int) $settings['quality'];

		$editor->set_quality( $quality );

		$backup = '';

		if ( ! empty( $settings['backup'] ) ) {
			$backup = $this->create_backup( $file );

			if ( is_wp_error( $backup ) ) {
				return $backup;
			}
		}

		// Optimizing an existing attachment must never change its format: the
		// output format filter would silently turn the in-place save into a
		// conversion, leaving an orphaned original and wrong statistics. WP core
		// neutralizes the filter the same way for its own image operations.
		add_filter( 'image_editor_output_format', '__return_empty_array', 100 );

		$saved = $editor->save( $file );

		if ( is_wp_error( $saved ) ) {
			remove_filter( 'image_editor_output_format', '__return_empty_array', 100 );

			return $saved;
		}

		$target = $this->target_bytes( $settings['max_size_mb'] );

		for ( $attempt = 0; $attempt < 4 && $target > 0; $attempt++ ) {
			clearstatcache( true, $file );

			if ( (int) @filesize( $file ) <= $target || $quality <= 40 ) {
				break;
			}

			$quality = max( 40, $quality - 10 );

			$editor->set_quality( $quality );

			$saved = $editor->save( $file );

			if ( is_wp_error( $saved ) ) {
				break;
			}
		}

		clearstatcache( true, $file );

		$after = $editor->get_size();

		// The editor reports the real destination, which may differ from the
		// requested path when another plugin converts formats on save.
		$final_file = is_array( $saved ) && ! empty( $saved['path'] ) ? $saved['path'] : $file;

		clearstatcache( true, $final_file );

		$optimized_bytes = (int) @filesize( $final_file );

		if ( $resized && function_exists( 'wp_create_image_subsizes' ) ) {
			$metadata = wp_create_image_subsizes( $final_file, $attachment_id );

			if ( ! is_wp_error( $metadata ) ) {
				wp_update_attachment_metadata( $attachment_id, $metadata );
			}
		}

		remove_filter( 'image_editor_output_format', '__return_empty_array', 100 );

		$state = [
			'original' => $original_bytes,
			'optimized' => $optimized_bytes,
			'quality' => $quality,
			'backup' => is_string( $backup ) ? $backup : '',
			'width' => (int) $after['width'],
			'height' => (int) $after['height'],
			'original_width' => is_array( $original_size ) ? (int) $original_size[0] : (int) $before['width'],
			'original_height' => is_array( $original_size ) ? (int) $original_size[1] : (int) $before['height'],
			'time' => time(),
		];

		update_post_meta( $attachment_id, self::META_KEY, $state );

		return $state;
	}

	/**
	 * Copies the original file outside the uploads directory.
	 *
	 * @param string $file Attachment path.
	 * @return string|WP_Error Backup path or an empty string when not needed.
	 */
	protected function create_backup( $file )
	{
		$uploads = wp_get_upload_dir();
		$basedir = untrailingslashit( (string) $uploads['basedir'] );
		$relative = ltrim( str_replace( $basedir, '', $file ), '/\\' );

		if ( '' === $relative ) {
			return '';
		}

		$backup = untrailingslashit( WP_CONTENT_DIR ) . '/icc-gg-image-backups/' . $relative;

		if ( file_exists( $backup ) ) {
			return $backup;
		}

		if ( ! wp_mkdir_p( dirname( $backup ) ) ) {
			return new WP_Error(
				'backup_dir',
				__( 'The backup directory could not be created.', 'icc-gg-wordpress-super-cache' )
			);
		}

		if ( ! @copy( $file, $backup ) ) {
			return new WP_Error(
				'backup',
				__( 'The original image could not be backed up.', 'icc-gg-wordpress-super-cache' )
			);
		}

		return $backup;
	}

	/**
	 * Restores the original image from its backup.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return true|WP_Error
	 */
	public function restore_attachment( $attachment_id )
	{
		$attachment_id = (int) $attachment_id;
		$state = $this->get_attachment_state( $attachment_id );

		if ( ! $state || empty( $state['backup'] ) || ! is_readable( $state['backup'] ) ) {
			return new WP_Error(
				'no_backup',
				__( 'No backup was found for this image.', 'icc-gg-wordpress-super-cache' )
			);
		}

		$file = get_attached_file( $attachment_id );

		if ( ! $file ) {
			return new WP_Error(
				'missing_file',
				__( 'The attached file could not be read.', 'icc-gg-wordpress-super-cache' )
			);
		}

		if ( ! @copy( $state['backup'], $file ) ) {
			return new WP_Error(
				'restore',
				__( 'The original image could not be restored.', 'icc-gg-wordpress-super-cache' )
			);
		}

		@unlink( $state['backup'] );

		delete_post_meta( $attachment_id, self::META_KEY );

		if ( function_exists( 'wp_create_image_subsizes' ) ) {
			$metadata = wp_create_image_subsizes( $file, $attachment_id );

			if ( ! is_wp_error( $metadata ) ) {
				wp_update_attachment_metadata( $attachment_id, $metadata );
			}
		}

		return true;
	}

	/**
	 * Returns the batch progress.
	 *
	 * @return array
	 */
	public function get_batch_state()
	{
		$state = get_option( self::STATE_OPTION, [] );
		$state = is_array( $state ) ? $state : [];

		return wp_parse_args(
			$state,
			[
				'running' => false,
				'processed' => 0,
				'saved' => 0,
				'failed_ids' => [],
			]
		);
	}

	/**
	 * Stores the batch progress.
	 *
	 * @param array $state Batch state.
	 * @return void
	 */
	protected function update_batch_state( $state )
	{
		$state = wp_parse_args(
			(array) $state,
			[
				'running' => false,
				'processed' => 0,
				'saved' => 0,
				'failed_ids' => [],
			]
		);

		update_option( self::STATE_OPTION, $state, false );
	}

	/**
	 * Forgets the batch progress.
	 *
	 * @return void
	 */
	public function reset_batch_state()
	{
		delete_option( self::STATE_OPTION );
	}

	/**
	 * Counts the attachments that still need to be optimized.
	 *
	 * @param int[] $excluded Attachment IDs to skip.
	 * @return int
	 */
	public function pending_count( $excluded = [] )
	{
		$query = new WP_Query(
			[
				'post_type' => 'attachment',
				'post_status' => 'inherit',
				'post_mime_type' => $this->source_mimes(),
				'fields' => 'ids',
				'posts_per_page' => 1,
				'paged' => 1,
				'post__not_in' => array_map( 'intval', (array) $excluded ),
				'update_post_term_cache' => false,
				'meta_query' => [
					[
						'key' => self::META_KEY,
						'compare' => 'NOT EXISTS',
					],
				],
			]
		);

		return (int) $query->found_posts;
	}

	/**
	 * Optimizes a batch of attachments.
	 *
	 * Already optimized images are skipped, and failures are recorded so they
	 * are never retried automatically.
	 *
	 * @param int|null $limit Batch size, defaults to the stored setting.
	 * @return array
	 */
	public function optimize_batch( $limit = null )
	{
		$settings = $this->get_settings();
		$limit = null === $limit ? (int) $settings['batch'] : (int) $limit;
		$limit = min( 20, max( 1, $limit ) );

		$state = $this->get_batch_state();

		$query = new WP_Query(
			[
				'post_type' => 'attachment',
				'post_status' => 'inherit',
				'post_mime_type' => $this->source_mimes(),
				'fields' => 'ids',
				'posts_per_page' => $limit,
				'paged' => 1,
				'orderby' => 'ID',
				'order' => 'ASC',
				'no_found_rows' => true,
				'ignore_sticky_posts' => true,
				'post__not_in' => array_map( 'intval', (array) $state['failed_ids'] ),
				'update_post_term_cache' => false,
				'meta_query' => [
					[
						'key' => self::META_KEY,
						'compare' => 'NOT EXISTS',
					],
				],
			]
		);

		$ids = array_map( 'intval', (array) $query->posts );

		$result = [
			'processed' => 0,
			'failed' => 0,
			'skipped' => 0,
			'saved' => 0,
			'errors' => [],
			'remaining' => 0,
			'done' => false,
		];

		if ( ! $ids ) {
			$state['running'] = false;

			$this->update_batch_state( $state );

			$result['done'] = true;

			return $result;
		}

		$failed_ids = array_map( 'intval', (array) $state['failed_ids'] );

		foreach ( $ids as $attachment_id ) {
			$outcome = $this->optimize_attachment( $attachment_id, $settings );

			if ( is_wp_error( $outcome ) ) {
				$result['failed']++;

				$result['errors'][] = sprintf(
					/* translators: 1: attachment title, 2: attachment ID, 3: error message. */
					__( '%1$s (#%2$d): %3$s', 'icc-gg-wordpress-super-cache' ),
					get_the_title( $attachment_id ),
					$attachment_id,
					$outcome->get_error_message()
				);

				$failed_ids[] = $attachment_id;

				// Record the failure so the image is not retried on every batch.
				update_post_meta(
					$attachment_id,
					self::META_KEY,
					[
						'original' => 0,
						'optimized' => 0,
						'error' => $outcome->get_error_code(),
						'time' => time(),
					]
				);

				continue;
			}

			$result['processed']++;
			$result['saved'] += max( 0, (int) $outcome['original'] - (int) $outcome['optimized'] );

			$state['processed'] = (int) $state['processed'] + 1;
			$state['saved'] = (int) $state['saved'] + max( 0, (int) $outcome['original'] - (int) $outcome['optimized'] );
		}

		$state['failed_ids'] = array_slice( array_values( array_unique( $failed_ids ) ), -100 );

		$result['remaining'] = $this->pending_count( $state['failed_ids'] );
		$result['done'] = 0 === $result['remaining'];

		$state['running'] = ! $result['done'];

		$this->update_batch_state( $state );

		return $result;
	}

	/**
	 * Optimizes images right after they are uploaded.
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array
	 */
	public function maybe_optimize_on_upload( $metadata, $attachment_id )
	{
		if ( empty( $this->get_setting( 'enabled' ) ) ) {
			return $metadata;
		}

		static $running = false;

		if ( $running ) {
			return $metadata;
		}

		$running = true;

		$this->optimize_attachment( (int) $attachment_id );

		$running = false;

		return $metadata;
	}
}
