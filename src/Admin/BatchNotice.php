<?php
/**
 * The progress notice of bulk actions.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress\Admin;

use PhotosSh\WordPress\AltText;
use PhotosSh\WordPress\Operation;
use PhotosSh\WordPress\Queue\Batches;
use PhotosSh\WordPress\Queue\Task;

defined( 'ABSPATH' ) || exit;

/**
 * Shows the user's bulk actions: how many images are done, which failed
 * and why. A small script refreshes the notice while images are waiting.
 */
final class BatchNotice {

	public const AJAX_ACTION    = 'photos_sh_progress';
	public const DISMISS_ACTION = 'photos_sh_dismiss';

	/**
	 * The batches.
	 *
	 * @var Batches
	 */
	private Batches $batches;

	/**
	 * The main plugin file.
	 *
	 * @var string
	 */
	private string $plugin_file;

	/**
	 * Sets the notice up.
	 *
	 * @param Batches $batches     The batches.
	 * @param string  $plugin_file The main plugin file.
	 */
	public function __construct( Batches $batches, string $plugin_file ) {
		$this->batches     = $batches;
		$this->plugin_file = $plugin_file;
	}

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_action( 'admin_notices', [ $this, 'render' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'ajax' ] );
		add_action( 'admin_post_' . self::DISMISS_ACTION, [ $this, 'dismiss' ] );
	}

	/**
	 * Prints the notice on the media and product screens.
	 */
	public function render(): void {
		if ( ! $this->on_our_screen() ) {
			return;
		}
		$state = $this->state();
		if ( '' === $state['html'] ) {
			return;
		}

		printf(
			'<div id="photos-sh-progress" class="notice notice-%s" data-pending="%d">%s</div>',
			esc_attr( $state['type'] ),
			(int) $state['pending'],
			wp_kses_post( $state['html'] )
		);
	}

	/**
	 * Loads the refresh script while images are waiting.
	 */
	public function enqueue(): void {
		if ( ! $this->on_our_screen() || 0 === $this->state()['pending'] ) {
			return;
		}

		wp_enqueue_script(
			'photos-sh-progress',
			plugins_url( 'assets/progress.js', $this->plugin_file ),
			[],
			\PhotosSh\WordPress\Plugin::VERSION,
			true
		);
		wp_add_inline_script(
			'photos-sh-progress',
			'window.photosShProgress = ' . wp_json_encode(
				[
					'url' => add_query_arg(
						[
							'action'   => self::AJAX_ACTION,
							'_wpnonce' => wp_create_nonce( self::AJAX_ACTION ),
						],
						admin_url( 'admin-ajax.php' )
					),
				]
			) . ';',
			'before'
		);
	}

	/**
	 * Answers the refresh script with the notice's current state.
	 */
	public function ajax(): void {
		check_ajax_referer( self::AJAX_ACTION );
		if ( ! $this->may_see() ) {
			wp_send_json_error( null, 403 );
		}

		$state         = $this->state();
		$state['html'] = wp_kses_post( $state['html'] );
		wp_send_json_success( $state );
	}

	/**
	 * Forgets a batch when its "Dismiss" link is followed.
	 */
	public function dismiss(): void {
		$batch = isset( $_GET['batch'] ) ? sanitize_key( wp_unslash( $_GET['batch'] ) ) : '';
		check_admin_referer( self::DISMISS_ACTION . '_' . $batch );
		if ( ! $this->may_see() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'photos-sh' ), '', [ 'response' => 403 ] );
		}

		$this->batches->dismiss( $batch, get_current_user_id() );

		$back = wp_get_referer();
		wp_safe_redirect( false !== $back ? $back : admin_url( 'upload.php' ) );
		exit;
	}

	/**
	 * The notice's content for the current user.
	 *
	 * @return array{html: string, pending: int, type: string}
	 */
	public function state(): array {
		$html    = '';
		$pending = 0;
		$failed  = 0;

		foreach ( $this->batches->for_user( get_current_user_id() ) as $id => $batch ) {
			$pending += $batch['pending'];
			$failed  += $batch['failed'];
			$html    .= $this->batch_html( (string) $id, $batch );
		}

		$type = 'success';
		if ( $pending > 0 ) {
			$type = 'info';
		} elseif ( $failed > 0 ) {
			$type = 'warning';
		}

		return [
			'html'    => $html,
			'pending' => $pending,
			'type'    => $type,
		];
	}

	/**
	 * One batch as HTML.
	 *
	 * @param string                                                                                                                                                    $id    The batch's ID.
	 * @param array{operation: string, kind: string, total: int, done: int, failed: int, skipped: int, pending: int, failures: list<array{item: int, message: string}>} $batch Its progress.
	 */
	private function batch_html( string $id, array $batch ): string {
		$operation = Operation::from_key( $batch['operation'] );
		$label     = null !== $operation ? $operation->label() : $batch['operation'];
		if ( Task::KIND_PRODUCT === $batch['kind'] ) {
			$label = __( 'Remove background from product images', 'photos-sh' );
		}

		$finished = $batch['total'] - $batch['pending'];
		if ( $batch['pending'] > 0 ) {
			/* translators: 1: the action's name, 2: images finished, 3: images in total. */
			$line = sprintf( __( 'Photos.sh, %1$s: %2$d of %3$d done, the rest is waiting.', 'photos-sh' ), $label, $finished, $batch['total'] );
		} else {
			/* translators: 1: the action's name, 2: images in total. */
			$line = sprintf( __( 'Photos.sh, %1$s: finished, %2$d in total.', 'photos-sh' ), $label, $batch['total'] );
		}
		if ( $batch['failed'] > 0 ) {
			/* translators: %d: a number of images. */
			$line .= ' ' . sprintf( _n( '%d failed.', '%d failed.', $batch['failed'], 'photos-sh' ), $batch['failed'] );
		}
		if ( $batch['skipped'] > 0 ) {
			/* translators: %d: a number of images. */
			$line .= ' ' . sprintf( _n( '%d skipped.', '%d skipped.', $batch['skipped'], 'photos-sh' ), $batch['skipped'] );
		}

		$dismiss = wp_nonce_url(
			add_query_arg(
				[
					'action' => self::DISMISS_ACTION,
					'batch'  => $id,
				],
				admin_url( 'admin-post.php' )
			),
			self::DISMISS_ACTION . '_' . $id
		);

		$html = sprintf(
			'<p><strong>%s</strong> <a href="%s">%s</a></p>',
			esc_html( $line ),
			esc_url( $dismiss ),
			esc_html__( 'Dismiss', 'photos-sh' )
		);

		if ( null !== $operation && ! $operation->makes_image() ) {
			$html .= sprintf( '<p>%s</p>', esc_html( AltText::notice() ) );
		}

		if ( [] !== $batch['failures'] ) {
			$html .= '<ul>';
			foreach ( $batch['failures'] as $failure ) {
				$title = get_the_title( $failure['item'] );
				$html .= sprintf(
					'<li>%s: %s</li>',
					esc_html( '' !== $title ? $title : '#' . $failure['item'] ),
					esc_html( $failure['message'] )
				);
			}
			$html .= '</ul>';
		}

		return $html;
	}

	/**
	 * Whether the current screen is one the notice belongs on.
	 */
	private function on_our_screen(): bool {
		if ( ! $this->may_see() || ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();

		return null !== $screen && in_array( $screen->id, [ 'upload', 'attachment', 'edit-product', 'settings_page_photos-sh' ], true );
	}

	/**
	 * Whether the current user can start bulk actions at all.
	 */
	private function may_see(): bool {
		return current_user_can( 'upload_files' ) || current_user_can( 'edit_products' );
	}
}
