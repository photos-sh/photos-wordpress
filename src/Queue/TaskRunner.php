<?php
/**
 * Does the work of a task.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress\Queue;

use PhotosSh\WordPress\AltText;
use PhotosSh\WordPress\Operation;
use PhotosSh\WordPress\ProcessingException;
use PhotosSh\WordPress\Processor;
use PhotosSh\WordPress\SkippedException;
use PhotosSh\WordPress\WooCommerce\ProductImages;

defined( 'ABSPATH' ) || exit;

/**
 * Processes the attachment of a task (or writes its alt text), or the main
 * image of its product.
 */
final class TaskRunner implements Runner {

	/**
	 * Processes attachments.
	 *
	 * @var Processor
	 */
	private Processor $processor;

	/**
	 * Writes alternative text.
	 *
	 * @var AltText
	 */
	private AltText $alt_text;

	/**
	 * Sets the runner up.
	 *
	 * @param Processor $processor Processes attachments.
	 * @param AltText   $alt_text  Writes alternative text.
	 */
	public function __construct( Processor $processor, AltText $alt_text ) {
		$this->processor = $processor;
		$this->alt_text  = $alt_text;
	}

	/**
	 * Runs a task once. The library does not retry here: the worker
	 * reschedules the task instead of making the queue wait.
	 *
	 * An image that is left as it is (it has alt text that is to be kept, or
	 * no face was found in it) is SKIPPED, with the reason.
	 *
	 * @param Task $task The task.
	 * @return Outcome How it ended.
	 * @throws ProcessingException When the task names no known operation.
	 * @throws \Throwable          When the work failed.
	 */
	public function run( Task $task ): Outcome {
		$operation = Operation::from_key( $task->operation );
		if ( null === $operation ) {
			throw new ProcessingException( esc_html__( 'Unknown operation.', 'photos-sh' ) );
		}

		if ( Task::KIND_PRODUCT === $task->kind ) {
			return ( new ProductImages( $this->processor ) )->replace_main_image( $task->item, $operation, $task->user );
		}

		try {
			if ( ! $operation->makes_image() ) {
				$this->alt_text->write( $task->item, $operation->replace );

				return new Outcome( Outcome::DONE );
			}

			return new Outcome( Outcome::DONE, $this->processor->process( $task->item, $operation, 0, $task->user ) );
		} catch ( SkippedException $skipped ) {
			return new Outcome( Outcome::SKIPPED, 0, $skipped->getMessage() );
		}
	}
}
