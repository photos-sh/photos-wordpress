<?php
/**
 * A failure on the WordPress side.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * An image could not be processed for a reason on this site (not an image,
 * the file is missing, the result could not be saved). The message is
 * translated and can be shown to the editor.
 */
final class ProcessingException extends \RuntimeException {
}
