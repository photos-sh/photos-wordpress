<?php
/**
 * Handles queued tasks.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress\Queue;

use PhotosSh\WordPress\ErrorMessages;

defined( 'ABSPATH' ) || exit;

/**
 * Runs one task when the scheduler fires its hook: records the outcome, or
 * schedules the task again when the retry policy allows it.
 */
final class Worker {

	/**
	 * Does the work.
	 *
	 * @var Runner
	 */
	private Runner $runner;

	/**
	 * Schedules retries.
	 *
	 * @var Scheduler
	 */
	private Scheduler $scheduler;

	/**
	 * Records outcomes.
	 *
	 * @var Progress
	 */
	private Progress $progress;

	/**
	 * Decides on retries.
	 *
	 * @var RetryPolicy
	 */
	private RetryPolicy $retries;

	/**
	 * Called with the error and the task when a task fails for good.
	 *
	 * @var callable(\Throwable, Task): void
	 */
	private $on_failure;

	/**
	 * Sets the worker up.
	 *
	 * @param Runner                           $runner     Does the work.
	 * @param Scheduler                        $scheduler  Schedules retries.
	 * @param Progress                         $progress   Records outcomes.
	 * @param RetryPolicy                      $retries    Decides on retries.
	 * @param callable(\Throwable, Task): void $on_failure Called when a task fails for good (to log it).
	 */
	public function __construct( Runner $runner, Scheduler $scheduler, Progress $progress, RetryPolicy $retries, callable $on_failure ) {
		$this->runner     = $runner;
		$this->scheduler  = $scheduler;
		$this->progress   = $progress;
		$this->retries    = $retries;
		$this->on_failure = $on_failure;
	}

	/**
	 * The scheduler's hook: runs one task.
	 *
	 * @param mixed $data The task, as Task::to_array() made it.
	 */
	public function handle( $data ): void {
		$task = Task::from_array( $data );
		if ( null === $task ) {
			return;
		}

		try {
			$outcome = $this->runner->run( $task );
		} catch ( \Throwable $error ) {
			$delay = $this->retries->delay( $error, $task->attempt );
			if ( null !== $delay ) {
				$this->scheduler->schedule( $task->next_attempt(), $delay );

				return;
			}

			( $this->on_failure )( $error, $task );
			$outcome = new Outcome( Outcome::FAILED, 0, ErrorMessages::describe( $error ) );
		}

		$this->progress->record( $task, $outcome );
	}
}
