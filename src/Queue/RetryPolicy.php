<?php
/**
 * When a failed task is tried again.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress\Queue;

use PhotosSh\Exception\ApiException;
use PhotosSh\Exception\BusyException;
use PhotosSh\Exception\ConnectionException;
use PhotosSh\Exception\TimeoutException;

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether a failed task runs again, and after how long.
 *
 * A busy server and a failed connection are worth another try, at most
 * MAX_RETRIES times. A timeout is not: the server may still be working on
 * the image (and charge for it), so a retry could pay twice. Every other
 * error (no credits, a refused key, an unreadable image) would only repeat.
 */
final class RetryPolicy {

	public const MAX_RETRIES = 3;

	/**
	 * Seconds before the first retry; doubled for each one after.
	 */
	public const BASE_DELAY = 60;

	/**
	 * The longest wait, whatever the server asks for.
	 */
	public const MAX_DELAY = 900;

	/**
	 * Seconds to wait before running the task again, or null to give up.
	 *
	 * @param \Throwable $error   What went wrong.
	 * @param int        $attempt How many times the task was tried before this run: 0 on the first.
	 */
	public function delay( \Throwable $error, int $attempt ): ?int {
		if ( $attempt >= self::MAX_RETRIES ) {
			return null;
		}

		$backoff = min( self::MAX_DELAY, self::BASE_DELAY * ( 2 ** max( 0, $attempt ) ) );

		if ( $error instanceof BusyException || ( $error instanceof ApiException && 503 === $error->status && null === $error->errorCode ) ) {
			$asked = $error->retryAfter;

			return null !== $asked && $asked > 0 ? min( self::MAX_DELAY, max( $asked, 5 ) ) : $backoff;
		}

		if ( $error instanceof TimeoutException ) {
			return null;
		}

		if ( $error instanceof ConnectionException ) {
			return $backoff;
		}

		return null;
	}
}
