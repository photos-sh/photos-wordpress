<?php
/**
 * The image an operation starts from.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Finds an image attachment and its file.
 */
final class SourceImage {

	/**
	 * The attachment's post and the path of its main file.
	 *
	 * @param int $attachment_id The attachment.
	 * @return array{\WP_Post, string}
	 * @throws ProcessingException When it is not an image attachment, or its file is not on this server.
	 */
	public static function read( int $attachment_id ): array {
		$source = get_post( $attachment_id );
		if ( ! $source instanceof \WP_Post || 'attachment' !== $source->post_type || ! wp_attachment_is_image( $attachment_id ) ) {
			throw new ProcessingException( esc_html__( 'This is not an image in the media library.', 'photos-sh' ) );
		}

		$path = get_attached_file( $attachment_id );
		if ( ! is_string( $path ) || '' === $path || ! is_readable( $path ) ) {
			throw new ProcessingException( esc_html__( 'The image file is not on this server.', 'photos-sh' ) );
		}

		return [ $source, $path ];
	}
}
