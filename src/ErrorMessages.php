<?php
/**
 * Errors in plain words.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress;

use PhotosSh\Exception\AccountSuspendedException;
use PhotosSh\Exception\ApiException;
use PhotosSh\Exception\BusyException;
use PhotosSh\Exception\ConnectionException;
use PhotosSh\Exception\PaymentRequiredException;
use PhotosSh\Exception\TimeoutException;
use PhotosSh\Exception\UnauthorizedException;

defined( 'ABSPATH' ) || exit;

/**
 * Turns the library's exceptions into messages for the editor, and logs them.
 */
final class ErrorMessages {

	/**
	 * A message an editor can act on.
	 *
	 * @param \Throwable $error What went wrong.
	 */
	public static function describe( \Throwable $error ): string {
		if ( $error instanceof ProcessingException || $error instanceof SkippedException ) {
			return $error->getMessage();
		}
		if ( $error instanceof NotConfiguredException ) {
			return __( 'Photos.sh has no API key yet. Add one under Settings → Photos.sh.', 'photos-sh' );
		}
		if ( $error instanceof UnauthorizedException ) {
			return __( 'Photos.sh refused the API key. Check it under Settings → Photos.sh.', 'photos-sh' );
		}
		if ( $error instanceof PaymentRequiredException ) {
			return __( 'The Photos.sh account is out of credits. Add credits, then try again.', 'photos-sh' );
		}
		if ( $error instanceof AccountSuspendedException ) {
			return __( 'The Photos.sh account is suspended.', 'photos-sh' );
		}
		if ( $error instanceof BusyException ) {
			return __( 'Photos.sh is busy right now. Try again in a moment.', 'photos-sh' );
		}
		if ( $error instanceof TimeoutException ) {
			return __( 'Photos.sh took too long to answer. Try again.', 'photos-sh' );
		}
		if ( $error instanceof ConnectionException ) {
			return __( 'Could not reach Photos.sh. Check the connection of this site, then try again.', 'photos-sh' );
		}
		if ( $error instanceof ApiException ) {
			switch ( $error->errorCode ) {
				case ApiException::PAYLOAD_TOO_LARGE:
					return __( 'The image file is too large for Photos.sh.', 'photos-sh' );
				case ApiException::UNSUPPORTED_FORMAT:
					return __( 'Photos.sh cannot read this image format.', 'photos-sh' );
				case ApiException::MODEL_UNAVAILABLE:
					return __( 'This tool is not available on the Photos.sh server.', 'photos-sh' );
				case ApiException::BAD_REQUEST:
				case ApiException::UNPROCESSABLE:
					/* translators: %s: the reason the API gave, in English. */
					return sprintf( __( 'Photos.sh could not process this image: %s', 'photos-sh' ), $error->getMessage() );
			}
			if ( 503 === $error->status ) {
				return __( 'Photos.sh is busy right now. Try again in a moment.', 'photos-sh' );
			}

			/* translators: %d: an HTTP status code. */
			return sprintf( __( 'Photos.sh answered with an error (HTTP %d). Try again later.', 'photos-sh' ), $error->status );
		}

		return __( 'Something went wrong while processing the image.', 'photos-sh' );
	}

	/**
	 * Writes an error to the PHP error log, without the API key, and fires
	 * the `photos_sh_error` action for sites that log elsewhere.
	 *
	 * @param \Throwable $error   What went wrong.
	 * @param string     $context What was being done, such as `remove-background of attachment 12`.
	 * @param string     $api_key The API key, removed from the line should it ever appear in it.
	 */
	public static function log( \Throwable $error, string $context, string $api_key = '' ): void {
		$line = sprintf( 'Photos.sh: %s: %s: %s', $context, get_class( $error ), $error->getMessage() );
		if ( '' !== $api_key ) {
			$line = str_replace( $api_key, '[key]', $line );
		}

		/**
		 * Fires when an image could not be processed.
		 *
		 * @param \Throwable $error   What went wrong.
		 * @param string     $context What was being done.
		 */
		do_action( 'photos_sh_error', $error, $context );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- the design asks for errors to be logged.
		error_log( $line );
	}
}
