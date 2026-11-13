<?php
/**
 * Actions in the media library.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress\Admin;

use PhotosSh\WordPress\AltText;
use PhotosSh\WordPress\ErrorMessages;
use PhotosSh\WordPress\Operation;
use PhotosSh\WordPress\Processor;
use PhotosSh\WordPress\Queue\Batches;
use PhotosSh\WordPress\Queue\Scheduler;
use PhotosSh\WordPress\Queue\Task;
use PhotosSh\WordPress\Settings;
use PhotosSh\WordPress\SkippedException;

defined( 'ABSPATH' ) || exit;

/**
 * The actions on one image (row actions in the list, a box on the edit
 * screen) and the bulk actions of the media list.
 *
 * Every action makes a new image, except "write alt text", which sets the
 * image's alternative text.
 */
final class MediaActions {

	public const ACTION      = 'photos_sh_process';
	public const BULK_PREFIX = 'photos_sh_';

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
	 * The settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Schedules bulk tasks.
	 *
	 * @var Scheduler
	 */
	private Scheduler $scheduler;

	/**
	 * The batches.
	 *
	 * @var Batches
	 */
	private Batches $batches;

	/**
	 * One-time notices.
	 *
	 * @var Flash
	 */
	private Flash $flash;

	/**
	 * Sets the actions up.
	 *
	 * @param Processor $processor Processes attachments.
	 * @param AltText   $alt_text  Writes alternative text.
	 * @param Settings  $settings  The settings.
	 * @param Scheduler $scheduler Schedules bulk tasks.
	 * @param Batches   $batches   The batches.
	 * @param Flash     $flash     One-time notices.
	 */
	public function __construct( Processor $processor, AltText $alt_text, Settings $settings, Scheduler $scheduler, Batches $batches, Flash $flash ) {
		$this->processor = $processor;
		$this->alt_text  = $alt_text;
		$this->settings  = $settings;
		$this->scheduler = $scheduler;
		$this->batches   = $batches;
		$this->flash     = $flash;
	}

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_filter( 'media_row_actions', [ $this, 'row_actions' ], 10, 2 );
		add_action( 'add_meta_boxes_attachment', [ $this, 'add_meta_box' ] );
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle_single' ] );
		add_filter( 'bulk_actions-upload', [ $this, 'bulk_actions' ] );
		add_filter( 'handle_bulk_actions-upload', [ $this, 'handle_bulk' ], 10, 3 );
		add_action( 'admin_notices', [ $this, 'render_flash' ] );
	}

	/**
	 * Adds the actions under an image in the media list.
	 *
	 * @param mixed $actions The row's actions.
	 * @param mixed $post    The attachment.
	 * @return mixed
	 */
	public function row_actions( $actions, $post ) {
		if ( ! is_array( $actions ) || ! $post instanceof \WP_Post || ! $this->may_process( $post->ID ) ) {
			return $actions;
		}

		foreach ( Operation::all() as $operation ) {
			if ( Operation::FIX === $operation->name || ! $operation->makes_image() ) {
				continue;
			}
			// The caution shows when the pointer rests on the link.
			$actions[ self::BULK_PREFIX . $operation->key() ] = sprintf(
				'<a href="%s" title="%s">%s</a>',
				esc_url( $this->action_url( $post->ID, $operation ) ),
				esc_attr( $operation->caution() ),
				esc_html( $operation->label() )
			);
		}

		// One link for all marketplaces: the edit screen has the list to pick from.
		$actions[ self::BULK_PREFIX . 'fix' ] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( get_edit_post_link( $post->ID, 'raw' ) . '#photos-sh' ),
			esc_html__( 'Fix for a marketplace…', 'photos-sh' )
		);

		if ( '' === AltText::current( $post->ID ) ) {
			$actions[ self::BULK_PREFIX . Operation::ALT_TEXT ] = sprintf(
				'<a href="%s" title="%s">%s</a>',
				esc_url( $this->action_url( $post->ID, Operation::alt_text() ) ),
				esc_attr( AltText::notice() ),
				esc_html( Operation::alt_text()->label() )
			);
		} else {
			// Replacing text an editor wrote is decided on the edit screen, which shows that text.
			$actions[ self::BULK_PREFIX . Operation::ALT_TEXT ] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( get_edit_post_link( $post->ID, 'raw' ) . '#photos-sh' ),
				esc_html__( 'Replace alt text…', 'photos-sh' )
			);
		}

		return $actions;
	}

	/**
	 * Adds the Photos.sh box to the attachment edit screen.
	 *
	 * @param mixed $post The attachment.
	 */
	public function add_meta_box( $post ): void {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$is_result = '' !== (string) get_post_meta( $post->ID, Processor::META_OPERATION, true );
		if ( ! $is_result && ! $this->may_process( $post->ID ) ) {
			return;
		}

		add_meta_box( 'photos-sh', __( 'Photos.sh', 'photos-sh' ), [ $this, 'render_meta_box' ], 'attachment', 'side' );
	}

	/**
	 * Prints the box: where the image came from, and the actions on it.
	 *
	 * @param mixed $post The attachment.
	 */
	public function render_meta_box( $post ): void {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$this->render_origin( $post->ID );

		if ( ! $this->may_process( $post->ID ) ) {
			return;
		}

		echo '<p>' . esc_html__( 'The result is saved as a new image. This one is not changed.', 'photos-sh' ) . '</p>';
		echo '<p>';
		$cautions = [];
		foreach ( Operation::all() as $operation ) {
			if ( Operation::FIX === $operation->name || ! $operation->makes_image() ) {
				continue;
			}
			printf(
				'<a class="button" style="margin:0 4px 4px 0" href="%s">%s</a>',
				esc_url( $this->action_url( $post->ID, $operation ) ),
				esc_html( $operation->label() )
			);
			if ( '' !== $operation->caution() ) {
				$cautions[] = $operation->caution();
			}
		}
		echo '</p>';
		foreach ( $cautions as $caution ) {
			echo '<p class="description">' . esc_html( $caution ) . '</p>';
		}

		echo '<p><strong>' . esc_html__( 'Fix for a marketplace', 'photos-sh' ) . '</strong></p><p>';
		foreach ( Operation::all() as $operation ) {
			if ( Operation::FIX !== $operation->name ) {
				continue;
			}
			printf(
				'<a class="button" style="margin:0 4px 4px 0" href="%s">%s</a>',
				esc_url( $this->action_url( $post->ID, $operation ) ),
				esc_html( $operation->marketplace_title() )
			);
		}
		echo '</p>';

		$this->render_alt_text( $post->ID );
	}

	/**
	 * Prints the alt text part of the box: one button, to write the text
	 * or, when the image has text, to replace it.
	 *
	 * @param int $attachment_id The attachment.
	 */
	private function render_alt_text( int $attachment_id ): void {
		$has_text  = '' !== AltText::current( $attachment_id );
		$operation = Operation::alt_text( $has_text );

		echo '<p><strong>' . esc_html__( 'Alternative text', 'photos-sh' ) . '</strong></p>';
		echo '<p>' . esc_html( AltText::notice() ) . '</p>';
		if ( $has_text ) {
			echo '<p>' . esc_html__( 'This image already has alternative text. Replacing it cannot be undone.', 'photos-sh' ) . '</p>';
		}
		printf(
			'<p><a class="button" href="%s">%s</a></p>',
			esc_url( $this->action_url( $attachment_id, $operation ) ),
			esc_html( $operation->label() )
		);
	}

	/**
	 * Runs one operation on one image, then goes back with a notice.
	 */
	public function handle_single(): void {
		$attachment_id = isset( $_GET['attachment'] ) ? absint( wp_unslash( $_GET['attachment'] ) ) : 0;
		check_admin_referer( self::ACTION . '_' . $attachment_id );

		if ( ! $this->may_process( $attachment_id ) ) {
			wp_die( esc_html__( 'You are not allowed to edit this image.', 'photos-sh' ), '', [ 'response' => 403 ] );
		}

		$key       = isset( $_GET['operation'] ) ? sanitize_text_field( wp_unslash( $_GET['operation'] ) ) : '';
		$operation = Operation::from_key( $key );
		$user      = get_current_user_id();

		if ( null === $operation ) {
			$this->flash->add( $user, 'error', __( 'Unknown Photos.sh action.', 'photos-sh' ) );
		} else {
			try {
				if ( $operation->makes_image() ) {
					$this->process( $attachment_id, $operation, $user );
				} else {
					$this->write_alt_text( $attachment_id, $operation, $user );
				}
			} catch ( SkippedException $skipped ) {
				$this->flash->add(
					$user,
					'warning',
					/* translators: 1: the action's name, 2: why nothing was done, as a sentence. */
					sprintf( __( '%1$s: %2$s', 'photos-sh' ), $operation->label(), $skipped->getMessage() )
				);
			} catch ( \Throwable $error ) {
				$this->processor->log_failure( $error, $attachment_id, $operation );
				$this->flash->add(
					$user,
					'error',
					/* translators: 1: the action's name, 2: why it failed. */
					sprintf( __( '%1$s failed. %2$s', 'photos-sh' ), $operation->label(), ErrorMessages::describe( $error ) )
				);
			}
		}

		$back = wp_get_referer();
		wp_safe_redirect( false !== $back ? $back : admin_url( 'upload.php' ) );
		exit;
	}

	/**
	 * Makes the new image, and says so in a notice: what was done, how many
	 * faces the API found, and the operation's caution.
	 *
	 * @param int       $attachment_id The source attachment.
	 * @param Operation $operation     What to do.
	 * @param int       $user          The user to tell.
	 * @throws \Throwable When it failed, or nothing was done.
	 */
	private function process( int $attachment_id, Operation $operation, int $user ): void {
		// One retry by the library: an editor is waiting for this page.
		$new_id = $this->processor->process( $attachment_id, $operation, 1 );

		/* translators: 1: the action's name, 2: the new image's title. */
		$message = sprintf( __( '%1$s: saved as a new image, "%2$s".', 'photos-sh' ), $operation->label(), get_the_title( $new_id ) );
		$note    = Processor::result_note( $new_id, $operation );
		if ( '' !== $note ) {
			$message .= ' ' . $note;
		}
		// How slow an operation is no longer matters once its image is made.
		if ( '' !== $operation->caution() && Operation::DENOISE !== $operation->name ) {
			$message .= ' ' . $operation->caution();
		}

		$this->flash->add( $user, 'success', $message, (string) get_edit_post_link( $new_id, 'raw' ), __( 'Open it', 'photos-sh' ) );
	}

	/**
	 * Writes the image's alternative text, and shows it in a notice.
	 *
	 * @param int       $attachment_id The attachment.
	 * @param Operation $operation     Write alt text, keeping or replacing what is there.
	 * @param int       $user          The user to tell.
	 * @throws \Throwable When it failed, or the image has text that is to be kept.
	 */
	private function write_alt_text( int $attachment_id, Operation $operation, int $user ): void {
		// One retry by the library: an editor is waiting for this page.
		$text = $this->alt_text->write( $attachment_id, $operation->replace, 1 );

		$this->flash->add(
			$user,
			'success',
			/* translators: 1: the action's name, 2: the new alternative text, a sentence with its full stop. */
			sprintf( __( '%1$s: the alternative text is now "%2$s"', 'photos-sh' ), $operation->label(), $text ) . ' ' . AltText::notice(),
			(string) get_edit_post_link( $attachment_id, 'raw' ),
			__( 'Edit it', 'photos-sh' )
		);
	}

	/**
	 * Adds the operations to the media list's bulk actions, as one group.
	 *
	 * @param mixed $actions The bulk actions.
	 * @return mixed
	 */
	public function bulk_actions( $actions ) {
		if ( ! is_array( $actions ) || ! current_user_can( 'upload_files' ) ) {
			return $actions;
		}

		$group = [];
		foreach ( Operation::all() as $operation ) {
			$group[ self::BULK_PREFIX . $operation->key() ] = $operation->bulk_label();
		}
		$actions[ __( 'Photos.sh', 'photos-sh' ) ] = $group;

		return $actions;
	}

	/**
	 * Queues the selected images, one task each. WordPress has checked the
	 * list's nonce before it calls this.
	 *
	 * @param mixed $location Where WordPress goes next.
	 * @param mixed $action   The chosen bulk action.
	 * @param mixed $ids      The selected attachments.
	 * @return mixed
	 */
	public function handle_bulk( $location, $action, $ids ) {
		if ( ! is_string( $action ) || ! str_starts_with( $action, self::BULK_PREFIX ) ) {
			return $location;
		}
		$operation = Operation::from_key( substr( $action, strlen( self::BULK_PREFIX ) ) );
		if ( null === $operation || ! current_user_can( 'upload_files' ) ) {
			return $location;
		}

		$user = get_current_user_id();
		if ( '' === $this->settings->api_key() ) {
			$this->flash->add( $user, 'error', __( 'Photos.sh has no API key yet. Add one under Settings → Photos.sh.', 'photos-sh' ) );

			return $location;
		}

		$items = [];
		foreach ( is_array( $ids ) ? $ids : [] as $id ) {
			$id = absint( $id );
			if ( $id > 0 && $this->may_process( $id ) ) {
				$items[] = $id;
			}
		}
		$items = array_values( array_unique( $items ) );
		if ( [] === $items ) {
			$this->flash->add( $user, 'warning', __( 'None of the selected items is an image you can edit.', 'photos-sh' ) );

			return $location;
		}

		$batch = $this->batches->create( $user, $operation->key(), Task::KIND_ATTACHMENT, $items );
		foreach ( $items as $item ) {
			$this->scheduler->schedule( new Task( $batch, Task::KIND_ATTACHMENT, $item, $operation->key(), $user ) );
		}

		return $location;
	}

	/**
	 * Prints the current user's one-time notices.
	 */
	public function render_flash(): void {
		$this->flash->render( get_current_user_id() );
	}

	/**
	 * Prints what an image was made from, when the plugin made it.
	 *
	 * @param int $attachment_id The attachment.
	 */
	private function render_origin( int $attachment_id ): void {
		$name = (string) get_post_meta( $attachment_id, Processor::META_OPERATION, true );
		if ( '' === $name ) {
			return;
		}

		$params = get_post_meta( $attachment_id, Processor::META_PARAMS, true );
		$params = is_array( $params ) ? $params : [];
		$key    = $name;
		if ( isset( $params['scale'] ) ) {
			$key .= '-' . (int) $params['scale'];
		} elseif ( isset( $params['marketplace'] ) ) {
			$key .= '-' . (string) $params['marketplace'];
		}
		$operation = Operation::from_key( $key );
		$source    = (int) get_post_meta( $attachment_id, Processor::META_SOURCE, true );
		$link      = $source > 0 ? get_edit_post_link( $source, 'raw' ) : null;

		echo '<p>';
		/* translators: %s: what was done, such as "Remove background". */
		echo esc_html( sprintf( __( 'Made with "%s".', 'photos-sh' ), null !== $operation ? $operation->label() : $name ) );
		if ( is_string( $link ) && '' !== $link && null !== get_post( $source ) ) {
			printf( ' <a href="%s">%s</a>', esc_url( $link ), esc_html__( 'Open the original', 'photos-sh' ) );
		}
		echo '</p>';

		$note = null !== $operation ? Processor::result_note( $attachment_id, $operation ) : '';
		if ( '' !== $note ) {
			echo '<p>' . esc_html( $note ) . '</p>';
		}

		$result = get_post_meta( $attachment_id, Processor::META_RESULT, true );
		if ( is_array( $result ) && isset( $result['passed'] ) ) {
			echo '<p>';
			if ( $result['passed'] ) {
				esc_html_e( 'It passes the marketplace\'s image rules.', 'photos-sh' );
			} else {
				$findings = isset( $result['findings'] ) && is_array( $result['findings'] ) ? implode( ', ', array_map( 'strval', $result['findings'] ) ) : '';
				/* translators: %s: a list of rule codes, such as "min_longest, no_text". */
				echo esc_html( sprintf( __( 'It still breaks these marketplace rules: %s', 'photos-sh' ), $findings ) );
			}
			echo '</p>';
		}
	}

	/**
	 * The link that runs an operation on an image.
	 *
	 * @param int       $attachment_id The attachment.
	 * @param Operation $operation     What to do.
	 */
	private function action_url( int $attachment_id, Operation $operation ): string {
		return wp_nonce_url(
			add_query_arg(
				[
					'action'     => self::ACTION,
					'attachment' => $attachment_id,
					'operation'  => $operation->key(),
				],
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '_' . $attachment_id
		);
	}

	/**
	 * Whether the current user may run an operation on an attachment.
	 *
	 * @param int $attachment_id The attachment.
	 */
	private function may_process( int $attachment_id ): bool {
		return $attachment_id > 0
			&& current_user_can( 'upload_files' )
			&& current_user_can( 'edit_post', $attachment_id )
			&& wp_attachment_is_image( $attachment_id );
	}
}
