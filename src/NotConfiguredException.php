<?php
/**
 * No API key.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * There is no API key yet.
 */
final class NotConfiguredException extends \RuntimeException {
}
