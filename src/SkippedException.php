<?php
/**
 * Nothing was done to an image.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * An image was left as it is, for a reason the editor should know: no face
 * was found in it, or it already has alternative text. Nothing failed. The
 * message is translated and can be shown to the editor.
 */
final class SkippedException extends \RuntimeException {
}
