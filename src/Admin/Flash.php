<?php
/**
 * One-time admin notices.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps a notice for a user until the next admin page shows it (actions
 * redirect, so the result is shown one request later).
 */
final class Flash {

	private const TRANSIENT = 'photos_sh_flash_';

	/**
	 * Adds a notice for a user.
	 *
	 * @param int    $user       The user.
	 * @param string $type       `success`, `error`, `warning` or `info`.
	 * @param string $message    The text.
	 * @param string $link_url   An optional link shown after the text.
	 * @param string $link_label The link's text.
	 */
	public function add( int $user, string $type, string $message, string $link_url = '', string $link_label = '' ): void {
		$notices   = $this->get( $user );
		$notices[] = [
			'type'       => $type,
			'message'    => $message,
			'link_url'   => $link_url,
			'link_label' => $link_label,
		];
		set_transient( self::TRANSIENT . $user, $notices, HOUR_IN_SECONDS );
	}

	/**
	 * Prints a user's notices and forgets them.
	 *
	 * @param int $user The user.
	 */
	public function render( int $user ): void {
		$notices = $this->get( $user );
		if ( [] === $notices ) {
			return;
		}
		delete_transient( self::TRANSIENT . $user );

		foreach ( $notices as $notice ) {
			$type = in_array( $notice['type'], [ 'success', 'error', 'warning', 'info' ], true ) ? $notice['type'] : 'info';
			printf( '<div class="notice notice-%s is-dismissible"><p>%s', esc_attr( $type ), esc_html( $notice['message'] ) );
			if ( '' !== $notice['link_url'] ) {
				printf( ' <a href="%s">%s</a>', esc_url( $notice['link_url'] ), esc_html( $notice['link_label'] ) );
			}
			echo '</p></div>';
		}
	}

	/**
	 * A user's waiting notices.
	 *
	 * @param int $user The user.
	 * @return list<array{type: string, message: string, link_url: string, link_label: string}>
	 */
	private function get( int $user ): array {
		$stored  = get_transient( self::TRANSIENT . $user );
		$notices = [];
		foreach ( is_array( $stored ) ? $stored : [] as $notice ) {
			if ( is_array( $notice ) ) {
				$notices[] = [
					'type'       => (string) ( $notice['type'] ?? 'info' ),
					'message'    => (string) ( $notice['message'] ?? '' ),
					'link_url'   => (string) ( $notice['link_url'] ?? '' ),
					'link_label' => (string) ( $notice['link_label'] ?? '' ),
				];
			}
		}

		return $notices;
	}
}
