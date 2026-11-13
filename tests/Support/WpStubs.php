<?php

declare(strict_types=1);

namespace PhotosSh\WordPress\Tests\Support;

/**
 * What the stubbed WordPress functions answer, and what they were given.
 */
final class WpStubs
{
    /** @var list<array{url: string, args: array<string, mixed>}> */
    public static array $requests = [];

    /** @var array<string, mixed>|\WP_Error */
    public static array|\WP_Error $response = [];

    /** @var array<string, mixed> */
    public static array $options = [];

    /** @var list<string> */
    public static array $settingsErrors = [];

    /** @var array<int, \WP_Post> */
    public static array $posts = [];

    /** @var array<int, string> post ID => the attachment's file */
    public static array $files = [];

    /** @var array<int, array<string, mixed>> post ID => meta key => value */
    public static array $meta = [];

    /** @var list<array{string, list<mixed>}> the actions fired: hook and arguments */
    public static array $actions = [];

    /** @var list<string> the file names given to wp_upload_bits() */
    public static array $uploads = [];

    public static function reset(): void
    {
        self::$requests = [];
        self::$response = [];
        self::$options = [];
        self::$settingsErrors = [];
        self::$posts = [];
        self::$files = [];
        self::$meta = [];
        self::$actions = [];
        self::$uploads = [];
    }

    /**
     * Puts an image attachment in the media library.
     */
    public static function image(int $id, string $file, string $alt = ''): void
    {
        self::$posts[$id] = new \WP_Post($id);
        self::$files[$id] = $file;
        if ($alt !== '') {
            self::$meta[$id]['_wp_attachment_image_alt'] = $alt;
        }
    }

    /**
     * @param array<string, string|list<string>> $headers
     *
     * @return array<string, mixed>
     */
    public static function httpResponse(int $status, array $headers = [], string $body = ''): array
    {
        return ['headers' => $headers, 'body' => $body, 'response' => ['code' => $status, 'message' => '']];
    }
}
