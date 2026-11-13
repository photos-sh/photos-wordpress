<?php
/**
 * The plugin's settings.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress;

use PhotosSh\Photos;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the API key and the base URL.
 *
 * The key comes from the constant or environment variable `PHOTOS_API_KEY`
 * when one is set, and from the settings page otherwise.
 */
final class Settings {

	public const OPTION = 'photos_sh_settings';

	public const SOURCE_CONSTANT    = 'constant';
	public const SOURCE_ENVIRONMENT = 'environment';
	public const SOURCE_OPTION      = 'option';
	public const SOURCE_NONE        = 'none';

	/**
	 * The API key, or an empty string when there is none.
	 */
	public function api_key(): string {
		return $this->locate_key()[1];
	}

	/**
	 * Where the key comes from: one of the SOURCE_* constants.
	 */
	public function api_key_source(): string {
		return $this->locate_key()[0];
	}

	/**
	 * Whether the key is set outside the settings page (and so cannot be
	 * changed there).
	 */
	public function is_key_locked(): bool {
		return in_array( $this->api_key_source(), [ self::SOURCE_CONSTANT, self::SOURCE_ENVIRONMENT ], true );
	}

	/**
	 * The key with all but its last four characters hidden, for display.
	 */
	public function masked_key(): string {
		$key = $this->api_key();
		if ( '' === $key ) {
			return '';
		}
		$dots = str_repeat( '•', 12 );

		return strlen( $key ) >= 12 ? $dots . substr( $key, -4 ) : $dots;
	}

	/**
	 * The API's base URL, without a trailing slash.
	 */
	public function base_url(): string {
		$stored = $this->stored();
		$url    = rtrim( trim( $stored['base_url'] ), '/' );

		return '' !== $url ? $url : Photos::DEFAULT_BASE_URL;
	}

	/**
	 * Whether the base URL is the default one.
	 */
	public function is_default_base_url(): bool {
		return Photos::DEFAULT_BASE_URL === $this->base_url();
	}

	/**
	 * Cleans the settings form's values before they are saved.
	 *
	 * An empty key field keeps the saved key (the form never shows it);
	 * the "remove" checkbox clears it.
	 *
	 * @param mixed $input The submitted values.
	 * @return array{api_key: string, base_url: string}
	 */
	public function sanitize( $input ): array {
		$stored = $this->stored();
		$input  = is_array( $input ) ? $input : [];

		$key = $stored['api_key'];
		if ( ! empty( $input['remove_key'] ) ) {
			$key = '';
		} elseif ( isset( $input['api_key'] ) && is_string( $input['api_key'] ) && '' !== trim( $input['api_key'] ) ) {
			$key = sanitize_text_field( $input['api_key'] );
		}

		$url = $stored['base_url'];
		if ( isset( $input['base_url'] ) && is_string( $input['base_url'] ) ) {
			$submitted = rtrim( trim( $input['base_url'] ), '/' );
			if ( '' === $submitted || Photos::DEFAULT_BASE_URL === $submitted ) {
				$url = '';
			} else {
				$clean = esc_url_raw( $submitted, [ 'http', 'https' ] );
				if ( '' !== $clean ) {
					$url = rtrim( $clean, '/' );
				} else {
					add_settings_error(
						self::OPTION,
						'photos_sh_base_url',
						__( 'The base URL must start with http:// or https://. The previous one was kept.', 'photos-sh' )
					);
				}
			}
		}

		return [
			'api_key'  => $key,
			'base_url' => $url,
		];
	}

	/**
	 * The saved option, with every field present.
	 *
	 * @return array{api_key: string, base_url: string}
	 */
	private function stored(): array {
		$option = get_option( self::OPTION, [] );
		$option = is_array( $option ) ? $option : [];

		return [
			'api_key'  => isset( $option['api_key'] ) && is_string( $option['api_key'] ) ? $option['api_key'] : '',
			'base_url' => isset( $option['base_url'] ) && is_string( $option['base_url'] ) ? $option['base_url'] : '',
		];
	}

	/**
	 * Finds the key: the constant first, then the environment, then the option.
	 *
	 * @return array{string, string} The source and the key.
	 */
	private function locate_key(): array {
		if ( defined( 'PHOTOS_API_KEY' ) ) {
			$constant = constant( 'PHOTOS_API_KEY' );
			if ( is_string( $constant ) && '' !== trim( $constant ) ) {
				return [ self::SOURCE_CONSTANT, trim( $constant ) ];
			}
		}

		$env = getenv( 'PHOTOS_API_KEY' );
		if ( is_string( $env ) && '' !== trim( $env ) ) {
			return [ self::SOURCE_ENVIRONMENT, trim( $env ) ];
		}

		$stored = trim( $this->stored()['api_key'] );
		if ( '' !== $stored ) {
			return [ self::SOURCE_OPTION, $stored ];
		}

		return [ self::SOURCE_NONE, '' ];
	}
}
