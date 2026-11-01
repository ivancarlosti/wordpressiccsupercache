<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * UI utility class.
 *
 * @package   ICC_GG_WordPress_Super_Cache
 * @category  General
 * @author    Ivan Carlos
 * @copyright 2007-2026 Ivan Carlos Consultoria
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0-or-later
 */

/**
 * UI class definition
 */
class ICC_GG_WordPress_Super_Cache_UI
{
	/**
	 * Holds all registered tabs
	 *
	 * @var array
	 */
	private static $tabs = [];

	/**
	 * Registers a settings tab
	 *
	 * @param string $slug   Unique slug to identify the tab.
	 * @param string $label  Tab label.
	 * @param array  $args   Optional arguments describing the tab.
	 * @return void
	 */
	public static function register_tab( $slug, $label, $args = [] )
	{
		self::$tabs[ $slug ] = new ICC_GG_WordPress_Super_Cache_UI_Tab( $slug, $label, $args );
	}

	/**
	 * Retrieves all registered tabs
	 *
	 * @return array
	 */
	public static function get_tabs()
	{
		return self::$tabs;
	}

}
