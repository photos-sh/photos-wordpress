<?php
/**
 * Settings → Photos.sh.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress\Admin;

use PhotosSh\Photos;
use PhotosSh\WordPress\ClientFactory;
use PhotosSh\WordPress\ErrorMessages;
use PhotosSh\WordPress\Operation;
use PhotosSh\WordPress\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * The settings page: the API key, the connected account, background
 * removal's "Studio shots" and "Model", the base URL.
 */
final class SettingsPage {

	public const SLUG  = 'photos-sh';
	public const GROUP = 'photos_sh';

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Makes the API clients.
	 *
	 * @var ClientFactory
	 */
	private ClientFactory $clients;

	/**
	 * The main plugin file.
	 *
	 * @var string
	 */
	private string $plugin_file;

	/**
	 * Sets the page up.
	 *
	 * @param Settings      $settings    The settings.
	 * @param ClientFactory $clients     Makes the API clients.
	 * @param string        $plugin_file The main plugin file.
	 */
	public function __construct( Settings $settings, ClientFactory $clients, string $plugin_file ) {
		$this->settings    = $settings;
		$this->clients     = $clients;
		$this->plugin_file = $plugin_file;
	}

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( $this->plugin_file ), [ $this, 'action_links' ] );
	}

	/**
	 * Adds the page under Settings.
	 */
	public function add_page(): void {
		add_options_page(
			__( 'Photos.sh', 'photos-sh' ),
			__( 'Photos.sh', 'photos-sh' ),
			'manage_options',
			self::SLUG,
			[ $this, 'render' ]
		);
	}

	/**
	 * Registers the option, its sections and fields.
	 */
	public function register_settings(): void {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this->settings, 'sanitize' ],
				'default'           => [],
				'show_in_rest'      => false,
			]
		);

		add_settings_section( 'photos_sh_account', '', '__return_empty_string', self::SLUG );
		add_settings_field(
			'photos_sh_api_key',
			__( 'API key', 'photos-sh' ),
			[ $this, 'render_key_field' ],
			self::SLUG,
			'photos_sh_account',
			[ 'label_for' => 'photos-sh-api-key' ]
		);
		add_settings_field(
			'photos_sh_status',
			__( 'Status', 'photos-sh' ),
			[ $this, 'render_status' ],
			self::SLUG,
			'photos_sh_account'
		);

		add_settings_section( 'photos_sh_remove_background', __( 'Remove background', 'photos-sh' ), '__return_empty_string', self::SLUG );
		add_settings_field(
			'photos_sh_studio',
			__( 'Studio shots', 'photos-sh' ),
			[ $this, 'render_studio_field' ],
			self::SLUG,
			'photos_sh_remove_background',
			[ 'label_for' => 'photos-sh-studio' ]
		);
		add_settings_field(
			'photos_sh_model',
			__( 'Model', 'photos-sh' ),
			[ $this, 'render_model_field' ],
			self::SLUG,
			'photos_sh_remove_background',
			[ 'label_for' => 'photos-sh-model' ]
		);

		add_settings_section( 'photos_sh_advanced', __( 'Advanced', 'photos-sh' ), '__return_empty_string', self::SLUG );
		add_settings_field(
			'photos_sh_base_url',
			__( 'Base URL', 'photos-sh' ),
			[ $this, 'render_base_url_field' ],
			self::SLUG,
			'photos_sh_advanced',
			[ 'label_for' => 'photos-sh-base-url' ]
		);
	}

	/**
	 * Adds a "Settings" link to the plugin's row on the Plugins screen.
	 *
	 * @param mixed $links The row's links.
	 * @return mixed
	 */
	public function action_links( $links ) {
		if ( is_array( $links ) && current_user_can( 'manage_options' ) ) {
			array_unshift(
				$links,
				sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ), esc_html__( 'Settings', 'photos-sh' ) )
			);
		}

		return $links;
	}

	/**
	 * Prints the page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		echo '<form action="options.php" method="post">';
		settings_fields( self::GROUP );
		do_settings_sections( self::SLUG );
		submit_button();
		echo '</form></div>';
	}

	/**
	 * Prints the API key field. The saved key is never printed in full.
	 */
	public function render_key_field(): void {
		$masked = $this->settings->masked_key();

		if ( $this->settings->is_key_locked() ) {
			printf(
				'<input type="text" id="photos-sh-api-key" class="regular-text" value="%s" readonly disabled />',
				esc_attr( $masked )
			);
			echo '<p class="description">';
			if ( Settings::SOURCE_CONSTANT === $this->settings->api_key_source() ) {
				esc_html_e( 'The key is set by the PHOTOS_API_KEY constant (in wp-config.php) and cannot be changed here.', 'photos-sh' );
			} else {
				esc_html_e( 'The key is set by the PHOTOS_API_KEY environment variable and cannot be changed here.', 'photos-sh' );
			}
			echo '</p>';

			return;
		}

		printf(
			'<input type="password" id="photos-sh-api-key" name="%s[api_key]" class="regular-text" value="" autocomplete="off" spellcheck="false" placeholder="%s" />',
			esc_attr( Settings::OPTION ),
			esc_attr( $masked )
		);
		echo '<p class="description">';
		if ( '' !== $masked ) {
			esc_html_e( 'A key is saved. Leave the field empty to keep it, or type a new one to replace it.', 'photos-sh' );
		} else {
			esc_html_e( 'Create an API key on your organisation\'s API keys page in the Photos.sh console.', 'photos-sh' );
		}
		echo '</p>';

		if ( '' !== $masked ) {
			printf(
				'<p><label><input type="checkbox" name="%s[remove_key]" value="1" /> %s</label></p>',
				esc_attr( Settings::OPTION ),
				esc_html__( 'Remove the saved key', 'photos-sh' )
			);
		}
	}

	/**
	 * Prints the "connected" line: the account and the credits left.
	 */
	public function render_status(): void {
		if ( '' === $this->settings->api_key() ) {
			echo '<p>' . esc_html__( 'Not connected: there is no API key yet.', 'photos-sh' ) . '</p>';

			return;
		}

		try {
			$account = $this->clients->create( 0, ClientFactory::ACCOUNT_TIMEOUT )->account();
		} catch ( \Throwable $error ) {
			ErrorMessages::log( $error, 'account', $this->settings->api_key() );
			/* translators: %s: why the connection failed. */
			echo '<p>' . esc_html( sprintf( __( 'Not connected. %s', 'photos-sh' ), ErrorMessages::describe( $error ) ) ) . '</p>';

			return;
		}

		echo '<p id="photos-sh-account">' . esc_html( self::account_line( $account ) ) . '</p>';
	}

	/**
	 * The "connected" line for an account.
	 *
	 * @param array<string, mixed> $account What the API's account call returned.
	 */
	public static function account_line( array $account ): string {
		$name = isset( $account['account'] ) && is_scalar( $account['account'] ) ? (string) $account['account'] : '';

		if ( ! empty( $account['unlimited'] ) ) {
			return '' !== $name
				/* translators: %s: the account's name. */
				? sprintf( __( 'Connected to the account "%s": unlimited use.', 'photos-sh' ), $name )
				: __( 'Connected: unlimited use.', 'photos-sh' );
		}

		if ( isset( $account['credits'] ) && is_numeric( $account['credits'] ) ) {
			$credits = (int) $account['credits'];
			$left    = sprintf(
				/* translators: %s: a number of credits. */
				_n( '%s credit left', '%s credits left', $credits, 'photos-sh' ),
				number_format_i18n( $credits )
			);

			return '' !== $name
				/* translators: 1: the account's name, 2: "12 credits left". */
				? sprintf( __( 'Connected to the account "%1$s": %2$s.', 'photos-sh' ), $name, $left )
				/* translators: %s: "12 credits left". */
				: sprintf( __( 'Connected: %s.', 'photos-sh' ), $left );
		}

		return '' !== $name
			/* translators: %s: the account's name. */
			? sprintf( __( 'Connected to the account "%s".', 'photos-sh' ), $name )
			: __( 'Connected.', 'photos-sh' );
	}

	/**
	 * Prints the "Studio shots" checkbox.
	 */
	public function render_studio_field(): void {
		printf(
			'<label><input type="checkbox" id="photos-sh-studio" name="%s[studio]" value="1" %s /> %s</label>',
			esc_attr( Settings::OPTION ),
			checked( $this->settings->studio(), true, false ),
			esc_html__( 'Treat product photos as studio shots', 'photos-sh' )
		);
		echo '<p class="description">';
		esc_html_e( 'Your product photos are taken on a plain light backdrop (a light tent): background removal also keeps the box, case or lid shown with the product.', 'photos-sh' );
		echo '</p>';
	}

	/**
	 * Prints the "Model" select, with what each model is for.
	 */
	public function render_model_field(): void {
		printf( '<select id="photos-sh-model" name="%s[model]">', esc_attr( Settings::OPTION ) );
		foreach ( Operation::MODELS as $model => $title ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $model ),
				selected( $this->settings->model(), $model, false ),
				esc_html(
					Operation::DEFAULT_MODEL === $model
						/* translators: %s: a model's name, such as Atlas. */
						? sprintf( __( '%s (default)', 'photos-sh' ), $title )
						: $title
				)
			);
		}
		echo '</select>';
		echo '<p class="description">';
		esc_html_e( 'The model that finds the subject. Each costs the same; try another when one drops part of the product.', 'photos-sh' );
		echo '</p>';
		foreach ( Operation::MODELS as $model => $title ) {
			printf(
				'<p class="description"><strong>%s</strong>: %s</p>',
				esc_html( $title ),
				esc_html( Operation::model_description( $model ) )
			);
		}
	}

	/**
	 * Prints the base URL field.
	 */
	public function render_base_url_field(): void {
		printf(
			'<input type="url" id="photos-sh-base-url" name="%s[base_url]" class="regular-text code" value="%s" placeholder="%s" />',
			esc_attr( Settings::OPTION ),
			esc_attr( $this->settings->is_default_base_url() ? '' : $this->settings->base_url() ),
			esc_attr( Photos::DEFAULT_BASE_URL )
		);
		echo '<p class="description">';
		/* translators: %s: the default URL. */
		echo esc_html( sprintf( __( 'Where the Photos.sh API is. Leave empty for %s.', 'photos-sh' ), Photos::DEFAULT_BASE_URL ) );
		echo '</p>';
	}
}
