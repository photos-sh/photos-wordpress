<?php
/**
 * A queued task.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress\Queue;

defined( 'ABSPATH' ) || exit;

/**
 * One image to process in the background.
 */
final class Task {

	public const KIND_ATTACHMENT = 'attachment';
	public const KIND_PRODUCT    = 'product';

	/**
	 * The batch the task belongs to.
	 *
	 * @var string
	 */
	public string $batch;

	/**
	 * What the item is: an attachment, or a product whose main image is replaced.
	 *
	 * @var string
	 */
	public string $kind;

	/**
	 * The attachment's or the product's post ID.
	 *
	 * @var int
	 */
	public int $item;

	/**
	 * The operation's key.
	 *
	 * @var string
	 */
	public string $operation;

	/**
	 * The user who started the batch.
	 *
	 * @var int
	 */
	public int $user;

	/**
	 * How many times the task was tried before: 0 on the first run.
	 *
	 * @var int
	 */
	public int $attempt;

	/**
	 * Makes a task.
	 *
	 * @param string $batch     The batch the task belongs to.
	 * @param string $kind      KIND_ATTACHMENT or KIND_PRODUCT.
	 * @param int    $item      The attachment's or the product's post ID.
	 * @param string $operation The operation's key.
	 * @param int    $user      The user who started the batch.
	 * @param int    $attempt   How many times the task was tried before.
	 */
	public function __construct( string $batch, string $kind, int $item, string $operation, int $user, int $attempt = 0 ) {
		$this->batch     = $batch;
		$this->kind      = $kind;
		$this->item      = $item;
		$this->operation = $operation;
		$this->user      = $user;
		$this->attempt   = $attempt;
	}

	/**
	 * The same task, one attempt later.
	 */
	public function next_attempt(): self {
		return new self( $this->batch, $this->kind, $this->item, $this->operation, $this->user, $this->attempt + 1 );
	}

	/**
	 * The task as the scheduler stores it.
	 *
	 * @return array{batch: string, kind: string, item: int, operation: string, user: int, attempt: int}
	 */
	public function to_array(): array {
		return [
			'batch'     => $this->batch,
			'kind'      => $this->kind,
			'item'      => $this->item,
			'operation' => $this->operation,
			'user'      => $this->user,
			'attempt'   => $this->attempt,
		];
	}

	/**
	 * Reads a task back from what the scheduler stored; null when it is not one.
	 *
	 * @param mixed $data What the scheduler passed to the hook.
	 */
	public static function from_array( $data ): ?self {
		if ( ! is_array( $data ) ) {
			return null;
		}
		foreach ( [ 'batch', 'kind', 'operation' ] as $field ) {
			if ( ! isset( $data[ $field ] ) || ! is_string( $data[ $field ] ) || '' === $data[ $field ] ) {
				return null;
			}
		}
		foreach ( [ 'item', 'user', 'attempt' ] as $field ) {
			if ( ! isset( $data[ $field ] ) || ! is_numeric( $data[ $field ] ) ) {
				return null;
			}
		}
		if ( ! in_array( $data['kind'], [ self::KIND_ATTACHMENT, self::KIND_PRODUCT ], true ) || (int) $data['item'] <= 0 ) {
			return null;
		}

		return new self(
			$data['batch'],
			$data['kind'],
			(int) $data['item'],
			$data['operation'],
			(int) $data['user'],
			max( 0, (int) $data['attempt'] )
		);
	}
}
