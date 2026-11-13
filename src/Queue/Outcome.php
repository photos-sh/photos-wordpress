<?php
/**
 * How a task ended.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress\Queue;

defined( 'ABSPATH' ) || exit;

/**
 * The end state of one task.
 */
final class Outcome {

	public const DONE    = 'done';
	public const FAILED  = 'failed';
	public const SKIPPED = 'skipped';

	/**
	 * DONE, FAILED or SKIPPED.
	 *
	 * @var string
	 */
	public string $status;

	/**
	 * The new attachment, when one was made.
	 *
	 * @var int
	 */
	public int $result;

	/**
	 * Why the task failed or was skipped, for the editor.
	 *
	 * @var string
	 */
	public string $message;

	/**
	 * Makes an outcome.
	 *
	 * @param string $status  DONE, FAILED or SKIPPED.
	 * @param int    $result  The new attachment, when one was made.
	 * @param string $message Why the task failed or was skipped.
	 */
	public function __construct( string $status, int $result = 0, string $message = '' ) {
		$this->status  = $status;
		$this->result  = $result;
		$this->message = $message;
	}
}
