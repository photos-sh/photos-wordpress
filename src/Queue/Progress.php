<?php
/**
 * Records how tasks end.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress\Queue;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the outcome of each task of a batch.
 */
interface Progress {

	/**
	 * Records how a task ended.
	 *
	 * @param Task    $task    The task.
	 * @param Outcome $outcome How it ended.
	 */
	public function record( Task $task, Outcome $outcome ): void;
}
