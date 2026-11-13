<?php
/**
 * Removes what the plugin stored: its options and the progress records of
 * bulk actions. Images the plugin made stay in the media library, with the
 * meta that links them to their source.
 *
 * @package PhotosSh\WordPress
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'photos_sh_settings' );
delete_option( 'photos_sh_batches' );
delete_post_meta_by_key( '_photos_sh_task' );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'photos_sh_task' );
}
wp_unschedule_hook( 'photos_sh_task' );
