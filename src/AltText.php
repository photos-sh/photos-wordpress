<?php
/**
 * Alternative text written by the API.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Sets an image attachment's alternative text to the API's short
 * description of the image. No image is made or changed.
 *
 * Text that is already there is kept, unless the caller asks for it to be
 * replaced.
 */
final class AltText {

	public const META = '_wp_attachment_image_alt';

	/**
	 * Makes the API clients.
	 *
	 * @var ClientFactory
	 */
	private ClientFactory $clients;

	/**
	 * Sets the service up.
	 *
	 * @param ClientFactory $clients Makes the API clients.
	 */
	public function __construct( ClientFactory $clients ) {
		$this->clients = $clients;
	}

	/**
	 * What an editor should know about the text, wherever it is offered.
	 */
	public static function notice(): string {
		return __( 'The text is written by AI, in English. Read it before publishing.', 'photos-sh' );
	}

	/**
	 * An attachment's alternative text; empty when it has none.
	 *
	 * @param int $attachment_id The attachment.
	 */
	public static function current( int $attachment_id ): string {
		$text = get_post_meta( $attachment_id, self::META, true );

		return is_string( $text ) ? trim( $text ) : '';
	}

	/**
	 * Writes an image attachment's alternative text.
	 *
	 * An image that has alternative text is not sent to the API unless the
	 * text is to be replaced.
	 *
	 * @param int  $attachment_id The attachment.
	 * @param bool $replace       Whether to replace text that is already there.
	 * @param int  $max_retries   Extra attempts the library makes itself, waiting in between.
	 * @return string The text that was written.
	 * @throws SkippedException       When the image has alternative text and it is not to be replaced.
	 * @throws ProcessingException    When the attachment cannot be read or the API returned no text.
	 * @throws NotConfiguredException When there is no API key.
	 * @throws \PhotosSh\Exception\PhotosException When the API or the connection fails.
	 */
	public function write( int $attachment_id, bool $replace = false, int $max_retries = 0 ): string {
		[ , $path ] = SourceImage::read( $attachment_id );

		$previous = self::current( $attachment_id );
		if ( '' !== $previous && ! $replace ) {
			throw new SkippedException( esc_html__( 'The image already has alternative text. It was kept.', 'photos-sh' ) );
		}

		$text = self::text_of( $this->clients->create( $max_retries )->caption( $path, 'short' ) );
		if ( '' === $text ) {
			throw new ProcessingException( esc_html__( 'Photos.sh did not return a description.', 'photos-sh' ) );
		}

		update_post_meta( $attachment_id, self::META, wp_slash( $text ) );

		/**
		 * Fires after an image's alternative text was written.
		 *
		 * @param int    $attachment_id The attachment.
		 * @param string $text          The new alternative text.
		 * @param string $previous      The text it replaced; empty when there was none.
		 */
		do_action( 'photos_sh_alt_text_written', $attachment_id, $text, $previous );

		return $text;
	}

	/**
	 * The description in the API's answer, as plain text on one line.
	 *
	 * @param array<string, mixed> $answer What the API answered.
	 */
	private static function text_of( array $answer ): string {
		$caption = $answer['caption'] ?? '';

		return is_string( $caption ) ? sanitize_text_field( $caption ) : '';
	}
}
