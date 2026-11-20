<?php
/**
 * The plugin.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress;

use PhotosSh\WordPress\Admin\BatchNotice;
use PhotosSh\WordPress\Admin\Flash;
use PhotosSh\WordPress\Admin\MediaActions;
use PhotosSh\WordPress\Admin\SettingsPage;
use PhotosSh\WordPress\Cli\Command;
use PhotosSh\WordPress\Queue\Batches;
use PhotosSh\WordPress\Queue\RetryPolicy;
use PhotosSh\WordPress\Queue\Task;
use PhotosSh\WordPress\Queue\TaskRunner;
use PhotosSh\WordPress\Queue\Worker;
use PhotosSh\WordPress\Queue\WpScheduler;
use PhotosSh\WordPress\WooCommerce\ProductBulkAction;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the plugin's services and registers their hooks.
 */
final class Plugin {

	public const VERSION = '1.2.0';

	/**
	 * Starts the plugin. Called once, from the main plugin file.
	 *
	 * @param string $plugin_file The main plugin file.
	 */
	public static function boot( string $plugin_file ): void {
		$settings  = new Settings();
		$clients   = new ClientFactory( $settings );
		$processor = new Processor( $clients, $settings );
		$alt_text  = new AltText( $clients );
		$scheduler = new WpScheduler();
		$batches   = new Batches();

		$worker = new Worker(
			new TaskRunner( $processor, $alt_text ),
			$scheduler,
			$batches,
			new RetryPolicy(),
			static function ( \Throwable $error, Task $task ) use ( $settings ): void {
				ErrorMessages::log(
					$error,
					sprintf( '%s of %s %d', $task->operation, $task->kind, $task->item ),
					$settings->api_key()
				);
			}
		);
		add_action( WpScheduler::HOOK, [ $worker, 'handle' ] );

		register_deactivation_hook( $plugin_file, [ WpScheduler::class, 'unschedule_all' ] );

		if ( is_admin() ) {
			$flash = new Flash();
			( new SettingsPage( $settings, $clients, $plugin_file ) )->register();
			( new MediaActions( $processor, $alt_text, $settings, $scheduler, $batches, $flash ) )->register();
			( new BatchNotice( $batches, $plugin_file ) )->register();

			add_action(
				'plugins_loaded',
				static function () use ( $settings, $scheduler, $batches, $flash ): void {
					if ( class_exists( '\WooCommerce' ) ) {
						( new ProductBulkAction( $settings, $scheduler, $batches, $flash ) )->register();
					}
				}
			);
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'photos-sh', new Command( $processor, $alt_text, $clients ) );
		}
	}
}
