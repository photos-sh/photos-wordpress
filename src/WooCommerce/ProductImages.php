<?php
/**
 * Product images.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress\WooCommerce;

use PhotosSh\WordPress\Operation;
use PhotosSh\WordPress\ProcessingException;
use PhotosSh\WordPress\Processor;
use PhotosSh\WordPress\Queue\Outcome;

defined( 'ABSPATH' ) || exit;

/**
 * Replaces a product's main image with a processed copy. The old image
 * stays in the media library.
 */
final class ProductImages {

	/**
	 * Processes attachments.
	 *
	 * @var Processor
	 */
	private Processor $processor;

	/**
	 * Sets the service up.
	 *
	 * @param Processor $processor Processes attachments.
	 */
	public function __construct( Processor $processor ) {
		$this->processor = $processor;
	}

	/**
	 * Processes a product's main image and makes the result the main image.
	 *
	 * A main image that already is the result of the same operation is left
	 * alone, so that running the bulk action twice does not pay twice.
	 *
	 * @param int       $product_id The product.
	 * @param Operation $operation  What to do to its main image.
	 * @param int       $author     The new attachment's author.
	 * @return Outcome DONE with the new attachment, or SKIPPED.
	 * @throws ProcessingException When the product is gone or has no main image.
	 * @throws \Throwable          When processing the image failed.
	 */
	public function replace_main_image( int $product_id, Operation $operation, int $author = 0 ): Outcome {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( ! $product instanceof \WC_Product ) {
			throw new ProcessingException( esc_html__( 'The product no longer exists.', 'photos-sh' ) );
		}

		$image_id = (int) $product->get_image_id();
		if ( $image_id <= 0 ) {
			throw new ProcessingException( esc_html__( 'The product has no main image.', 'photos-sh' ) );
		}

		if ( get_post_meta( $image_id, Processor::META_OPERATION, true ) === $operation->name ) {
			return new Outcome( Outcome::SKIPPED, 0, __( 'The main image was already processed.', 'photos-sh' ) );
		}

		$new_id = $this->processor->process( $image_id, $operation, 0, $author );
		$product->set_image_id( $new_id );
		$product->save();

		return new Outcome( Outcome::DONE, $new_id );
	}
}
