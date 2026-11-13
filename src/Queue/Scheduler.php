<?php
/**
 * Schedules tasks.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress\Queue;

defined( 'ABSPATH' ) || exit;

/**
 * Puts tasks in the site's background queue.
 */
interface Scheduler {

	/**
	 * Schedules a task.
	 *
	 * @param Task $task  The task.
	 * @param int  $delay Seconds to wait before running it.
	 */
	public function schedule( Task $task, int $delay = 0 ): void;
}
