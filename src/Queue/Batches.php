<?php
/**
 * Bulk actions in progress.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress\Queue;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the batches bulk actions start, and the outcome of each task.
 *
 * A batch (who started it, the operation, the items) is written once, to an
 * option. Each task then adds its outcome as a post meta row on its item:
 * an insert, so tasks that run at the same time cannot overwrite each other.
 */
final class Batches implements Progress {

	public const OPTION = 'photos_sh_batches';
	public const META   = '_photos_sh_task';

	/**
	 * Batches older than this are forgotten, in seconds.
	 */
	public const MAX_AGE = 604800;

	/**
	 * Starts a batch.
	 *
	 * @param int       $user      The user who started it.
	 * @param string    $operation The operation's key.
	 * @param string    $kind      Task::KIND_ATTACHMENT or Task::KIND_PRODUCT.
	 * @param list<int> $items     The attachments' or products' post IDs.
	 * @return string The batch's ID.
	 */
	public function create( int $user, string $operation, string $kind, array $items ): string {
		$id      = strtolower( wp_generate_password( 12, false ) );
		$batches = $this->prune( $this->all() );

		$batches[ $id ] = [
			'user'      => $user,
			'operation' => $operation,
			'kind'      => $kind,
			'items'     => array_map( 'intval', $items ),
			'created'   => time(),
		];
		update_option( self::OPTION, $batches, false );

		return $id;
	}

	/**
	 * Records how a task ended.
	 *
	 * @param Task    $task    The task.
	 * @param Outcome $outcome How it ended.
	 */
	public function record( Task $task, Outcome $outcome ): void {
		add_post_meta(
			$task->item,
			self::META,
			wp_slash(
				[
					'batch'   => $task->batch,
					'status'  => $outcome->status,
					'result'  => $outcome->result,
					'message' => $outcome->message,
				]
			)
		);
	}

	/**
	 * The batches a user started, oldest first, each with its progress.
	 *
	 * @param int $user The user.
	 * @return array<string, array{operation: string, kind: string, total: int, done: int, failed: int, skipped: int, pending: int, failures: list<array{item: int, message: string}>}>
	 */
	public function for_user( int $user ): array {
		$summaries = [];
		foreach ( $this->all() as $id => $batch ) {
			if ( $batch['user'] !== $user ) {
				continue;
			}
			$summaries[ (string) $id ] = $this->summarise( (string) $id, $batch );
		}

		return $summaries;
	}

	/**
	 * Forgets a batch a user started, with the outcomes of its tasks.
	 *
	 * @param string $id   The batch's ID.
	 * @param int    $user The user.
	 */
	public function dismiss( string $id, int $user ): void {
		$batches = $this->all();
		if ( ! isset( $batches[ $id ] ) || $batches[ $id ]['user'] !== $user ) {
			return;
		}

		$this->forget_outcomes( $id, $batches[ $id ]['items'] );
		unset( $batches[ $id ] );
		update_option( self::OPTION, $batches, false );
	}

	/**
	 * Counts a batch's outcomes.
	 *
	 * @param string                                                                            $id    The batch's ID.
	 * @param array{user: int, operation: string, kind: string, items: list<int>, created: int} $batch The batch.
	 * @return array{operation: string, kind: string, total: int, done: int, failed: int, skipped: int, pending: int, failures: list<array{item: int, message: string}>}
	 */
	private function summarise( string $id, array $batch ): array {
		$summary = [
			'operation' => $batch['operation'],
			'kind'      => $batch['kind'],
			'total'     => count( $batch['items'] ),
			'done'      => 0,
			'failed'    => 0,
			'skipped'   => 0,
			'pending'   => 0,
			'failures'  => [],
		];

		update_meta_cache( 'post', $batch['items'] );
		foreach ( $batch['items'] as $item ) {
			$outcome = $this->outcome_of( $id, $item );
			if ( null === $outcome ) {
				++$summary['pending'];
				continue;
			}
			if ( Outcome::DONE === $outcome['status'] ) {
				++$summary['done'];
				continue;
			}
			if ( Outcome::SKIPPED === $outcome['status'] ) {
				++$summary['skipped'];
			} else {
				++$summary['failed'];
			}
			$summary['failures'][] = [
				'item'    => $item,
				'message' => $outcome['message'],
			];
		}

		return $summary;
	}

	/**
	 * The recorded outcome of one item of a batch, or null while it is pending.
	 *
	 * @param string $id   The batch's ID.
	 * @param int    $item The item's post ID.
	 * @return array{status: string, message: string}|null
	 */
	private function outcome_of( string $id, int $item ): ?array {
		$rows = get_post_meta( $item, self::META, false );
		foreach ( is_array( $rows ) ? $rows : [] as $row ) {
			if ( is_array( $row ) && ( $row['batch'] ?? '' ) === $id ) {
				return [
					'status'  => (string) ( $row['status'] ?? Outcome::FAILED ),
					'message' => (string) ( $row['message'] ?? '' ),
				];
			}
		}

		return null;
	}

	/**
	 * Deletes the outcome rows of a batch.
	 *
	 * @param string    $id    The batch's ID.
	 * @param list<int> $items The batch's items.
	 */
	private function forget_outcomes( string $id, array $items ): void {
		foreach ( $items as $item ) {
			$rows = get_post_meta( $item, self::META, false );
			foreach ( is_array( $rows ) ? $rows : [] as $row ) {
				if ( is_array( $row ) && ( $row['batch'] ?? '' ) === $id ) {
					delete_post_meta( $item, self::META, wp_slash( $row ) );
				}
			}
		}
	}

	/**
	 * Every batch, well-formed.
	 *
	 * @return array<string, array{user: int, operation: string, kind: string, items: list<int>, created: int}>
	 */
	private function all(): array {
		$stored  = get_option( self::OPTION, [] );
		$batches = [];
		foreach ( is_array( $stored ) ? $stored : [] as $id => $batch ) {
			if ( ! is_array( $batch ) || ! isset( $batch['items'] ) || ! is_array( $batch['items'] ) ) {
				continue;
			}
			$batches[ (string) $id ] = [
				'user'      => (int) ( $batch['user'] ?? 0 ),
				'operation' => (string) ( $batch['operation'] ?? '' ),
				'kind'      => (string) ( $batch['kind'] ?? Task::KIND_ATTACHMENT ),
				'items'     => array_values( array_map( 'intval', $batch['items'] ) ),
				'created'   => (int) ( $batch['created'] ?? 0 ),
			];
		}

		return $batches;
	}

	/**
	 * Drops batches older than MAX_AGE, with their outcomes.
	 *
	 * @param array<string, array{user: int, operation: string, kind: string, items: list<int>, created: int}> $batches Every batch.
	 * @return array<string, array{user: int, operation: string, kind: string, items: list<int>, created: int}>
	 */
	private function prune( array $batches ): array {
		foreach ( $batches as $id => $batch ) {
			if ( $batch['created'] < time() - self::MAX_AGE ) {
				$this->forget_outcomes( (string) $id, $batch['items'] );
				unset( $batches[ $id ] );
			}
		}

		return $batches;
	}
}
