<?php
/**
 * HTTP through WordPress.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress;

use PhotosSh\Exception\ConnectionException;
use PhotosSh\Exception\TimeoutException;
use PhotosSh\Transport\Request;
use PhotosSh\Transport\Response;
use PhotosSh\Transport\Transport;

defined( 'ABSPATH' ) || exit;

/**
 * Sends the library's requests with `wp_remote_request()`, so that the
 * site's proxy settings and HTTP filters apply.
 */
final class WpTransport implements Transport {

	/**
	 * Sends one request. Redirects are not followed and nothing is retried:
	 * the library does that.
	 *
	 * @param Request $request The request to send.
	 * @throws ConnectionException When no response arrives (a TimeoutException when the request timed out).
	 */
	public function send( Request $request ): Response {
		$headers = $request->headers;
		foreach ( $headers as $name => $value ) {
			if ( 'user-agent' === strtolower( $name ) ) {
				$headers[ $name ] = $value . ' photos-sh-wordpress/' . Plugin::VERSION;
			}
		}

		$args = [
			'method'      => $request->method,
			'headers'     => $headers,
			'timeout'     => $request->timeout,
			'redirection' => 0,
			'httpversion' => '1.1',
		];
		if ( null !== $request->body ) {
			$args['body'] = $request->body;
		}

		$result = wp_remote_request( $request->url, $args );

		if ( is_wp_error( $result ) ) {
			$message = sprintf( '%s %s failed: %s', $request->method, $request->url, $result->get_error_message() );
			if ( self::is_timeout( $result->get_error_message() ) ) {
				throw new TimeoutException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			throw new ConnectionException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$response_headers = [];
		foreach ( wp_remote_retrieve_headers( $result ) as $name => $value ) {
			$response_headers[ (string) $name ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
		}

		return new Response(
			(int) wp_remote_retrieve_response_code( $result ),
			$response_headers,
			wp_remote_retrieve_body( $result )
		);
	}

	/**
	 * Whether a WordPress HTTP error message describes a timeout. WordPress
	 * gives every failure the same error code, so the message is all there is.
	 *
	 * @param string $message The error message.
	 */
	private static function is_timeout( string $message ): bool {
		return 1 === preg_match( '/cURL error 28\b|timed out|timeout/i', $message );
	}
}
