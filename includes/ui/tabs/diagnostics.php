<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Diagnostics tab template.
 *
 * @package   ICC_GG_WordPress_Super_Cache
 * @category  General
 * @author    Ivan Carlos
 * @copyright 2007-2026 Ivan Carlos Consultoria
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0-or-later
 */

?>

<div class="card" id="icc-gg-wordpress-super-cache-diagnostics"><?php require __DIR__ . '/../../diagnostics.php'; ?></div>

<p id="icc-gg-wordpress-super-cache-copy-button">
	<span class="copy-button-wrapper">
		<button type="button" class="button copy-button" data-clipboard-target="#icc-gg-wordpress-super-cache-diagnostics">
			<?php esc_html_e( 'Copy diagnostics to clipboard', 'icc-gg-wordpress-super-cache' ); ?>
		</button>
		<span class="success hidden" aria-hidden="true"><?php esc_html_e( 'Copied!', 'icc-gg-wordpress-super-cache' ); ?></span>
	</span>
</p>
