<?php
/**
 * Processes one attachment.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Sends an image attachment through an operation and saves the result as a
 * new attachment. The original is never changed.
 */
final class Processor {

	public const META_SOURCE    = '_photos_sh_source';
	public const META_OPERATION = '_photos_sh_operation';
	public const META_PARAMS    = '_photos_sh_params';
	public const META_RESULT    = '_photos_sh_result';

	/**
	 * Makes the API clients.
	 *
	 * @var ClientFactory
	 */
	private ClientFactory $clients;

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Sets the processor up.
	 *
	 * @param ClientFactory $clients  Makes the API clients.
	 * @param Settings      $settings The settings.
	 */
	public function __construct( ClientFactory $clients, Settings $settings ) {
		$this->clients  = $clients;
		$this->settings = $settings;
	}

	/**
	 * Runs an operation on an attachment.
	 *
	 * @param int       $attachment_id The source attachment.
	 * @param Operation $operation     What to do; one that makes an image. A remove background
	 *                                 that does not say whether the photo is a studio shot
	 *                                 follows the "Studio shots" setting.
	 * @param int       $max_retries   Extra attempts the library makes itself, waiting in between.
	 * @param int       $author        The new attachment's author; the current user by default.
	 * @return int The new attachment's ID.
	 * @throws ProcessingException    When the attachment cannot be read or the result cannot be saved.
	 * @throws SkippedException       When the API found nothing to work on (no face); no attachment is made.
	 * @throws NotConfiguredException When there is no API key.
	 * @throws \PhotosSh\Exception\PhotosException When the API or the connection fails.
	 */
	public function process( int $attachment_id, Operation $operation, int $max_retries = 0, int $author = 0 ): int {
		[ $source, $path ] = SourceImage::read( $attachment_id );

		if ( Operation::REMOVE_BACKGROUND === $operation->name && null === $operation->studio ) {
			$operation = Operation::remove_background( $this->settings->studio() );
		}

		$result    = $operation->run( $this->clients->create( $max_retries ), $path );
		$extension = $result->extension();
		if ( 'bin' === $extension || '' === $result->bytes ) {
			throw new ProcessingException( esc_html__( 'Photos.sh did not return an image.', 'photos-sh' ) );
		}

		// With no face to hide or restore, the image that came back is the
		// source again. Saving it as "faces hidden" would mislead.
		$details = Operation::details( $result );
		if ( $operation->found_nothing( $details ) ) {
			throw new SkippedException( esc_html( $operation->result_note( $details ) . ' ' . __( 'No new image was saved.', 'photos-sh' ) ) );
		}

		$upload = wp_upload_bits( Naming::filename( $path, $operation->suffix(), $extension ), null, $result->bytes );
		if ( ! empty( $upload['error'] ) ) {
			throw new ProcessingException(
				/* translators: %s: the reason WordPress gave. */
				esc_html( sprintf( __( 'The new image could not be saved: %s', 'photos-sh' ), (string) $upload['error'] ) )
			);
		}

		$new_id = wp_insert_attachment(
			[
				'post_title'     => Naming::title( $source->post_title, $operation->done_label() ),
				'post_content'   => '',
				'post_excerpt'   => $source->post_excerpt,
				'post_status'    => 'inherit',
				'post_mime_type' => (string) $upload['type'],
				'post_author'    => $author > 0 ? $author : get_current_user_id(),
			],
			$upload['file'],
			$source->post_parent,
			true
		);
		if ( is_wp_error( $new_id ) ) {
			wp_delete_file( $upload['file'] );
			throw new ProcessingException(
				/* translators: %s: the reason WordPress gave. */
				esc_html( sprintf( __( 'The new image could not be saved: %s', 'photos-sh' ), $new_id->get_error_message() ) )
			);
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $new_id, wp_generate_attachment_metadata( $new_id, $upload['file'] ) );

		update_post_meta( $new_id, self::META_SOURCE, $attachment_id );
		update_post_meta( $new_id, self::META_OPERATION, $operation->name );
		update_post_meta( $new_id, self::META_PARAMS, $operation->params() );
		if ( [] !== $details ) {
			update_post_meta( $new_id, self::META_RESULT, $details );
		}

		$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( is_string( $alt ) && '' !== $alt ) {
			update_post_meta( $new_id, '_wp_attachment_image_alt', wp_slash( $alt ) );
		}

		/**
		 * Fires after an image was processed and saved as a new attachment.
		 *
		 * @param int       $new_id        The new attachment.
		 * @param int       $attachment_id The source attachment.
		 * @param Operation $operation     What was done.
		 */
		do_action( 'photos_sh_processed', $new_id, $attachment_id, $operation );

		return $new_id;
	}

	/**
	 * What the API said was done to make a result attachment, in words for
	 * the editor; empty when it said nothing of the kind.
	 *
	 * @param int       $new_id    A result attachment.
	 * @param Operation $operation The operation that made it.
	 */
	public static function result_note( int $new_id, Operation $operation ): string {
		$details = get_post_meta( $new_id, self::META_RESULT, true );

		return is_array( $details ) ? $operation->result_note( $details ) : '';
	}

	/**
	 * Logs a failure of process(), or of writing alt text.
	 *
	 * @param \Throwable $error         What went wrong.
	 * @param int        $attachment_id The source attachment.
	 * @param Operation  $operation     What was being done.
	 */
	public function log_failure( \Throwable $error, int $attachment_id, Operation $operation ): void {
		ErrorMessages::log(
			$error,
			sprintf( '%s of attachment %d', $operation->key(), $attachment_id ),
			$this->settings->api_key()
		);
	}
}
