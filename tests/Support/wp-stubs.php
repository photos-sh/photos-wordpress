<?php

/**
 * The few WordPress functions the unit-tested classes call, in their
 * simplest form. Tests set what they answer through WpStubs.
 */

declare(strict_types=1);

use PhotosSh\WordPress\Tests\Support\WpStubs;

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct(private string $code = '', private string $message = '') {}

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }
    }
}

if (!class_exists('WP_Post')) {
    class WP_Post
    {
        public function __construct(
            public int $ID = 0,
            public string $post_type = 'attachment',
            public string $post_mime_type = 'image/jpeg',
            public string $post_title = '',
            public string $post_excerpt = '',
            public int $post_parent = 0,
        ) {}
    }
}

function __(string $text, string $domain = 'default'): string
{
    return $text;
}

function _n(string $single, string $plural, int $number, string $domain = 'default'): string
{
    return $number === 1 ? $single : $plural;
}

function esc_html__(string $text, string $domain = 'default'): string
{
    return $text;
}

function esc_html(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8', false);
}

function number_format_i18n(float|int $number, int $decimals = 0): string
{
    return number_format((float) $number, $decimals);
}

function is_wp_error(mixed $thing): bool
{
    return $thing instanceof WP_Error;
}

/**
 * @param array<string, mixed> $args
 *
 * @return array<string, mixed>|WP_Error
 */
function wp_remote_request(string $url, array $args = []): array|WP_Error
{
    WpStubs::$requests[] = ['url' => $url, 'args' => $args];

    return WpStubs::$response;
}

/**
 * @param array<string, mixed>|WP_Error $response
 *
 * @return array<string, string|list<string>>
 */
function wp_remote_retrieve_headers(array|WP_Error $response): array
{
    return is_array($response) ? $response['headers'] : [];
}

/**
 * @param array<string, mixed>|WP_Error $response
 */
function wp_remote_retrieve_response_code(array|WP_Error $response): int|string
{
    return is_array($response) ? $response['response']['code'] : '';
}

/**
 * @param array<string, mixed>|WP_Error $response
 */
function wp_remote_retrieve_body(array|WP_Error $response): string
{
    return is_array($response) ? $response['body'] : '';
}

function get_option(string $name, mixed $default = false): mixed
{
    return WpStubs::$options[$name] ?? $default;
}

function sanitize_text_field(string $text): string
{
    return trim(strip_tags($text));
}

/**
 * @param list<string>|null $protocols
 */
function esc_url_raw(string $url, ?array $protocols = null): string
{
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

    return in_array($scheme, $protocols ?? ['http', 'https'], true) && parse_url($url, PHP_URL_HOST) ? $url : '';
}

function add_settings_error(string $setting, string $code, string $message, string $type = 'error'): void
{
    WpStubs::$settingsErrors[] = $code;
}

function get_post(int $post_id): ?WP_Post
{
    return WpStubs::$posts[$post_id] ?? null;
}

function wp_attachment_is_image(int $post_id): bool
{
    $post = WpStubs::$posts[$post_id] ?? null;

    return $post !== null && $post->post_type === 'attachment' && str_starts_with($post->post_mime_type, 'image/');
}

function get_attached_file(int $post_id): string|false
{
    return WpStubs::$files[$post_id] ?? false;
}

function get_post_meta(int $post_id, string $key = '', bool $single = false): mixed
{
    return WpStubs::$meta[$post_id][$key] ?? ($single ? '' : []);
}

/**
 * Like WordPress, it takes slashed data and stores it unslashed.
 */
function update_post_meta(int $post_id, string $key, mixed $value): bool
{
    WpStubs::$meta[$post_id][$key] = is_string($value) ? stripslashes($value) : $value;

    return true;
}

function wp_slash(mixed $value): mixed
{
    return is_string($value) ? addslashes($value) : $value;
}

function do_action(string $hook, mixed ...$args): void
{
    WpStubs::$actions[] = [$hook, $args];
}

/**
 * Saving a file always fails here: the tests only look at what would have
 * been saved.
 *
 * @return array{error: string}
 */
function wp_upload_bits(string $name, ?string $deprecated, string $bits): array
{
    WpStubs::$uploads[] = $name;

    return ['error' => 'no uploads in the unit tests'];
}
