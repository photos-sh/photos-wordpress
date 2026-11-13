<?php
/**
 * Runs a task.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress\Queue;

defined( 'ABSPATH' ) || exit;

/**
 * Does the work of one task.
 */
interface Runner {

	/**
	 * Runs a task once.
	 *
	 * @param Task $task The task.
	 * @return Outcome How it ended.
	 * @throws \Throwable When the work failed; the worker decides whether to retry.
	 */
	public function run( Task $task ): Outcome;
}
