<?php
/**
 * Builds the API client.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress;

use PhotosSh\Photos;
use PhotosSh\Transport\Transport;

defined( 'ABSPATH' ) || exit;

/**
 * Makes `PhotosSh\Photos` clients from the plugin's settings.
 */
final class ClientFactory {

	/**
	 * Seconds allowed for one image request: the library's own default.
	 */
	public const TIMEOUT = 120.0;

	/**
	 * Seconds allowed for the account request on the settings page.
	 */
	public const ACCOUNT_TIMEOUT = 10.0;

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * The HTTP layer.
	 *
	 * @var Transport
	 */
	private Transport $transport;

	/**
	 * Sets the factory up.
	 *
	 * @param Settings       $settings  The settings.
	 * @param Transport|null $transport The HTTP layer; `wp_remote_request()` by default.
	 */
	public function __construct( Settings $settings, ?Transport $transport = null ) {
		$this->settings  = $settings;
		$this->transport = $transport ?? new WpTransport();
	}

	/**
	 * A client for the configured account.
	 *
	 * @param int   $max_retries Extra attempts the library makes itself (waiting in
	 *                           between) after a busy answer or a failed connection.
	 * @param float $timeout     Seconds allowed for one request.
	 * @throws NotConfiguredException When there is no API key.
	 */
	public function create( int $max_retries = 0, float $timeout = self::TIMEOUT ): Photos {
		$key = $this->settings->api_key();
		if ( '' === $key ) {
			throw new NotConfiguredException( 'No Photos.sh API key is set.' );
		}

		return new Photos(
			$key,
			$this->settings->base_url(),
			$timeout,
			$max_retries,
			$this->transport
		);
	}
}
