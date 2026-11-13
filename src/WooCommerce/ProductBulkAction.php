<?php
/**
 * The bulk action on the products list.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress\WooCommerce;

use PhotosSh\WordPress\Admin\Flash;
use PhotosSh\WordPress\Operation;
use PhotosSh\WordPress\Queue\Batches;
use PhotosSh\WordPress\Queue\Scheduler;
use PhotosSh\WordPress\Queue\Task;
use PhotosSh\WordPress\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * "Remove background from product images" on WooCommerce's products list.
 */
final class ProductBulkAction {

	public const ACTION = 'photos_sh_product_remove_background';

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Schedules the tasks.
	 *
	 * @var Scheduler
	 */
	private Scheduler $scheduler;

	/**
	 * The batches.
	 *
	 * @var Batches
	 */
	private Batches $batches;

	/**
	 * One-time notices.
	 *
	 * @var Flash
	 */
	private Flash $flash;

	/**
	 * Sets the action up.
	 *
	 * @param Settings  $settings  The settings.
	 * @param Scheduler $scheduler Schedules the tasks.
	 * @param Batches   $batches   The batches.
	 * @param Flash     $flash     One-time notices.
	 */
	public function __construct( Settings $settings, Scheduler $scheduler, Batches $batches, Flash $flash ) {
		$this->settings  = $settings;
		$this->scheduler = $scheduler;
		$this->batches   = $batches;
		$this->flash     = $flash;
	}

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_filter( 'bulk_actions-edit-product', [ $this, 'bulk_actions' ] );
		add_filter( 'handle_bulk_actions-edit-product', [ $this, 'handle_bulk' ], 10, 3 );
	}

	/**
	 * Adds the action to the products list.
	 *
	 * @param mixed $actions The bulk actions.
	 * @return mixed
	 */
	public function bulk_actions( $actions ) {
		if ( is_array( $actions ) && current_user_can( 'edit_products' ) ) {
			$actions[ self::ACTION ] = __( 'Remove background from product images', 'photos-sh' );
		}

		return $actions;
	}

	/**
	 * Queues the selected products, one task each. WordPress has checked
	 * the list's nonce before it calls this.
	 *
	 * @param mixed $location Where WordPress goes next.
	 * @param mixed $action   The chosen bulk action.
	 * @param mixed $ids      The selected products.
	 * @return mixed
	 */
	public function handle_bulk( $location, $action, $ids ) {
		if ( self::ACTION !== $action || ! current_user_can( 'edit_products' ) ) {
			return $location;
		}

		$user = get_current_user_id();
		if ( '' === $this->settings->api_key() ) {
			$this->flash->add( $user, 'error', __( 'Photos.sh has no API key yet. Add one under Settings → Photos.sh.', 'photos-sh' ) );

			return $location;
		}

		$items = [];
		foreach ( is_array( $ids ) ? $ids : [] as $id ) {
			$id = absint( $id );
			if ( $id > 0 && 'product' === get_post_type( $id ) && current_user_can( 'edit_product', $id ) ) {
				$items[] = $id;
			}
		}
		$items = array_values( array_unique( $items ) );
		if ( [] === $items ) {
			$this->flash->add( $user, 'warning', __( 'None of the selected items is a product you can edit.', 'photos-sh' ) );

			return $location;
		}

		$operation = Operation::remove_background();
		$batch     = $this->batches->create( $user, $operation->key(), Task::KIND_PRODUCT, $items );
		foreach ( $items as $item ) {
			$this->scheduler->schedule( new Task( $batch, Task::KIND_PRODUCT, $item, $operation->key(), $user ) );
		}

		return $location;
	}
}
