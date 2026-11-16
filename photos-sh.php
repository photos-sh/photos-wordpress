<?php
/**
 * Plugin Name:       Photos.sh
 * Plugin URI:        https://photos.sh
 * Description:       Remove backgrounds, upscale, remove text and fix product photos for marketplaces, from the media library. Uses the Photos.sh API.
 * Version:           1.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.3
 * Author:            Photos.sh
 * Author URI:        https://photos.sh
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       photos-sh
 * Domain Path:       /languages
 *
 * @package PhotosSh\WordPress
 */

defined( 'ABSPATH' ) || exit;

define( 'PHOTOS_SH_VERSION', '1.1.0' );

if ( ! is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Photos.sh is missing its bundled library. Install the plugin from a release zip, or run "composer install" in the plugin folder.', 'photos-sh' );
			echo '</p></div>';
		}
	);

	return;
}

require_once __DIR__ . '/vendor/autoload.php';

PhotosSh\WordPress\Plugin::boot( __FILE__ );
