<?php
/**
 * Names for the images the plugin makes.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * File names and titles of result attachments.
 */
final class Naming {

	/**
	 * The file name of a result: the source's name, the operation's suffix
	 * and the result's extension. `shoe.jpg` becomes `shoe-no-bg.png`.
	 *
	 * @param string $source_file The source's file name or path.
	 * @param string $suffix      The operation's suffix, such as `no-bg`.
	 * @param string $extension   The result's extension, without a dot.
	 * @return non-empty-string
	 */
	public static function filename( string $source_file, string $suffix, string $extension ): string {
		$base = pathinfo( str_replace( '\\', '/', $source_file ), PATHINFO_FILENAME );
		// WordPress adds "-scaled" to its downsized copy of a large upload.
		$base = (string) preg_replace( '/-scaled$/', '', $base );
		$base = trim( $base );
		if ( '' === $base ) {
			$base = 'image';
		}

		return $base . '-' . $suffix . '.' . strtolower( ltrim( $extension, '.' ) );
	}

	/**
	 * The title of a result: the source's title and what was done to it.
	 * `Red shoe` becomes `Red shoe (background removed)`.
	 *
	 * @param string $source_title The source attachment's title.
	 * @param string $done         What was done, such as `background removed`.
	 */
	public static function title( string $source_title, string $done ): string {
		$source_title = trim( $source_title );

		return '' === $source_title ? ucfirst( $done ) : sprintf( '%s (%s)', $source_title, $done );
	}
}
