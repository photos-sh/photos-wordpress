<?php
/**
 * Action Scheduler or WP-Cron.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress\Queue;

defined( 'ABSPATH' ) || exit;

/**
 * Schedules tasks with Action Scheduler when a plugin on the site provides
 * it (WooCommerce does), and with WP-Cron otherwise.
 */
final class WpScheduler implements Scheduler {

	public const HOOK  = 'photos_sh_task';
	public const GROUP = 'photos-sh';

	/**
	 * Schedules a task.
	 *
	 * @param Task $task  The task.
	 * @param int  $delay Seconds to wait before running it.
	 */
	public function schedule( Task $task, int $delay = 0 ): void {
		$args = [ $task->to_array() ];

		if ( self::has_action_scheduler() ) {
			if ( $delay > 0 ) {
				as_schedule_single_action( time() + $delay, self::HOOK, $args, self::GROUP );
			} else {
				as_enqueue_async_action( self::HOOK, $args, self::GROUP );
			}

			return;
		}

		wp_schedule_single_event( time() + $delay, self::HOOK, $args );
	}

	/**
	 * Removes every task that has not run yet.
	 */
	public static function unschedule_all(): void {
		if ( self::has_action_scheduler() ) {
			as_unschedule_all_actions( self::HOOK );
		}
		wp_unschedule_hook( self::HOOK );
	}

	/**
	 * Whether Action Scheduler is loaded and ready to take actions.
	 */
	public static function has_action_scheduler(): bool {
		return function_exists( 'as_enqueue_async_action' )
			&& function_exists( 'as_schedule_single_action' )
			&& class_exists( '\ActionScheduler' )
			&& \ActionScheduler::is_initialized();
	}
}
